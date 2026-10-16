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
        $middleware->validateCsrfTokens(except: ['payment/duitku/callback']);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'invoice.api' => \App\Http\Middleware\VerifyInvoiceSignature::class,
            'wa.api' => \App\Http\Middleware\VerifyWaApiSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
