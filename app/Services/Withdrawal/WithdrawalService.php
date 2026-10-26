<?php

namespace App\Services\Withdrawal;

use App\Models\BankAccount;
use App\Models\DisbursementSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Services\Payment\DuitkuDisbursementClient;
use App\Services\Wallet\InsufficientBalanceException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Tarik saldo (pola teleios yang sudah berjalan):
 * ajukan -> saldo LANGSUNG ditahan (dipotong) -> superadmin setujui -> inquiry + transfer Duitku.
 * Gagal sebelum transfer = saldo dikembalikan. Transfer sudah terkirim tapi hasil belum pasti =
 * "Sedang Dicek Admin", TIDAK dikembalikan otomatis (mencegah uang keluar dua kali).
 * Semua mutasi saldo lewat WalletService dengan idempotency key, jadi tidak pernah dobel.
 */
class WithdrawalService
{
    private const SUCCESS = '00';

    /** Pengajuan yang boleh menunggu bersamaan per user; mencegah saldo ditahan berkali-kali lewat spam pengajuan. */
    public const MAX_PENDING = 3;

    public function __construct(private readonly WalletService $wallets)
    {
    }

    public function request(User $user, int $amount, ?int $expectedFee): WalletWithdrawal
    {
        $setting = DisbursementSetting::current();

        if (! $setting->is_active || ! $setting->isConfigured()) {
            throw new RuntimeException('Tarik saldo sedang tidak tersedia. Silakan coba lagi nanti.');
        }

        $account = BankAccount::where('user_id', $user->getKey())->where('status', BankAccount::STATUS_APPROVED)->first()
            ?? throw new RuntimeException('Tambahkan rekening dan tunggu verifikasi admin dulu ya.');

        if ($expectedFee !== null && $expectedFee !== $setting->withdrawal_fee) {
            throw new RuntimeException('Biaya penarikan baru saja berubah menjadi '.$this->rupiah($setting->withdrawal_fee).'. Cek lagi jumlah yang diterima, lalu ajukan ulang.');
        }

        if ($amount < $setting->min_amount) {
            throw new RuntimeException('Minimal tarik saldo '.$this->rupiah($setting->min_amount).'.');
        }

        $net = $amount - $setting->withdrawal_fee;

        if ($net < 10000) {
            throw new RuntimeException('Jumlah yang diterima setelah biaya minimal Rp 10.000.');
        }

        return DB::transaction(function () use ($user, $account, $amount, $net, $setting) {
            // Kunci user: pengajuan bersamaan dari user yang sama antre, jadi batas di bawah tidak bisa dilewati.
            User::whereKey($user->getKey())->lockForUpdate()->first();

            if (WalletWithdrawal::where('user_id', $user->getKey())->whereIn('status', [WalletWithdrawal::STATUS_PENDING, WalletWithdrawal::STATUS_PROCESSING])->count() >= self::MAX_PENDING) {
                throw new RuntimeException('Masih ada '.self::MAX_PENDING.' pengajuan yang menunggu diproses. Tunggu sampai selesai, atau batalkan salah satunya dulu.');
            }

            $withdrawal = new WalletWithdrawal();
            $withdrawal->forceFill([
                'user_id' => $user->getKey(),
                'bank_account_id' => $account->getKey(),
                'reference' => $this->uniqueReference(),
                'amount' => $amount,
                'fee' => $setting->withdrawal_fee,
                'net_amount' => $net,
                'bank_code' => $account->bank_code,
                'bank_name' => $account->bank_name,
                'account_last4' => $account->account_last4,
                'account_name' => $account->account_name,
                'status' => WalletWithdrawal::STATUS_PENDING,
            ])->save();

            try {
                $this->hold($withdrawal, $user);
            } catch (InsufficientBalanceException $e) {
                throw new RuntimeException($e->getMessage().' Yang bisa ditarik saat ini '.$this->rupiah($e->usable()).'.');
            }

            return $withdrawal->refresh();
        });
    }

    public function cancel(WalletWithdrawal $withdrawal, User $user): void
    {
        abort_unless($withdrawal->user_id === $user->getKey(), 404);

        DB::transaction(function () use ($withdrawal) {
            $this->claim($withdrawal, WalletWithdrawal::STATUS_CANCELLED, [], 'Hanya pengajuan yang masih menunggu persetujuan yang bisa dibatalkan.');
            $this->refund($withdrawal, 'dibatalkan');
        });
    }

