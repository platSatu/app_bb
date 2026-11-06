<?php

namespace App\Http\Controllers\Superadmin\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\PaymentGateway\SettingRequest;
use App\Models\PaymentFeeRule;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function setting(): View
    {
        return view('superadmin.payment-gateway.setting', [
            'setting' => PaymentGatewaySetting::duitku(),
            // Biaya pembayaran per pelanggan (menimpa default QRIS), berlaku di semua aplikasi pembayaran.
            'feeRules' => PaymentFeeRule::with('user:id,name,email', 'user.company:id,user_id,name')
                ->orderBy('user_id')->orderByRaw('payment_method IS NULL DESC')->orderBy('payment_method')->get(),
            'customers' => User::has('company')->with('company:id,user_id,name')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    /** Tambah / ubah biaya satu pelanggan. Satu baris per pelanggan + metode (metode kosong = semua metode). */
    public function storeFeeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid', Rule::exists('user_companies', 'user_id')],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(PaymentGatewaySetting::duitku()->settlementMethods()))],
            'fee_type' => ['required', Rule::in(array_keys(PaymentFeeRule::TYPE_LABELS))],
            'fee_value' => ['required', 'numeric', 'min:0', 'decimal:0,2', $request->input('fee_type') === PaymentFeeRule::TYPE_PERCENT ? 'max:50' : 'max:1000000'],
        ], [], ['user_id' => 'pelanggan', 'payment_method' => 'metode', 'fee_type' => 'jenis biaya', 'fee_value' => 'nilai biaya']);

        $method = $data['payment_method'] ?? null;

        // Dikunci per pelanggan: dua admin menyimpan bersamaan tidak membuat baris ganda (NULL tidak dijaga unique index).
        DB::transaction(function () use ($data, $method, $request) {
            User::whereKey($data['user_id'])->lockForUpdate()->first();

            $rule = PaymentFeeRule::where('user_id', $data['user_id'])
                ->when($method, fn ($q) => $q->where('payment_method', $method), fn ($q) => $q->whereNull('payment_method'))
                ->first() ?? new PaymentFeeRule();

            $rule->forceFill([
                'user_id' => $data['user_id'],
                'payment_method' => $method,
                'fee_type' => $data['fee_type'],
                'fee_value' => $data['fee_value'],
                'updated_by' => $request->user()->getKey(),
            ])->save();
        });

        return redirect()->route('superadmin.payment-gateway.setting')->withFragment('biaya-pelanggan')
            ->with('success', 'Biaya pelanggan disimpan. Berlaku untuk pembayaran berikutnya.');
    }

    public function destroyFeeRule(PaymentFeeRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('superadmin.payment-gateway.setting')->withFragment('biaya-pelanggan')
            ->with('success', 'Biaya pelanggan dihapus. Pelanggan ini kembali memakai biaya default.');
    }

    public function updateSetting(SettingRequest $request): RedirectResponse
    {
        $setting = PaymentGatewaySetting::duitku();
        $setting->fill($request->safe()->except(['sandbox_api_key', 'production_api_key', 'settlement_methods', 'settlement_holidays', 'qris_fee_percent', 'qris_fee_methods']));
        $setting->settlement_methods = $this->parseMethods((string) $request->validated('settlement_methods'));
        $setting->settlement_holidays = $this->parseHolidays((string) $request->validated('settlement_holidays'));
        // Potongan QRIS: kosong = tetap pakai nilai lama.
        if ($request->filled('qris_fee_percent')) {
            $setting->qris_fee_percent = (float) $request->validated('qris_fee_percent');
        }
        if ($request->has('qris_fee_methods')) {
            $setting->qris_fee_methods = $this->parseQrisMethods((string) $request->validated('qris_fee_methods'));
        }
        $setting->is_active = $request->boolean('is_active');

        // API key hanya diganti kalau diisi; kosong = tetap pakai yang lama.
        foreach (['sandbox_api_key', 'production_api_key'] as $field) {
            if ($request->filled($field)) {
                $setting->{$field} = $request->validated($field);
            }
        }

        $setting->save();

        return back()->with('success', 'Pengaturan payment gateway berhasil disimpan.');
    }

    /** Satu baris per metode: "KODE | Nama | hari kerja" (hari kosong = ikut default). */
    private function parseMethods(string $text): array
    {
        $methods = [];

        foreach (preg_split('/\R/', trim($text)) ?: [] as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            [$code, $name, $days] = array_pad(array_map('trim', explode('|', $line)), 3, '');

            if (! preg_match('/^[A-Za-z0-9]{1,10}$/', $code) || ($days !== '' && (! ctype_digit($days) || (int) $days > 14))) {
                throw ValidationException::withMessages(['settlement_methods' => 'Baris '.($i + 1).' belum benar. Contoh: SP | ShopeePay QRIS | 2']);
            }

            $methods[strtoupper($code)] = ['name' => $name !== '' ? mb_substr($name, 0, 60) : strtoupper($code), 'days' => $days === '' ? null : (int) $days];
        }

        return $methods;
    }

    /** "SP, NQ, SQ" -> ['SP', 'NQ', 'SQ']. Kosong = tidak ada metode yang dipotong. */
    private function parseQrisMethods(string $text): array
    {
        $codes = [];

        foreach (preg_split('/[\s,;]+/', trim($text)) ?: [] as $code) {
            if ($code === '') {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9]{1,10}$/', $code)) {
                throw ValidationException::withMessages(['qris_fee_methods' => "Kode {$code} belum benar. Contoh: SP, NQ, SQ"]);
            }

            $codes[] = strtoupper($code);
        }

        return array_values(array_unique($codes));
    }

    /** Satu tanggal per baris, format YYYY-MM-DD. */
    private function parseHolidays(string $text): array
    {
        $dates = [];

        foreach (preg_split('/[\s,]+/', trim($text)) ?: [] as $value) {
            if ($value === '') {
                continue;
            }

            [$y, $m, $d] = array_map('intval', array_pad(explode('-', $value), 3, 0));

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! checkdate($m, $d, $y)) {
                throw ValidationException::withMessages(['settlement_holidays' => "Tanggal {$value} belum benar. Pakai format 2026-12-25."]);
            }

            $dates[] = $value;
        }

        return array_values(array_unique($dates));
    }
}
