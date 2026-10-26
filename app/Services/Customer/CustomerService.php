<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    /**
     * Pengunjung live chat bizbos.id: customer aplikasi "website" milik platform (tanpa company/branch).
     * Dicari lewat WhatsApp lalu email; dikunci per kontak supaya dua tab yang mulai chat bersamaan tidak membuat data kembar.
     */
    public function resolveWebsiteLead(string $name, string $phone, string $email): Customer
    {
        $phone = User::normalizePhone($phone);
        $email = strtolower(trim($email));

        return Cache::lock('website-lead:'.sha1($phone.'|'.$email), 10)->block(5, fn () => DB::transaction(function () use ($name, $phone, $email) {
            $leads = Customer::whereNull('branch_id')->forApp(Customer::APP_WEBSITE);
            $customer = (clone $leads)->where('phone', $phone)->first() ?? (clone $leads)->where('email', $email)->first() ?? new Customer();

            $customer->forceFill([
                'app_key' => Customer::APP_WEBSITE,
                'source' => $customer->source ?? 'live_chat',
                'first_seen_at' => $customer->first_seen_at ?? now(),
                'name' => trim($name),
                'phone' => $customer->phone ?? $phone,
                'email' => $customer->email ?? $email,
                'last_seen_at' => now(),
            ])->save();

            return $customer;
        }));
    }

    /**
     * Validasi form tambah/ubah customer satu aplikasi. Nomor dirapikan ke 62..., email huruf kecil.
     * Nomor/email boleh sama dengan customer aplikasi lain, tapi tidak dengan customer lain di aplikasi yang sama.
     */
    public static function validate(Request $request, string $branchId, string $app, ?Customer $self = null, array $extra = []): array
    {
        $request->merge([
            'phone' => User::normalizePhone($request->input('phone')) ?: null,
            'email' => $request->filled('email') ? strtolower(trim($request->input('email'))) : null,
        ]);
        $unique = fn () => Rule::unique('customers')->where('branch_id', $branchId)->where('app_key', $app)->ignore($self?->id);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'required_without:email', 'string', 'min:10', 'max:20', $unique()],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', $unique()],
            ...$extra,
        ], [
            'phone.unique' => 'Nomor WhatsApp ini sudah dipakai customer lain di aplikasi ini.',
            'email.unique' => 'Email ini sudah dipakai customer lain di aplikasi ini.',
            'phone.required_without' => 'Isi nomor WhatsApp atau email.',
            'email.required_without' => 'Isi nomor WhatsApp atau email.',
        ], ['phone' => 'nomor WhatsApp']);
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
