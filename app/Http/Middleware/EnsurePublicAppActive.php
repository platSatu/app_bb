<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use App\Models\PageCategory;
use App\Models\TicketScanner;
use App\Services\Subscription\AppAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman publik (page, scan tiket, link bayar invoice) ikut tutup kalau layanan branch pemiliknya habis.
 * Data yang tidak ditemukan diteruskan saja; controller yang menjawab 404.
 */
class EnsurePublicAppActive
{
    public function handle(Request $request, Closure $next, string $app): Response
    {
        $branchId = match ($app) {
            'pages' => $this->pageBranch($request),
            'invoices' => $this->invoiceBranch($request),
            default => null,
        };

        if ($branchId === false || ! $branchId || app(AppAccess::class)->branchActive($branchId, $app)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Layanan ini sedang tidak tersedia.'], 503)
            : response()->view('errors.app-inactive', [], 503);
    }

    private function pageBranch(Request $request): ?string
    {
        if ($slug = $request->route('slug')) {
            return PageCategory::where('slug', $slug)->value('branch_id');
        }

        return TicketScanner::with('page:id,branch_id')->where('token', (string) $request->route('token'))->first()?->page?->branch_id;
    }

    /** Invoice yang sudah lunas tetap bisa dibuka sebagai bukti bayar. */
    private function invoiceBranch(Request $request): string|false|null
    {
        $invoice = Invoice::where('token', (string) $request->route('token'))->first(['branch_id', 'status']);

        return $invoice?->isPaid() ? false : $invoice?->branch_id;
    }
}
