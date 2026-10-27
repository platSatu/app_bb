<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TicketSetting;
use Illuminate\Http\JsonResponse;

/**
 * Biaya layanan tiket untuk halaman bizbos.id/tiket (fe-konexa), supaya angka di website selalu sama
 * dengan yang diatur superadmin. Hanya angka publik; tidak ada data pengguna.
 */
class TicketFeeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $setting = TicketSetting::current();

        return response()->json(['data' => [
            'fee_percent' => (float) $setting->fee_percent,
            'free_allowance' => (int) $setting->free_allowance,
        ]]);
    }
}
