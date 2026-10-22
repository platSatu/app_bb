<?php

namespace App\Services\Withdrawal;

use App\Jobs\ProcessDisbursement;
use App\Jobs\SendInvoiceWebhook;
use App\Models\BankAccount;
use App\Models\Disbursement;
use App\Models\DisbursementRecipient;
use App\Models\DisbursementSetting;
use App\Models\InvoiceApiClient;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WalletTransaction;
use App\Services\Payment\DuitkuDisbursementClient;
use App\Services\Wallet\InsufficientBalanceException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Kirim dana dari saldo branch ke rekening penerima (dashboard atau API), pola sama dengan WithdrawalService:
 * saldo LANGSUNG ditahan -> antre -> inquiry + cocokkan nama -> transfer Duitku.
 * Gagal sebelum transfer = saldo dikembalikan. Transfer terkirim tapi hasil belum pasti = "Sedang Dicek",
 * TIDAK dikembalikan otomatis (mencegah uang keluar dua kali). Biaya & minimal mengikuti DisbursementSetting.
 */
class DisbursementService
{
    private const SUCCESS = '00';

    public function __construct(private readonly WalletService $wallets)
    {
    }

    /** Daftarkan / perbarui rekening penerima. $data: bank_code, account_number, [account_name, label, external_id]. */
    public function saveRecipient(UserBranch $branch, array $data, string $source, ?User $actor = null): DisbursementRecipient
    {
        $number = preg_replace('/\D/', '', (string) $data['account_number']);
        $client = DuitkuDisbursementClient::make();
        $bank = collect($client->banks())->firstWhere('bankCode', $data['bank_code']) ?? throw new RuntimeException('Kode bank tidak dikenali. Pilih bank dari daftar.');

        // Cek nama pemilik rekening ke bank (tidak memindahkan uang).
        $inquiry = $client->inquiry($bank['bankCode'], $number, 10000, 'Verifikasi rekening');

        if (($inquiry['responseCode'] ?? null) !== self::SUCCESS || blank($inquiry['accountName'])) {
            throw new RuntimeException('Rekening tidak ditemukan atau belum bisa dicek: '.($inquiry['responseDesc'] ?? 'tidak ada jawaban').'. Periksa lagi nomor rekening dan bank-nya.');
        }

        if (filled($data['account_name'] ?? null) && ! BankAccountService::sameName($inquiry['accountName'], $data['account_name'])) {
            throw new RuntimeException('Nama pemilik rekening di bank ('.$inquiry['accountName'].') tidak sama dengan nama yang diisi ('.$data['account_name'].').');
        }

        return DB::transaction(function () use ($branch, $data, $source, $actor, $bank, $number, $inquiry) {
            UserBranch::whereKey($branch->getKey())->lockForUpdate()->first();

            $externalId = filled($data['external_id'] ?? null) ? $data['external_id'] : null;
            $hash = BankAccount::hash($bank['bankCode'], $number);
            $byExternal = $externalId ? DisbursementRecipient::where('branch_id', $branch->getKey())->where('external_id', $externalId)->first() : null;
            $byHash = DisbursementRecipient::where('branch_id', $branch->getKey())->where('account_hash', $hash)->first();

            if ($byHash && (($byExternal && ! $byExternal->is($byHash)) || ($externalId && $byHash->external_id && $byHash->external_id !== $externalId))) {
                throw new RuntimeException('Rekening ini sudah terdaftar untuk penerima lain'.($byHash->label ? ' ('.$byHash->label.')' : '').'.');
            }

            $recipient = $byExternal ?? $byHash ?? new DisbursementRecipient();
            $recipient->forceFill([
                'branch_id' => $branch->getKey(),
                'external_id' => $externalId ?? $recipient->external_id,
                'label' => filled($data['label'] ?? null) ? Str::limit(trim($data['label']), 150, '') : $recipient->label,
                'bank_code' => $bank['bankCode'],
                'bank_name' => $bank['bankName'],
                'account_number' => $number,
                'account_hash' => $hash,
                'account_last4' => substr($number, -4),
                'account_name' => Str::limit(trim($inquiry['accountName']), 150, ''),
                'status' => DisbursementRecipient::STATUS_ACTIVE,
                'source' => $recipient->source ?? $source,
                'created_by' => $recipient->created_by ?? $actor?->getKey(),
            ])->save();

            return $recipient;
        });
    }