    public function reject(WalletWithdrawal $withdrawal, User $admin, string $reason): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $this->claim($withdrawal, WalletWithdrawal::STATUS_REJECTED, ['approved_by' => $admin->getKey(), 'approved_at' => now(), 'rejection_reason' => $reason]);
            $this->refund($withdrawal, 'ditolak');
        });
    }

    /** Setujui lalu langsung kirim lewat Duitku. */
    public function approve(WalletWithdrawal $withdrawal, User $admin): WalletWithdrawal
    {
        DB::transaction(fn () => $this->claim($withdrawal, WalletWithdrawal::STATUS_PROCESSING, ['approved_by' => $admin->getKey(), 'approved_at' => now()]));

        $withdrawal = $withdrawal->refresh()->load('bankAccount');
        $transferSent = false;
        $inquiry = null;

        // Dana hanya dikirim ke rekening milik user ini yang masih aktif (bukan rekening yang sudah diganti/ditolak).
        $account = $withdrawal->bankAccount;
        if (! $account || $account->user_id !== $withdrawal->user_id || $account->status !== BankAccount::STATUS_APPROVED
            || $account->bank_code !== $withdrawal->bank_code || $account->account_last4 !== $withdrawal->account_last4) {
            return $this->fail($withdrawal, 'Rekening tujuan sudah tidak aktif atau berubah sejak pengajuan dibuat. Dana tidak dikirim, silakan ajukan ulang ke rekening yang aktif.');
        }

        try {
            $client = DuitkuDisbursementClient::make();
            $purpose = 'Tarik saldo '.$withdrawal->reference;
            $number = $withdrawal->bankAccount->account_number;

            $inquiry = $client->inquiry($withdrawal->bank_code, $number, $withdrawal->net_amount, $purpose);

            if (($inquiry['responseCode'] ?? null) !== self::SUCCESS || ! $inquiry['disburseId']) {
                return $this->fail($withdrawal, 'Inquiry Duitku ditolak: '.($inquiry['responseDesc'] ?? '-').' (kode '.($inquiry['responseCode'] ?? '?').')', ['inquiry' => $inquiry['raw']]);
            }

            if (! BankAccountService::sameName($inquiry['accountName'], $withdrawal->account_name)) {
                return $this->fail($withdrawal, 'Nama pemilik rekening di bank ('.($inquiry['accountName'] ?: '-').') berbeda dengan rekening terdaftar. Dana tidak dikirim.', ['inquiry' => $inquiry['raw']]);
            }

            $custRef = $client->custRefNumber();
            $withdrawal->forceFill(['disburse_id' => $inquiry['disburseId'], 'cust_ref_number' => $custRef])->save();

            $transferSent = true;
            $transfer = $client->transfer($inquiry['disburseId'], $withdrawal->bank_code, $number, $withdrawal->net_amount, $inquiry['accountName'], $purpose, $custRef);
            $responses = ['inquiry' => $inquiry['raw'], 'transfer' => $transfer];

            if (($transfer['responseCode'] ?? null) !== self::SUCCESS) {
                return $this->needsReview($withdrawal, 'Jawaban transfer Duitku: '.($transfer['responseDesc'] ?? '-').' (kode '.($transfer['responseCode'] ?? '?').')', $responses);
            }

            $withdrawal->forceFill(['status' => WalletWithdrawal::STATUS_SUCCESS, 'duitku_response' => $responses, 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);

            return $transferSent
                ? $this->needsReview($withdrawal, 'Koneksi ke Duitku terputus saat transfer: '.$e->getMessage(), ['inquiry' => $inquiry['raw'] ?? null])
                : $this->fail($withdrawal, $e->getMessage());
        }

        return $withdrawal->refresh();
    }

    public function checkStatus(WalletWithdrawal $withdrawal): string
    {
        if (! $withdrawal->disburse_id) {
            return 'Belum ada ID transaksi Duitku (transfer belum sempat dikirim).';
        }

        try {
            $status = DuitkuDisbursementClient::make()->inquiryStatus($withdrawal->disburse_id);
        } catch (Throwable $e) {
            return 'Duitku belum bisa dihubungi: '.$e->getMessage();
        }

        return 'Jawaban Duitku: '.($status['responseDesc'] ?? '-').' (kode '.($status['responseCode'] ?? '?').'). Cocokkan juga dengan dashboard Duitku › Laporan Disbursement.';
    }

    /** Admin memastikan hasil transfer yang belum pasti. Tidak terkirim = saldo dikembalikan. */
    public function resolve(WalletWithdrawal $withdrawal, User $admin, bool $sent, string $note): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $sent, $note) {
            $locked = WalletWithdrawal::whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->needsResolve()) {
                throw new RuntimeException('Pengajuan ini sudah diselesaikan sebelumnya.');
            }

            $locked->forceFill([
                'status' => $sent ? WalletWithdrawal::STATUS_SUCCESS : WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => trim(($locked->failure_reason ? $locked->failure_reason.' | ' : '').'Dicek '.$admin->name.': '.$note),
                'processed_at' => now(),
            ])->save();

            if (! $sent) {
                $this->refund($locked, 'transfer tidak terkirim');
            }
        });
    }

    /** Pindah status hanya dari "pending"; dua klik bersamaan, hanya satu yang menang. */
    private function claim(WalletWithdrawal $withdrawal, string $status, array $changes, string $error = 'Pengajuan ini sudah diproses sebelumnya.'): void
    {
        $claimed = WalletWithdrawal::whereKey($withdrawal->getKey())->where('status', WalletWithdrawal::STATUS_PENDING)
            ->update(['status' => $status, ...$changes, 'updated_at' => now()]);

        if (! $claimed) {
            throw new RuntimeException($error);
        }
    }

    private function hold(WalletWithdrawal $withdrawal, User $user): void
    {
        // Hanya saldo yang sudah settlement Duitku (sekaligus mengunci wallet sampai transaksi selesai).
        $this->wallets->assertSettled($user, $withdrawal->amount);

        $target = $withdrawal->bank_name.' ••••'.$withdrawal->account_last4;
        $this->wallets->debit($user, $withdrawal->net_amount, WalletTransaction::TYPE_WITHDRAWAL, 'withdrawal:'.$withdrawal->getKey(), $withdrawal, "Tarik saldo {$withdrawal->reference} ke {$target}", $user);

        if ($withdrawal->fee > 0) {
            $this->wallets->debit($user, $withdrawal->fee, WalletTransaction::TYPE_WITHDRAWAL_FEE, 'withdrawal-fee:'.$withdrawal->getKey(), $withdrawal, "Biaya tarik saldo {$withdrawal->reference}", $user);
        }

        $withdrawal->forceFill(['held_at' => now()])->save();
    }

    private function refund(WalletWithdrawal $withdrawal, string $reason): void
    {
        $locked = WalletWithdrawal::with('user')->whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->held_at || $locked->refunded_at) {
            return;
        }

        $this->wallets->credit($locked->user, $locked->net_amount, WalletTransaction::TYPE_WITHDRAWAL_REFUND, 'withdrawal-refund:'.$locked->getKey(), $locked, "Pengembalian tarik saldo {$locked->reference} ({$reason})");

        if ($locked->fee > 0) {
            $this->wallets->credit($locked->user, $locked->fee, WalletTransaction::TYPE_WITHDRAWAL_REFUND, 'withdrawal-fee-refund:'.$locked->getKey(), $locked, "Pengembalian biaya tarik saldo {$locked->reference} ({$reason})");
        }

        $locked->forceFill(['refunded_at' => now()])->save();
    }

    private function fail(WalletWithdrawal $withdrawal, string $reason, ?array $responses = null): WalletWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $reason, $responses) {
            $withdrawal->forceFill(array_filter([
                'status' => WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => $reason,
                'duitku_response' => $responses,
                'processed_at' => now(),
            ], fn ($v) => $v !== null))->save();

            $this->refund($withdrawal, 'gagal diproses');
        });

        return $withdrawal->refresh();
    }

    private function needsReview(WalletWithdrawal $withdrawal, string $reason, array $responses): WalletWithdrawal
    {
        $withdrawal->forceFill(['status' => WalletWithdrawal::STATUS_NEEDS_REVIEW, 'failure_reason' => $reason, 'duitku_response' => $responses, 'processed_at' => now()])->save();
        Log::critical('withdrawal: hasil transfer belum pasti, perlu dicek admin', ['withdrawal_id' => $withdrawal->getKey(), 'reason' => $reason]);

        return $withdrawal->refresh();
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'WD'.now()->format('ymd').Str::upper(Str::random(6));
        } while (WalletWithdrawal::where('reference', $reference)->exists());

        return $reference;
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
