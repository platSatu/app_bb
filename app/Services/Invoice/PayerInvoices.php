<?php

namespace App\Services\Invoice;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ScheduleStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tagihan milik pembayar yang login: invoice (dari dashboard owner, bukan API website) yang ditujukan ke email akun ini,
 * langsung di invoice atau lewat customer Invoice-nya. Isi tagihan hanya dibuka setelah email akun terverifikasi,
 * supaya orang tidak bisa mendaftar memakai email orang lain lalu melihat tagihannya.
 *
 * Tagihan les dari Schedule (Murid › Buat Tagihan) juga ikut, lewat murid yang terhubung ke akun ini (portal,
 * schedule_students.user_id) -- tidak bergantung pada email. Sebelumnya tagihan ini hanya terlihat kalau email
 * customer Invoice kebetulan sama dengan email akun; murid yang hanya punya nomor WhatsApp, atau yang emailnya
 * berbeda dengan email akun portal, tidak melihat menu Tagihan Saya.
 */
class PayerInvoices
{
    public static function query(User $user): Builder
    {
        $email = mb_strtolower(trim((string) $user->email));
        $studentIds = self::studentIds($user);

        return Invoice::query()
            ->where('source', '!=', Invoice::SOURCE_API)
            ->where(fn (Builder $q) => $q->where('customer_email', $email)
                ->orWhereIn('customer_id', Customer::forApp('invoices')->where('email', $email)->select('id'))
                // external_id tagihan les: "jadwal:{id murid}:..." (lihat ScheduleBillingService::key()).
                ->when($studentIds !== [], fn (Builder $q) => $q->orWhere(fn (Builder $s) => $s
                    ->where('source', Invoice::SOURCE_SCHEDULE)
                    ->where(fn (Builder $ids) => collect($studentIds)->each(fn (string $id) => $ids->orWhere('external_id', 'like', 'jadwal:'.$id.':%'))))));
    }

    /** Murid (portal) yang terhubung ke akun ini. Tidak di-cache dengan once(): kuncinya per baris kode, bukan per akun. */
    private static function studentIds(User $user): array
    {
        return ScheduleStudent::where('user_id', $user->getKey())->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Untuk menu: punya tagihan (belum bayar / sudah lunas), atau sudah terdaftar sebagai customer Invoice (link pendaftaran). */
    public static function has(User $user): bool
    {
        return (filled($user->email) && self::customerBranches($user)->exists())
            || self::query($user)->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID])->exists();
    }

    /** branch_id tempat email akun ini terdaftar sebagai customer Invoice (subquery, pakai indeks app_key+email). */
    public static function customerBranches(User $user): Builder
    {
        return Customer::forApp('invoices')->where('email', mb_strtolower(trim((string) $user->email)))->select('branch_id');
    }

    public static function verified(User $user): bool
    {
        return $user->email_verified_at !== null;
    }

    /** Belum dibayar & masih bisa dibayar, urut seperti cara membayarnya (kategori, jatuh tempo terlama dulu). */
    public static function payable(User $user, string $branchId): Collection
    {
        return self::sortForPayment(self::query($user)
            ->where('branch_id', $branchId)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('category')
            ->get()
            // Lewat jatuh tempo tanpa denda sudah tidak bisa dibayar (scheduler menutupnya).
            ->reject(fn (Invoice $i) => $i->isPastDueClosed()));
    }

    public static function sortForPayment(Collection $invoices): Collection
    {
        return $invoices->sortBy(fn (Invoice $i) => [
            $i->category_id ?? 'zzzz',
            $i->due_date?->format('Y-m-d') ?? '9999-12-31',
            $i->created_at?->format('Y-m-d H:i:s') ?? '',
            $i->number,
        ])->values();
    }

    /**
     * Aturan urutan: di satu kategori (mis. SPP), tagihan harus dibayar dari yang terlama.
     * Tidak boleh mencentang Maret kalau Januari/Februari di kategori yang sama belum ikut dicentang.
     * Tagihan tanpa kategori bebas dipilih.
     *
     * @param  Collection<int, Invoice>  $payable  hasil payable()
     * @return array<int, string> pesan untuk pembayar (kosong = boleh)
     */
    public static function sequenceErrors(Collection $payable, array $selectedIds): array
    {
        $selected = array_flip($selectedIds);
        $errors = [];

        foreach ($payable->whereNotNull('category_id')->groupBy('category_id') as $group) {
            $skipped = null;

            foreach ($group as $invoice) {
                if (! isset($selected[$invoice->getKey()])) {
                    $skipped ??= $invoice;

                    continue;
                }

                if ($skipped) {
                    $errors[] = "Tagihan {$invoice->description} baru bisa dibayar setelah {$skipped->description}. Centang juga tagihan sebelumnya ya.";

                    break;
                }
            }
        }

        return $errors;
    }
}
