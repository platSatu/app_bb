<?php

namespace App\Services\Ticket;

use App\Models\PageCategory;
use App\Models\TicketCategory;
use App\Models\TicketFreeFee;
use App\Models\TicketSetting;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WalletTransaction;
use App\Services\Wallet\InsufficientBalanceException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Biaya layanan tiket GRATIS: dibayar di depan dari Saldo penyelenggara, per tiket, saat kategori disimpan.
 * Tidak lewat payment gateway (Saldo sendiri diisi lewat Duitku).
 *
 * Alurnya, dibaca dari atas ke bawah:
 *   1. Hitung   – tiket gratis yang perlu dibayar = total kuota tiket gratis di SATU EVENT dikurangi jatah gratis.
 *                 Dihitung per event (bukan per kategori), jadi memecah jadi banyak kategori kecil tidak bisa
 *                 melewati jatah dari superadmin.
 *   2. Bayar    – kuota tiket gratis bertambah: selisihnya dipotong dari Saldo. Saldo kurang = kategori batal disimpan.
 *   3. Kembali  – kuota berkurang, kategori dihapus, atau diubah jadi berbayar: selisihnya kembali ke Saldo.
 *   4. Tutup    – penyelenggara menutup pendaftaran: tiket gratis yang tidak terpakai dikembalikan ke Saldo,
 *                 kategori gratis dinonaktifkan, dan tidak bisa dibuka lagi.
 *
 * Tarif & jatah gratis dikunci per event saat pertama kali dihitung. Event yang sudah ada sebelum fitur ini
 * bebas biaya (exempt). Langkah 2–4 selalu berjalan di dalam transaksi yang mengunci baris page, kategori,
 * dan catatan biaya ini, jadi dua simpanan bersamaan antre dan saldo tidak terpotong dua kali.
 */
class FreeTicketFeeService
{
    public function __construct(private readonly WalletService $wallets)
    {
    }

    /** Langkah 1 (untuk tampilan): angka yang sedang berlaku di event ini. */
    public function summary(PageCategory $page): array
    {
        $fee = TicketFreeFee::where('page_id', $page->getKey())->first();
        $setting = TicketSetting::current();
        $free = TicketCategory::where('page_id', $page->getKey())->where('price', 0)->get(['quota', 'sold', 'reserved']);

        return [
            'exempt' => (bool) $fee?->exempt,
            'closed' => (bool) $fee?->closed_at,
            'unit_price' => $fee->unit_price ?? $setting->free_unit_price,
            'allowance' => $fee->free_allowance ?? $setting->free_allowance,
            'paid_units' => $fee->paid_units ?? 0,
            'quota' => (int) $free->sum('quota'),
            'used' => (int) $free->sum(fn ($c) => $c->sold + $c->reserved),
        ];
    }

    /** Langkah 1: tiket gratis di atas jatah, yang harus dibayar. */
    public static function billableUnits(int $freeTickets, int $allowance): int
    {
        return max(0, $freeTickets - $allowance);
    }