    /**
     * Tahan saldo lalu antrekan transfer. external_id yang sama = kiriman yang sama (tidak dikirim ulang).
     *
     * @return array{0: Disbursement, 1: bool} [kiriman, baru dibuat?]
     */
    public function send(DisbursementRecipient $recipient, int $amount, ?string $description, string $source, ?string $externalId = null, ?InvoiceApiClient $client = null, ?User $actor = null): array
    {
        $setting = DisbursementSetting::current();

        if (! $setting->is_active || ! $setting->isConfigured()) {
            throw new RuntimeException('Disbursement sedang tidak tersedia. Silakan coba lagi nanti.');
        }

        if ($amount < $setting->min_amount) {
            throw new RuntimeException('Minimal disbursement '.$this->rupiah($setting->min_amount).'.');
        }

        $net = $amount - $setting->withdrawal_fee;

        if ($net < 10000) {
            throw new RuntimeException('Jumlah yang diterima setelah biaya minimal Rp 10.000.');
        }

        if ($client?->payout_max_amount && $amount > $client->payout_max_amount) {
            throw new RuntimeException('Melebihi batas per transaksi '.$this->rupiah($client->payout_max_amount).'.');
        }

        [$disbursement, $created] = DB::transaction(function () use ($recipient, $amount, $net, $description, $source, $externalId, $client, $actor, $setting) {
            // Kunci branch: kiriman bersamaan antre, batas harian & external_id tidak bisa diakali.
            $branch = UserBranch::with('user')->whereKey($recipient->branch_id)->lockForUpdate()->firstOrFail();

            if ($externalId && $existing = Disbursement::where('branch_id', $branch->getKey())->where('external_id', $externalId)->first()) {
                return [$existing, false];
            }

            $locked = DisbursementRecipient::whereKey($recipient->getKey())->first();

            if (! $locked?->isActive()) {
                throw new RuntimeException('Rekening penerima tidak aktif.');
            }

            if ($client?->payout_daily_limit) {
                $today = (int) Disbursement::where('branch_id', $branch->getKey())->where('api_client_id', $client->getKey())
                    ->where('created_at', '>=', today())->where('status', '!=', Disbursement::STATUS_FAILED)->sum('amount');

                if ($today + $amount > $client->payout_daily_limit) {
                    throw new RuntimeException('Melebihi batas harian '.$this->rupiah($client->payout_daily_limit).'. Sudah terpakai hari ini '.$this->rupiah($today).'.');
                }
            }

            $disbursement = new Disbursement();
            $disbursement->forceFill([
                'branch_id' => $branch->getKey(),
                'user_id' => $branch->user_id,
                'recipient_id' => $locked->getKey(),
                'api_client_id' => $client?->getKey(),
                'source' => $source,
                'external_id' => $externalId,
                'reference' => $this->uniqueReference(),
                'amount' => $amount,
                'fee' => $setting->withdrawal_fee,
                'net_amount' => $net,
                'bank_code' => $locked->bank_code,
                'bank_name' => $locked->bank_name,
                'account_last4' => $locked->account_last4,
                'account_name' => $locked->account_name,
                'description' => filled($description) ? Str::limit(trim($description), 255, '') : null,
                'status' => Disbursement::STATUS_PENDING,
                'created_by' => $actor?->getKey(),
            ])->save();

            try {
                $this->hold($disbursement, $branch->user, $actor);
            } catch (InsufficientBalanceException $e) {
                throw new RuntimeException($e->getMessage().' Yang bisa dikirim saat ini '.$this->rupiah($e->usable()).'.');
            }

            return [$disbursement->refresh(), true];
        });

        if ($created) {
            ProcessDisbursement::dispatch($disbursement->getKey());
        }

        return [$disbursement, $created];
    }

