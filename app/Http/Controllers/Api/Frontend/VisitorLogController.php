<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\FrontendVisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menerima laporan kunjungan dari fe-konexa (server-to-server, lewat
 * App\Http\Middleware\LogVisitorMiddleware di app itu) untuk setiap
 * halaman publik yang dibuka orang — beranda/artikel/syarat-dan-
 * ketentuan/video/kontak. Gated oleh VerifyFrontendApiKey yang sama
 * dengan endpoint /api/frontend/* lain (lihat routes/api.php's
 * `frontend.api-key` group) — bukan endpoint yang bisa dipanggil bebas
 * dari browser pengunjung, cuma dari server fe-konexa yang tahu
 * kuncinya.
 *
 * browser/browser_version/os/device_type sengaja DIPARSE DI SINI dari
 * user_agent mentah yang dikirim fe-konexa (parser ringan di bawah),
 * bukan dipercaya kalau fe-konexa yang kirim sudah-jadi — supaya
 * logika parsing-nya cuma ada di satu tempat dan konsisten kalau nanti
 * ada sumber lain yang ikut lapor.
 */
class VisitorLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:512'],
            'path' => ['required', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:255'],
            'visited_at' => ['required', 'date'],
        ]);

        $ua = (string) ($validated['user_agent'] ?? '');
        [$browser, $version] = $this->browser($ua);

        $log = FrontendVisitorLog::create([
            'visitor_id' => $validated['visitor_id'],
            'ip_address' => $validated['ip_address'],
            'user_agent' => $validated['user_agent'] ?? null,
            'browser' => $browser,
            'browser_version' => $version,
            'os' => $this->os($ua),
            'device_type' => $this->deviceType($ua),
            'path' => $validated['path'],
            'referrer' => $validated['referrer'] ?? null,
            'visited_at' => $validated['visited_at'],
        ]);

        return response()->json(['data' => ['id' => $log->id]], 201);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function browser(string $ua): array
    {
        foreach (['Edge' => 'Edg(?:e|A|iOS)?', 'Opera' => 'OPR|Opera', 'Samsung Browser' => 'SamsungBrowser', 'Chrome' => 'Chrome|CriOS', 'Firefox' => 'Firefox|FxiOS', 'Safari' => 'Version(?=.*Safari)'] as $name => $pattern) {
            if (preg_match('#(?:'.$pattern.')/([\d.]+)#', $ua, $m)) {
                return [$name, substr($m[1], 0, 30)];
            }
        }

        return [null, null];
    }

    private function os(string $ua): ?string
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

    private function deviceType(string $ua): string
    {
        return match (true) {
            (bool) preg_match('#bot|crawl|spider|slurp|facebookexternalhit|preview#i', $ua) => 'bot',
            (bool) preg_match('#iPad|Tablet|(Android(?!.*Mobile))#i', $ua) => 'tablet',
            (bool) preg_match('#Mobi|iPhone|iPod|Android#i', $ua) => 'mobile',
            default => 'desktop',
        };
    }
}
