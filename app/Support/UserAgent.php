<?php

namespace App\Support;

/**
 * Pembaca user agent yang ringan (tanpa library): browser, versi, OS, jenis perangkat.
 * Dipakai log kunjungan website (VisitorLogController) dan log aktivitas pengguna (LogUserActivity).
 */
class UserAgent
{
    /** @return array{browser: ?string, browser_version: ?string, os: ?string, device_type: string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;
        [$browser, $version] = static::browser($ua);

        return ['browser' => $browser, 'browser_version' => $version, 'os' => static::os($ua), 'device_type' => static::deviceType($ua)];
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function browser(string $ua): array
    {
        foreach (['Edge' => 'Edg(?:e|A|iOS)?', 'Opera' => 'OPR|Opera', 'Samsung Browser' => 'SamsungBrowser', 'Chrome' => 'Chrome|CriOS', 'Firefox' => 'Firefox|FxiOS', 'Safari' => 'Version(?=.*Safari)'] as $name => $pattern) {
            if (preg_match('#(?:'.$pattern.')/([\d.]+)#', $ua, $m)) {
                return [$name, substr($m[1], 0, 30)];
            }
        }

        return [null, null];
    }

    private static function os(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('#Windows#i', $ua) => 'Windows',
            (bool) preg_match('#Android#i', $ua) => 'AndroidOS',
            (bool) preg_match('#iPhone|iPad|iPod#i', $ua) => 'iOS',
            (bool) preg_match('#Mac OS X|Macintosh#i', $ua) => 'OS X',
            (bool) preg_match('#Linux#i', $ua) => 'Linux',
            default => null,
        };
    }

    private static function deviceType(string $ua): string
    {
        return match (true) {
            (bool) preg_match('#bot|crawl|spider|slurp|facebookexternalhit|preview#i', $ua) => 'bot',
            (bool) preg_match('#iPad|Tablet|(Android(?!.*Mobile))#i', $ua) => 'tablet',
            (bool) preg_match('#Mobi|iPhone|iPod|Android#i', $ua) => 'mobile',
            default => 'desktop',
        };
    }
}