    /** Dijalankan job: hanya kiriman berstatus "pending" yang diproses, satu kali. */
    public function process(string $id): void
    {
        $claimed = Disbursement::whereKey($id)->where('status', Disbursement::STATUS_PENDING)
            ->update(['status' => Disbursement::STATUS_PROCESSING, 'updated_at' => now()]);

        if (! $claimed) {
            return;
        }

        $disbursement = Disbursement::with('recipient')->findOrFail($id);
        $transferSent = false;
        $inquiry = null;

        try {
            if (! $disbursement->recipient?->isActive()) {
                $this->fail($disbursement, 'Rekening penerima dinonaktifkan sebelum dana dikirim.');

                return;
            }

            $client = DuitkuDisbursementClient::make();
            $purpose = 'Disbursement '.$disbursement->reference;
            $number = $disbursement->recipient->account_number;

            $inquiry = $client->inquiry($disbursement->bank_code, $number, $disbursement->net_amount, $purpose);

            if (($inquiry['responseCode'] ?? null) !== self::SUCCESS || ! $inquiry['disburseId']) {
                $this->fail($disbursement, 'Inquiry Duitku ditolak: '.($inquiry['responseDesc'] ?? '-').' (kode '.($inquiry['responseCode'] ?? '?').')', ['inquiry' => $inquiry['raw']]);

                return;
            }

            if (! BankAccountService::sameName($inquiry['accountName'], $disbursement->account_name)) {
                $this->fail($disbursement, 'Nama pemilik rekening di bank ('.($inquiry['accountName'] ?: '-').') berbeda dengan rekening terdaftar. Dana tidak dikirim.', ['inquiry' => $inquiry['raw']]);

                return;
            }

            $custRef = $client->custRefNumber();
            $disbursement->forceFill(['disburse_id' => $inquiry['disburseId'], 'cust_ref_number' => $custRef])->save();

            $transferSent = true;
            $transfer = $client->transfer($inquiry['disburseId'], $disbursement->bank_code, $number, $disbursement->net_amount, $inquiry['accountName'], $purpose, $custRef);
            $responses = ['inquiry' => $inquiry['raw'], 'transfer' => $transfer];

            if (($transfer['responseCode'] ?? null) !== self::SUCCESS) {
                $this->needsReview($disbursement, 'Jawaban transfer Duitku: '.($transfer['responseDesc'] ?? '-').' (kode '.($transfer['responseCode'] ?? '?').')', $responses);

                return;
            }

            $disbursement->forceFill(['status' => Disbursement::STATUS_SUCCESS, 'duitku_response' => $responses, 'processed_at' => now()])->save();
            $this->notify($disbursement, 'payout.success');
        } catch (Throwable $e) {
            report($e);

            $transferSent
                ? $this->needsReview($disbursement, 'Koneksi ke Duitku terputus saat transfer: '.$e->getMessage(), ['inquiry' => $inquiry['raw'] ?? null])
                : $this->fail($disbursement, $e->getMessage());
        }
    }

    public function checkStatus(Disbursement $disbursement): string
    {
        if (! $disbursement->disburse_id) {
            return 'Belum ada ID transaksi Duitku (transfer belum sempat dikirim).';
        }

        try {
            $status = DuitkuDisbursementClient::make()->inquiryStatus($disbursement->disburse_id);
        } catch (Throwable $e) {
            return 'Duitku belum bisa dihubungi: '.$e->getMessage();
        }

        return 'Jawaban Duitku: '.($status['responseDesc'] ?? '-').' (kode '.($status['responseCode'] ?? '?').'). Cocokkan juga dengan dashboard Duitku › Laporan Disbursement.';
    }

