<?php

namespace App\Http\Controllers\Superadmin\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\PaymentGateway\SettingRequest;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function setting(): View
    {
        return view('superadmin.payment-gateway.setting', ['setting' => PaymentGatewaySetting::duitku()]);
    }

    public function updateSetting(SettingRequest $request): RedirectResponse
    {
        $setting = PaymentGatewaySetting::duitku();
        $setting->fill($request->safe()->except(['sandbox_api_key', 'production_api_key', 'settlement_methods', 'settlement_holidays']));
        $setting->settlement_methods = $this->parseMethods((string) $request->validated('settlement_methods'));
        $setting->settlement_holidays = $this->parseHolidays((string) $request->validated('settlement_holidays'));
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
