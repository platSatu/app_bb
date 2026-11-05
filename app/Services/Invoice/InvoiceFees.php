<?php

namespace App\Services\Invoice;

use App\Models\InvoiceFeeRule;
use Illuminate\Support\Collection;

/**
 * Biaya transaksi Invoice per customer (branch) & per metode bayar — 5 Nov 2026.
 *
 * Tanpa aturan untuk branch itu: semua method mengembalikan null = pakai perilaku lama
 * (Biaya Invoice global + potongan QRIS di WalletService). Dengan aturan: biaya dari aturan
 * menjadi SATU-SATUNYA potongan (all-in), potongan QRIS tidak ditambahkan lagi.
 */
class InvoiceFees
{
    /** @var array<string, Collection<int, InvoiceFeeRule>> */
    private array $cache = [];

    /** Aturan "ditanggung pembayar" (selalu untuk semua metode), atau null. */
    public function payerRule(?string $branchId): ?InvoiceFeeRule
    {
        return $this->rules($branchId)->first(fn (InvoiceFeeRule $r) => $r->bearer === InvoiceFeeRule::BEARER_PAYER);
    }

    /** Biaya yang ditambahkan ke total pembayar; null = tidak ada aturan pembayar. */
    public function payerFee(?string $branchId, int $total): ?int
    {
        $rule = $this->payerRule($branchId);

        return $rule ? self::calculate($rule->fee_type, $rule->fee_value, $total) : null;
    }

    /**
     * Biaya yang dipotong dari saldo customer untuk metode ini; null = tidak ada aturan (pakai cara lama).
     * Urutan: aturan metode ini -> aturan "semua metode".
     */
    public function merchantFee(?string $branchId, ?string $method, int $paidAmount): ?int
    {
        $rules = $this->rules($branchId)->where('bearer', InvoiceFeeRule::BEARER_MERCHANT);

        if ($rules->isEmpty()) {
            return null;
        }

        $method = $method ? strtoupper($method) : null;
        $rule = ($method ? $rules->firstWhere('payment_method', $method) : null) ?? $rules->firstWhere('payment_method', null);

        // Ada aturan customer tapi tidak ada yang cocok (metode lain & tidak ada "semua metode"): biaya 0, bukan cara lama,
        // supaya customer yang sudah diatur tidak tiba-tiba kena potongan global.
        return $rule ? self::calculate($rule->fee_type, $rule->fee_value, $paidAmount) : 0;
    }

    public function hasRules(?string $branchId): bool
    {
        return $this->rules($branchId)->isNotEmpty();
    }

    /** Flat = rupiah bulat; persen = dari nominal, dibulatkan ke atas. Tidak pernah negatif atau melebihi nominal. */
    public static function calculate(string $type, float $value, int $base): int
    {
        $fee = $type === InvoiceFeeRule::TYPE_PERCENT
            ? (int) ceil(round($base * $value / 100, 4))
            : (int) round($value);

        return max(0, min($fee, max(0, $base)));
    }

    /** @return Collection<int, InvoiceFeeRule> */
    private function rules(?string $branchId): Collection
    {
        if (! $branchId) {
            return collect();
        }

        return $this->cache[$branchId] ??= InvoiceFeeRule::where('branch_id', $branchId)->get();
    }
}
