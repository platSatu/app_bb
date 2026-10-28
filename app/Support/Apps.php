<?php

namespace App\Support;

use App\Models\PageType;

/**
 * Layanan yang bisa dibuka/ditutup lewat paket. Kunci ini dipakai di packages.apps,
 * branch_subscriptions.app_key, dan 'app' pada grup menu di config/menu.php.
 */
class Apps
{
    /** Dipakai menu bersama (mis. Kontak): terbuka kalau layanan apa pun aktif. */
    public const ANY = 'any';

    public const EVENTS = 'events';
    public const PAGES = 'pages';

    public const LIST = [
        self::EVENTS => ['label' => 'Event', 'hint' => 'Tiket event, e-ticket QR, registrasi ulang & ID card', 'icon' => 'ri-coupon-3-line'],
        self::PAGES => ['label' => 'Pages', 'hint' => 'Landing page, katalog, kuis & bio link', 'icon' => 'ri-pages-line'],
        'schedules' => ['label' => 'Jadwal', 'hint' => 'Jadwal, absensi & portal murid/pengajar', 'icon' => 'ri-calendar-2-line'],
        'invoices' => ['label' => 'Tagihan', 'hint' => 'Invoice & link bayar', 'icon' => 'ri-file-list-3-line'],
        'whatsapp' => ['label' => 'WhatsApp', 'hint' => 'Device, inbox, broadcast & chatbot', 'icon' => 'ri-whatsapp-line'],
    ];

    /**
     * Event memakai mesin page yang sama (tabel page_categories), dibedakan dari template kategorinya.
     * Page bertemplate ini masuk layanan Event; template lain tetap layanan Pages.
     */
    public const EVENT_TEMPLATES = [PageType::TEMPLATE_TICKET];

    public static function keys(): array
    {
        return array_keys(self::LIST);
    }

    public static function label(string $key): string
    {
        return $key === self::ANY ? 'aplikasi' : (self::LIST[$key]['label'] ?? $key);
    }

    /** Layanan pemilik sebuah page menurut template kategorinya: 'events' atau 'pages'. */
    public static function forTemplate(?string $template): string
    {
        return in_array($template, self::EVENT_TEMPLATES, true) ? self::EVENTS : self::PAGES;
    }

    /** Area page dari nama route: events.* / superadmin.events.* = Event, selain itu Pages. */
    public static function pageArea(?string $routeName): string
    {
        return str_starts_with((string) $routeName, 'events.') || str_starts_with((string) $routeName, 'superadmin.events.')
            ? self::EVENTS : self::PAGES;
    }

    /** Prefix nama route area page: events, pages, superadmin.events, superadmin.pages. */
    public static function pageRoutes(?string $routeName): string
    {
        $area = self::pageArea($routeName);

        return str_starts_with((string) $routeName, 'superadmin.') ? 'superadmin.'.$area : $area;
    }

    /** Label breadcrumb halaman page/event dari prefix route-nya. */
    public static function pageSection(string $routes): string
    {
        return match ($routes) {
            self::EVENTS => 'Event',
            self::PAGES => 'Pages',
            default => 'Superadmin',
        };
    }
}