    /** Tim bizbos memastikan hasil transfer yang belum pasti. Tidak terkirim = saldo dikembalikan. */
    public function resolve(Disbursement $disbursement, User $admin, bool $sent, string $note): void
    {
        $resolved = DB::transaction(function () use ($disbursement, $admin, $sent, $note) {
            $locked = Disbursement::whereKey($disbursement->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->needsResolve()) {
                throw new RuntimeException('Kiriman ini sudah diselesaikan sebelumnya.');
            }

            $locked->forceFill([
                'status' => $sent ? Disbursement::STATUS_SUCCESS : Disbursement::STATUS_FAILED,
                'failure_reason' => trim(($locked->failure_reason ? $locked->failure_reason.' | ' : '').'Dicek '.$admin->name.': '.$note),
                'processed_at' => now(),
            ])->save();

            if (! $sent) {
                $this->refund($locked, 'transfer tidak terkirim');
            }

            return $locked;
        });

        $this->notify($resolved, $sent ? 'payout.success' : 'payout.failed');
    }

    private function hold(Disbursement $disbursement, User $owner, ?User $actor): void
    {
        // Duitku hanya bisa mengirim dana yang sudah settlement; cek & kunci wallet sebelum memotong.
        $this->wallets->assertSettled($owner, $disbursement->amount);

        $target = $disbursement->bank_name.' ••••'.$disbursement->account_last4.' a.n. '.$disbursement->account_name;
        $this->wallets->debit($owner, $disbursement->net_amount, WalletTransaction::TYPE_DISBURSEMENT, 'disbursement:'.$disbursement->getKey(), $disbursement, "Disbursement {$disbursement->reference} ke {$target}", $actor);

        if ($disbursement->fee > 0) {
            $this->wallets->debit($owner, $disbursement->fee, WalletTransaction::TYPE_DISBURSEMENT_FEE, 'disbursement-fee:'.$disbursement->getKey(), $disbursement, "Biaya disbursement {$disbursement->reference}", $actor);
        }

        $disbursement->forceFill(['held_at' => now()])->save();
    }

    private function refund(Disbursement $disbursement, string $reason): void
    {
        $locked = Disbursement::with('user')->whereKey($disbursement->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->held_at || $locked->refunded_at) {
            return;
        }

        $this->wallets->credit($locked->user, $locked->net_amount, WalletTransaction::TYPE_DISBURSEMENT_REFUND, 'disbursement-refund:'.$locked->getKey(), $locked, "Pengembalian disbursement {$locked->reference} ({$reason})");

        if ($locked->fee > 0) {
            $this->wallets->credit($locked->user, $locked->fee, WalletTransaction::TYPE_DISBURSEMENT_REFUND, 'disbursement-fee-refund:'.$locked->getKey(), $locked, "Pengembalian biaya disbursement {$locked->reference} ({$reason})");
        }

        $locked->forceFill(['refunded_at' => now()])->save();
    }

    private function fail(Disbursement $disbursement, string $reason, ?array $responses = null): void
    {
        DB::transaction(function () use ($disbursement, $reason, $responses) {
            $disbursement->forceFill(array_filter([
                'status' => Disbursement::STATUS_FAILED,
                'failure_reason' => $reason,
                'duitku_response' => $responses,
                'processed_at' => now(),
            ], fn ($v) => $v !== null))->save();

            $this->refund($disbursement, 'gagal diproses');
        });

        $this->notify($disbursement, 'payout.failed');
    }

    private function needsReview(Disbursement $disbursement, string $reason, array $responses): void
    {
        $disbursement->forceFill(['status' => Disbursement::STATUS_NEEDS_REVIEW, 'failure_reason' => $reason, 'duitku_response' => $responses, 'processed_at' => now()])->save();
        Log::critical('disbursement: hasil transfer belum pasti, perlu dicek tim bizbos', ['disbursement_id' => $disbursement->getKey(), 'reason' => $reason]);

        $this->notify($disbursement, 'payout.needs_review');
    }

    private function notify(Disbursement $disbursement, string $event): void
    {
        SendInvoiceWebhook::forDisbursement($disbursement, $event);
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'DS'.now()->format('ymd').Str::upper(Str::random(6));
        } while (Disbursement::where('reference', $reference)->exists());

        return $reference;
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
