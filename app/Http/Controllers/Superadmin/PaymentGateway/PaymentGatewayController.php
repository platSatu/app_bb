<?php

namespace App\Http\Controllers\Superadmin\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\PaymentGateway\SettingRequest;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\RedirectResponse;
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
        $setting->fill($request->safe()->except(['sandbox_api_key', 'production_api_key']));
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
}
