<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use App\Models\PageCategory;
use App\Models\TicketScanner;
use App\Services\Subscription\AppAccess;
use App\Support\Apps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman publik (page, event, scan tiket, link bayar invoice) ikut tutup kalau layanan branch pemiliknya habis.
 * Data yang tidak ditemukan diteruskan saja; controller yang menjawab 404.
 */
class EnsurePublicAppActive
{
    public function handle(Request $request, Closure $next, string $app): Response
    {
        // 'pages' = halaman publik page/event: layanannya ikut template page (event = Event, lainnya = Pages).
        [$branchId, $app] = match ($app) {
            'pages' => $this->pageBranch($request),
            'invoices' => [$this->invoiceBranch($request), 'invoices'],
            default => [null, $app],
        };

        if ($branchId === false || ! $branchId || app(AppAccess::class)->branchActive($branchId, $app)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Layanan ini sedang tidak tersedia.'], 503)
            : response()->view('errors.app-inactive', [], 503);
    }

    /** @return array{0: ?string, 1: string} [branch pemilik page, layanan yang harus aktif] */
    private function pageBranch(Request $request): array
    {
        $page = ($slug = $request->route('slug'))
            ? PageCategory::with('type:id,template')->where('slug', $slug)->first(['id', 'branch_id', 'page_type_id'])
            : TicketScanner::with('page:id,branch_id,page_type_id', 'page.type:id,template')->where('token', (string) $request->route('token'))->first()?->page;

        return [$page?->branch_id, $page ? $page->app() : Apps::PAGES];
    }

    /** Invoice yang sudah lunas tetap bisa dibuka sebagai bukti bayar. */
    private function invoiceBranch(Request $request): string|false|null
    {
        $invoice = Invoice::where('token', (string) $request->route('token'))->first(['branch_id', 'status']);

        return $invoice?->isPaid() ? false : $invoice?->branch_id;
    }
}
