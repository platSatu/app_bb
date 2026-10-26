<?php

use App\Models\Disbursement;
use App\Models\DisbursementRecipient;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Support\Str;

function payoutRecipient(UserBranch $branch, string $number): DisbursementRecipient
{
    return tap((new DisbursementRecipient())->forceFill([
        'branch_id' => $branch->id, 'bank_code' => '014', 'bank_name' => 'BCA', 'account_number' => $number,
        'account_hash' => hash('sha256', $number), 'account_last4' => substr($number, -4), 'account_name' => 'PENERIMA',
    ]))->save();
}

function payout(DisbursementRecipient $recipient, int $amount, int $fee, string $status, string $createdAt = 'now'): Disbursement
{
    $branch = UserBranch::find($recipient->branch_id);

    return tap((new Disbursement())->forceFill([
        'branch_id' => $branch->id, 'user_id' => $branch->user_id, 'recipient_id' => $recipient->id,
        'reference' => 'DB'.Str::upper(Str::random(8)), 'amount' => $amount, 'fee' => $fee, 'net_amount' => $amount - $fee,
        'bank_code' => '014', 'bank_name' => 'BCA', 'account_last4' => $recipient->account_last4, 'account_name' => 'PENERIMA',
        'status' => $status, 'created_at' => $createdAt, 'updated_at' => $createdAt,
    ]))->save();
}

it('kotak disbursement owner: total, jumlah rekening, dan fee dari yang berhasil saja, ikut filter tanggal', function () {
    $branch = appBranch(['invoices']);
    [$a, $b] = [payoutRecipient($branch, '1110001111'), payoutRecipient($branch, '2220002222')];

    payout($a, 100000, 5000, Disbursement::STATUS_SUCCESS, '2026-10-05 10:00:00');
    payout($a, 200000, 5000, Disbursement::STATUS_SUCCESS, '2026-10-06 10:00:00');
    payout($b, 50000, 5000, Disbursement::STATUS_SUCCESS, '2026-10-07 10:00:00');
    payout($b, 900000, 5000, Disbursement::STATUS_FAILED, '2026-10-07 11:00:00');          // gagal: tidak dihitung
    payout($b, 70000, 5000, Disbursement::STATUS_SUCCESS, '2026-09-30 10:00:00');          // di luar tanggal

    $superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $this->actingAs($superadmin)->get(route('superadmin.payouts.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
        ->assertOk()
        ->assertSeeInOrder(['Total Penarikan', 'Rp 350.000', '3 disbursement berhasil', 'Rp 335.000 diterima rekening',
            'Rekening Penerima', '2 rekening', 'Total Fee', 'Rp 15.000'])
        ->assertSee(route('superadmin.payouts.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'status' => Disbursement::STATUS_SUCCESS]));
});
