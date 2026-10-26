<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
        // Header keamanan (anti-clickjacking, nosniff, referrer) untuk semua halaman web; /page/* tetap boleh di-embed.
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: ['payment/duitku/callback']);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'invoice.api' => \App\Http\Middleware\VerifyInvoiceSignature::class,
            'wa.api' => \App\Http\Middleware\VerifyWaApiKey::class,
            'frontend.api-key' => \App\Http\Middleware\VerifyFrontendApiKey::class,
            'public.app' => \App\Http\Middleware\EnsurePublicAppActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "419 Page Expired" (form/halaman terlalu lama dibiarkan): jangan tampilkan halaman error.
        // Sesi sudah habis -> ke halaman login; masih login -> kembali ke halaman tadi dengan isian yang sama.
        // Laravel mengubah TokenMismatchException menjadi HttpException 419 sebelum callback ini dipanggil.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if (! $e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException) {
                return null;
            }

            $loggedIn = $request->user() !== null && ! $request->routeIs('logout');
            $message = $loggedIn
                ? 'Halaman ini sudah terlalu lama dibuka. Silakan kirim ulang ya.'
                : 'Sesi Anda sudah berakhir karena terlalu lama tidak aktif. Silakan login kembali.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => $loggedIn ? null : route('login')], 419);
            }

            if (! $loggedIn) {
                if ($request->routeIs('logout')) {
                    \Illuminate\Support\Facades\Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return redirect()->route('login')->withInput($request->only('email'))->with('warning', $message);
            }

            return back()->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'pin']))->with('warning', $message);
        });
        // API Invoice selalu menjawab JSON dengan bentuk {success, message, errors}.
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['success' => false, 'message' => 'Data belum lengkap atau tidak valid.', 'errors' => $e->errors()], 422);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['success' => false, 'message' => 'Endpoint atau data tidak ditemukan.'], 404);
            }
        });
    })->create();
