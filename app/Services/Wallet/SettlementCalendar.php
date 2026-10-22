<?php

namespace App\Services\Wallet;

use App\Models\PaymentGatewaySetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Kapan uang dari Duitku boleh ditarik: tanggal bayar + masa settlement metode bayar (hari kerja,
 * Sabtu/Minggu & tanggal libur dilewati), mulai jam 12.00 hari itu. Metode tidak diketahui = masa terlama.
 */
class SettlementCalendar
{
    private const OPEN_HOUR = 12;

    public static function availableAt(?string $method, ?CarbonInterface $paidAt = null): ?Carbon
    {
        $setting = PaymentGatewaySetting::duitku();
        $days = $setting->settlementDays($method);

        if ($days <= 0) {
            return null;
        }

        $holidays = array_flip($setting->settlement_holidays ?? []);
        $date = Carbon::instance($paidAt ?? now())->startOfDay();

        while ($days > 0) {
            $date->addDay();

            if (! $date->isWeekend() && ! isset($holidays[$date->toDateString()])) {
                $days--;
            }
        }

        return $date->setTime(self::OPEN_HOUR, 0);
    }
}
