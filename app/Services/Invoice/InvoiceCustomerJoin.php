<?php

namespace App\Services\Invoice;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserBranch;

/**
 * Akun pembayar bergabung sebagai customer Invoice di satu branch (lewat link pendaftaran).
 * Customer dicocokkan lewat EMAIL akun saja: data customer lain (nama, nomor) tidak pernah ditimpa,
 * dan tagihan baru terlihat setelah email akun terverifikasi (lihat PayerInvoices).
 * Dipanggil di dalam transaksi; baris branch dikunci supaya dua klik bersamaan tidak membuat customer kembar.
 */
class InvoiceCustomerJoin
{
    public function join(UserBranch $branch, User $user): Customer
    {
        UserBranch::whereKey($branch->getKey())->lockForUpdate()->value('id');

        $email = strtolower(trim((string) $user->email));
        $customers = Customer::forApp('invoices')->where('branch_id', $branch->getKey());

        if ($customer = (clone $customers)->where('email', $email)->first()) {
            $customer->forceFill(['last_seen_at' => now()])->save();

            return $customer;
        }

        // Nomor WhatsApp hanya diisi kalau belum dipakai customer lain di branch ini (unik per branch & aplikasi).
        $phone = $user->handphone && ! (clone $customers)->where('phone', $user->handphone)->exists() ? $user->handphone : null;

        $customer = new Customer();
        $customer->forceFill([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->getKey(),
            'app_key' => 'invoices',
            'source' => 'Link Pendaftaran',
            'name' => \Illuminate\Support\Str::limit($user->name, 150, ''),
            'phone' => $phone,
            'email' => $email,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return $customer;
    }
}
