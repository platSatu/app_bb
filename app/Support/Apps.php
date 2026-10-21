<?php

namespace App\Support;

/**
 * Layanan yang bisa dibuka/ditutup lewat paket. Kunci ini dipakai di packages.apps,
 * branch_subscriptions.app_key, dan 'app' pada grup menu di config/menu.php.
 */
class Apps
{
    public const LIST = [
        'pages' => ['label' => 'Pages', 'hint' => 'Tiket, katalog, kuis, landing & biolink', 'icon' => 'ri-pages-line'],
        'schedules' => ['label' => 'Jadwal', 'hint' => 'Jadwal, absensi & portal murid/pengajar', 'icon' => 'ri-calendar-2-line'],
        'invoices' => ['label' => 'Tagihan', 'hint' => 'Invoice & link bayar', 'icon' => 'ri-file-list-3-line'],
        'whatsapp' => ['label' => 'WhatsApp', 'hint' => 'Device, inbox, broadcast & chatbot', 'icon' => 'ri-whatsapp-line'],
    ];

    public static function keys(): array
    {
        return array_keys(self::LIST);
    }

    public static function label(string $key): string
    {
        return self::LIST[$key]['label'] ?? $key;
    }
}
