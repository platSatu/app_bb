<?php

namespace App\Services\User;

use App\Models\CatalogOrder;
use App\Models\Deposit;
use App\Models\Disbursement;
use App\Models\InvoiceCheckout;
use App\Models\TicketOrder;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus user beserta SEMUA datanya (reset total): perusahaan, branch, dan semua tabel turunannya.
 *
 * Tidak memakai daftar tabel manual (gampang ketinggalan saat ada tabel baru): relasi dibaca dari
 * foreign key database, lalu baris anak dihapus lebih dulu dari induknya, berantai sampai habis.
 * FK "set null" (mis. created_by / reviewed_by di data orang lain) tidak dihapus, cukup dikosongkan database.
 * Kolom user_id / branch_id / company_id tanpa FK (mis. sessions, outbox WhatsApp) ikut dibersihkan.
 */
class UserPurger
{
    private const CHUNK = 500;

    private const OWNER_COLUMNS = ['user_id' => 'users', 'company_id' => 'user_companies', 'branch_id' => 'user_branches'];

    private const ACTIVE_STATUSES = ['pending', 'processing', 'needs_review'];

    /** @var array<string, list<array{table: string, column: string}>> induk => anak yang ikut terhapus */
    private array $children = [];

    /** @var array<string, array<string, true>> tabel => kolom yang sudah punya FK (tidak disapu ulang) */
    private array $constrained = [];

    /** @var array<string, int> jumlah baris terhapus per tabel */
    private array $deleted = [];

    /** Alasan user belum boleh dihapus: masih ada uang / transaksi uang yang belum selesai. */
    public function blockers(User $user): array
    {
        $branches = DB::table('user_branches')->where('user_id', $user->getKey())->pluck('id');
        $rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.');
        $reasons = [];

        if (($balance = (int) DB::table('wallets')->where('user_id', $user->getKey())->value('balance')) > 0) {
            $reasons[] = 'masih ada saldo '.$rupiah($balance);
        }
        if (WalletWithdrawal::where('user_id', $user->getKey())->whereIn('status', self::ACTIVE_STATUSES)->exists()) {
            $reasons[] = 'ada tarik saldo yang belum selesai';
        }
        if (Deposit::where('user_id', $user->getKey())->where('status', Deposit::STATUS_PENDING)->exists()) {
            $reasons[] = 'ada top up saldo yang menunggu pembayaran';
        }

        if ($branches->isNotEmpty()) {
            if (Disbursement::withoutGlobalScopes()->whereIn('branch_id', $branches)->whereIn('status', self::ACTIVE_STATUSES)->exists()) {
                $reasons[] = 'ada disbursement yang belum selesai';
            }
            if (TicketOrder::query()->withoutGlobalScopes()->whereIn('branch_id', $branches)->where('status', TicketOrder::STATUS_PENDING)->exists()) {
                $reasons[] = 'ada pesanan tiket yang menunggu pembayaran';
            }
            if (InvoiceCheckout::query()->withoutGlobalScopes()->whereIn('branch_id', $branches)->where('status', InvoiceCheckout::STATUS_PENDING)->exists()) {
                $reasons[] = 'ada pembayaran invoice yang sedang berjalan';
            }
            if (CatalogOrder::query()->withoutGlobalScopes()->whereIn('page_id', DB::table('page_categories')->whereIn('branch_id', $branches)->select('id'))
                ->where('status', CatalogOrder::STATUS_NEW)->where('payment_type', 'online')->where('expires_at', '>', now())->exists()) {
                $reasons[] = 'ada pesanan katalog yang menunggu pembayaran online';
            }
        }

        return $reasons;
    }

    /** @return array<string, int> jumlah baris terhapus per tabel */
    public function purge(User $user): array
    {
        $this->mapForeignKeys();
        $this->deleted = [];

        DB::transaction(function () use ($user) {
            $id = $user->getKey();
            $owned = [
                'users' => collect([$id]),
                'user_companies' => DB::table('user_companies')->where('user_id', $id)->pluck('id'),
                'user_branches' => DB::table('user_branches')->where('user_id', $id)->pluck('id'),
            ];

            // Data tanpa FK dulu (selama induknya masih ada), lalu rantai FK dari branch, perusahaan, user.
            $this->sweepUnconstrained($owned);
            DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            foreach (['user_branches', 'user_companies', 'users'] as $table) {
                $this->delete($table, $owned[$table]);
            }
        });

        return array_filter($this->deleted);
    }

    private function delete(string $table, Collection $ids, int $depth = 0): void
    {
        if ($ids->isEmpty() || $depth > 20) {
            return;
        }

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            foreach ($this->children[$table] ?? [] as ['table' => $child, 'column' => $column]) {
                if (isset($this->children[$child])) {
                    // Anak yang punya anak lagi: ambil id-nya supaya cucunya ikut terhapus lebih dulu.
                    $this->delete($child, DB::table($child)->whereIn($column, $chunk)->pluck('id'), $depth + 1);
                } else {
                    $this->count($child, DB::table($child)->whereIn($column, $chunk)->delete());
                }
            }

            $this->count($table, DB::table($table)->whereIn('id', $chunk)->delete());
        }
    }

    private function sweepUnconstrained(array $owned): void
    {
        foreach ($this->tables() as $table) {
            foreach (self::OWNER_COLUMNS as $column => $parent) {
                if (isset($this->constrained[$table][$column]) || $owned[$parent]->isEmpty() || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($owned[$parent]->chunk(self::CHUNK) as $chunk) {
                    $this->count($table, DB::table($table)->whereIn($column, $chunk)->delete());
                }
            }
        }
    }

    private function mapForeignKeys(): void
    {
        $this->children = $this->constrained = [];

        foreach ($this->tables() as $table) {
            foreach (Schema::getForeignKeys($table) as $fk) {
                $column = $fk['columns'][0];
                $this->constrained[$table][$column] = true;

                // Hanya FK ke kolom id induk (semua FK di aplikasi ini begitu); "set null" dibiarkan database.
                if (($fk['foreign_columns'][0] ?? null) === 'id' && strtolower((string) $fk['on_delete']) !== 'set null') {
                    $this->children[$fk['foreign_table']][] = ['table' => $table, 'column' => $column];
                }
            }
        }
    }

    private function tables(): array
    {
        return once(fn () => collect(Schema::getTables())->pluck('name')
            ->reject(fn ($name) => in_array($name, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true))
            ->values()->all());
    }

    private function count(string $table, int $rows): void
    {
        $this->deleted[$table] = ($this->deleted[$table] ?? 0) + $rows;
    }
}
