<?php

namespace App\Services\Withdrawal;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\Payment\DuitkuDisbursementClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rekening tujuan tarik saldo. Nama pemilik diambil dari bank (inquiry Duitku), bukan ketikan user,
 * lalu tetap dicek superadmin sebelum aktif. Rekening baru yang disetujui menggantikan yang lama.
 */
class BankAccountService
{
    public function submit(User $user, string $bankCode, string $number): BankAccount
    {
        $number = preg_replace('/\D/', '', $number);
        $client = DuitkuDisbursementClient::make();
        $bank = collect($client->banks())->firstWhere('bankCode', $bankCode) ?? throw new RuntimeException('Bank tidak dikenali, silakan pilih ulang.');

        // Cek nama pemilik rekening ke bank (tidak memindahkan uang).
        $inquiry = $client->inquiry($bankCode, $number, 10000, 'Verifikasi rekening');

        if (($inquiry['responseCode'] ?? null) !== '00' || blank($inquiry['accountName'])) {
            throw new RuntimeException('Rekening tidak ditemukan atau belum bisa dicek: '.($inquiry['responseDesc'] ?? 'tidak ada jawaban').'. Periksa lagi nomor rekening dan bank-nya.');
        }

        return DB::transaction(function () use ($user, $bank, $number, $inquiry) {
            User::whereKey($user->getKey())->lockForUpdate()->first();

            // Hanya boleh 1 pengajuan yang menunggu verifikasi.
            BankAccount::where('user_id', $user->getKey())->where('status', BankAccount::STATUS_PENDING)->delete();

            $account = new BankAccount();
            $account->forceFill([
                'user_id' => $user->getKey(),
                'bank_code' => $bank['bankCode'],
                'bank_name' => $bank['bankName'],
                'account_number' => $number,
                'account_hash' => BankAccount::hash($bank['bankCode'], $number),
                'account_last4' => substr($number, -4),
                'account_name' => Str::limit(trim($inquiry['accountName']), 150, ''),
                'status' => BankAccount::STATUS_PENDING,
            ])->save();

            return $account;
        });
    }

    public function review(BankAccount $account, User $admin, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($account, $admin, $approve, $note) {
            $locked = BankAccount::whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== BankAccount::STATUS_PENDING) {
                throw new RuntimeException('Rekening ini sudah diverifikasi sebelumnya.');
            }

            if ($approve) {
                BankAccount::where('user_id', $locked->user_id)->where('status', BankAccount::STATUS_APPROVED)->update(['status' => BankAccount::STATUS_REPLACED]);
            }

            $locked->forceFill([
                'status' => $approve ? BankAccount::STATUS_APPROVED : BankAccount::STATUS_REJECTED,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();
        });
    }

    /** Nama sama walau beda huruf besar/kecil, spasi, titik, atau gelar seperti "BPK/IBU". */
    public static function sameName(?string $a, ?string $b): bool
    {
        $normalize = fn ($v) => preg_replace('/\b(BPK|BP|IBU|SDR|SDRI|TN|NY)\b|[^A-Z]/', '', strtoupper((string) $v));

        return $normalize($a) !== '' && $normalize($a) === $normalize($b);
    }
}
