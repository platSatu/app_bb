<?php

namespace App\Http\Controllers\Superadmin\Withdrawal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Withdrawal\DisbursementSettingRequest;
use App\Models\DisbursementSetting;
use App\Services\Payment\DuitkuDisbursementClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class DisbursementController extends Controller
{
    public function setting(): View
    {
        return view('superadmin.withdrawal.setting', ['setting' => DisbursementSetting::current()]);
    }

    public function update(DisbursementSettingRequest $request): RedirectResponse
    {
        $setting = DisbursementSetting::current();
        $setting->fill($request->safe()->except(['sandbox_secret_key', 'production_secret_key']));
        $setting->is_active = $request->boolean('is_active');

        // Secret key hanya diganti kalau diisi; kosong = tetap pakai yang lama.
        foreach (['sandbox_secret_key', 'production_secret_key'] as $field) {
            if ($request->filled($field)) {
                $setting->{$field} = $request->validated($field);
            }
        }

        $setting->save();
        Cache::forget('duitku-disbursement-banks:s');
        Cache::forget('duitku-disbursement-banks:p');

        return back()->with('success', 'Pengaturan pencairan dana berhasil disimpan.');
    }

    public function balance(): RedirectResponse
    {
        try {
            $result = DuitkuDisbursementClient::make()->checkBalance();
        } catch (Throwable $e) {
            return back()->with('error', 'Saldo Duitku belum bisa dicek: '.$e->getMessage());
        }

        if (($result['responseCode'] ?? null) !== '00') {
            return back()->with('error', 'Duitku menjawab: '.($result['responseDesc'] ?? '-').' (kode '.($result['responseCode'] ?? '?').')');
        }

        return back()->with('success', 'Saldo disbursement Duitku: Rp '.number_format((float) ($result['balance'] ?? 0), 0, ',', '.')
            .' (efektif Rp '.number_format((float) ($result['effectiveBalance'] ?? 0), 0, ',', '.').').');
    }
}
