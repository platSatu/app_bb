<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserBranch;

class CustomerService
{
    /**
     * Cari customer aplikasi $app di branch lewat WhatsApp, lalu email; kalau tidak ada, buat baru.
     * Customer aplikasi lain tidak disentuh (customer Pages belum tentu customer Invoice).
     * WAJIB dipanggil di dalam transaksi. Kontak kembar dicegah unique index (branch_id + phone/email):
     * pemanggil boleh mengunci baris branch dulu, atau (checkout tiket, demi war ticket) mencoba ulang
     * saat terjadi UniqueConstraintViolationException.
     */
    public function resolve(UserBranch $branch, string $app, ?string $name, ?string $phone, ?string $email, string $source): Customer
    {
        $phone = User::normalizePhone($phone) ?: null;
        $email = filled($email) ? strtolower(trim($email)) : null;
        $name = filled($name) ? trim($name) : null;

        $customer = $this->find($branch, $app, 'phone', $phone) ?? $this->find($branch, $app, 'email', $email);

        if (! $customer) {
            $customer = new Customer();
            $customer->forceFill([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->getKey(),
                'app_key' => $app,
                'source' => $source,
                'first_seen_at' => now(),
            ]);
        }

        // Data yang sudah ada tidak ditimpa kosong; WA/email baru hanya diisi kalau belum dipakai kontak lain.
        $customer->forceFill([
            'name' => $name ?? $customer->name,
            'phone' => $customer->phone ?? ($this->taken($branch, $app, 'phone', $phone, $customer) ? null : $phone),
            'email' => $customer->email ?? ($this->taken($branch, $app, 'email', $email, $customer) ? null : $email),
            'last_seen_at' => now(),
        ])->save();

        return $customer;
    }

    private function find(UserBranch $branch, string $app, string $column, ?string $value): ?Customer
    {
        return $value === null ? null : Customer::where('branch_id', $branch->getKey())->forApp($app)->where($column, $value)->lockForUpdate()->first();
    }

    private function taken(UserBranch $branch, string $app, string $column, ?string $value, Customer $self): bool
    {
        return $value !== null && Customer::where('branch_id', $branch->getKey())->forApp($app)
            ->where($column, $value)
            ->when($self->exists, fn ($q) => $q->whereKeyNot($self->getKey()))
            ->exists();
    }
}