    /**
     * Langkah 2 & 3. Dipanggil di dalam transaksi simpan kategori, SETELAH kategori disimpan.
     * $confirmedAmount = nominal yang sudah disetujui penyelenggara di layar; kalau berbeda dengan hasil hitung
     * server (mis. ada perubahan bersamaan), simpanan dibatalkan supaya tidak ada potongan yang mengejutkan.
     *
     * @return string|null pesan untuk penyelenggara kalau ada saldo yang dipotong/dikembalikan
     */
    public function settle(PageCategory $page, ?User $actor, ?int $confirmedAmount): ?string
    {
        $this->assertInTransaction();
        $fee = $this->lockedFee($page);

        if ($fee->exempt) {
            return null;
        }

        $free = TicketCategory::where('page_id', $page->getKey())->where('price', 0)->get(['quota', 'status']);

        if ($fee->closed_at) {
            if ($free->contains('status', TicketCategory::STATUS_ACTIVE)) {
                throw ValidationException::withMessages(['categories' => 'Pendaftaran tiket gratis di event ini sudah ditutup, jadi kategori gratis tidak bisa diaktifkan lagi.']);
            }

            return null;
        }

        $needed = self::billableUnits((int) $free->sum('quota'), $fee->free_allowance);
        $change = $needed - $fee->paid_units;

        if ($change === 0) {
            return null;
        }

        $amount = abs($change) * $fee->unit_price;
        $rp = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.');

        if ($change > 0 && $amount > 0 && $confirmedAmount !== $amount) {
            throw ValidationException::withMessages(['free_fee' => "Ada {$change} tiket gratis tambahan yang perlu dibayar sebesar {$rp($amount)}. Silakan cek lagi lalu tekan Simpan untuk menyetujui."]);
        }

        $owner = UserBranch::with('user')->findOrFail($page->branch_id)->user;
        $fee->version++;
        $key = "ticket-free-fee:{$fee->getKey()}:{$fee->version}";

        if ($amount > 0 && $change > 0) {
            try {
                $this->wallets->debit($owner, $amount, WalletTransaction::TYPE_TICKET_FREE_FEE, $key, $fee,
                    "Biaya {$change} tiket gratis × {$rp($fee->unit_price)} · {$page->name}", $actor);
            } catch (InsufficientBalanceException) {
                throw ValidationException::withMessages(['free_fee' => "Saldo Anda belum cukup untuk {$change} tiket gratis tambahan ({$rp($amount)}). Isi Saldo dulu, lalu simpan lagi ya. Perubahan kategori belum disimpan."]);
            }
        } elseif ($amount > 0) {
            $this->wallets->credit($owner, $amount, WalletTransaction::TYPE_TICKET_FREE_REFUND, $key, $fee,
                'Pengembalian '.abs($change)." tiket gratis × {$rp($fee->unit_price)} · {$page->name}", $actor);
        }

        $fee->paid_units = $needed;
        $fee->save();

        return match (true) {
            $amount === 0 => null,
            $change > 0 => "Biaya {$change} tiket gratis sebesar {$rp($amount)} sudah dibayar dari Saldo.",
            default => abs($change)." tiket gratis tidak jadi dipakai, {$rp($amount)} sudah dikembalikan ke Saldo.",
        };
    }

    /** Langkah 4. Tutup pendaftaran tiket gratis dan kembalikan yang tidak terpakai. Tidak bisa dibuka lagi. */
    public function close(PageCategory $page, ?User $actor): string
    {
        return DB::transaction(function () use ($page, $actor) {
            PageCategory::whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $free = TicketCategory::where('page_id', $page->getKey())->where('price', 0)->lockForUpdate()->get();
            $fee = $this->lockedFee($page);

            if ($fee->exempt || $fee->closed_at || $free->isEmpty()) {
                throw new RuntimeException('Tidak ada pendaftaran tiket gratis yang perlu ditutup di event ini.');
            }

            $used = self::billableUnits((int) $free->sum(fn ($c) => $c->sold + $c->reserved), $fee->free_allowance);
            $unused = max(0, $fee->paid_units - $used);
            $amount = $unused * $fee->unit_price;

            TicketCategory::whereKey($free->modelKeys())->update(['status' => TicketCategory::STATUS_INACTIVE, 'updated_at' => now()]);

            if ($amount > 0) {
                $fee->version++;
                $owner = UserBranch::with('user')->findOrFail($page->branch_id)->user;
                $this->wallets->credit($owner, $amount, WalletTransaction::TYPE_TICKET_FREE_REFUND, "ticket-free-fee:{$fee->getKey()}:{$fee->version}", $fee,
                    "Pengembalian {$unused} tiket gratis tidak terpakai · {$page->name}", $actor);
            }

            $fee->paid_units = $used;
            $fee->closed_at = now();
            $fee->save();

            return $amount > 0
                ? "Pendaftaran tiket gratis sudah ditutup. {$unused} tiket tidak terpakai, Rp ".number_format($amount, 0, ',', '.').' sudah dikembalikan ke Saldo Anda.'
                : 'Pendaftaran tiket gratis sudah ditutup. Tidak ada biaya yang perlu dikembalikan.';
        });
    }

    /** Catatan biaya event ini, dikunci. Event baru mendapat tarif & jatah yang berlaku saat ini (lalu dikunci). */
    private function lockedFee(PageCategory $page): TicketFreeFee
    {
        if (! TicketFreeFee::where('page_id', $page->getKey())->exists()) {
            $setting = TicketSetting::current();
            $fee = new TicketFreeFee();
            $fee->forceFill(['page_id' => $page->getKey(), 'unit_price' => $setting->free_unit_price, 'free_allowance' => $setting->free_allowance])->save();
        }

        return TicketFreeFee::where('page_id', $page->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('FreeTicketFeeService::settle harus dipanggil di dalam DB::transaction.');
        }
    }
}
