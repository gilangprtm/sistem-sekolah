<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
            'theme_mode',
            'theme_preset',
            'font',
            'content_layout',
            'navbar_style',
            'sidebar_variant',
            'sidebar_collapsible',
        ]);

        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        Inertia::handleExceptionsUsing(function (ExceptionResponse $exception): ?ExceptionResponse {
            $request = $exception->request;

            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $status = $exception->statusCode();
            $copy = match ($status) {
                403 => ['title' => 'Akses ditolak', 'message' => 'Anda tidak memiliki izin untuk membuka halaman ini.'],
                404 => ['title' => 'Halaman tidak ditemukan', 'message' => 'Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.'],
                419 => ['title' => 'Sesi kedaluwarsa', 'message' => 'Sesi Anda telah berakhir. Silakan ulangi setelah memuat halaman kembali.'],
                429 => ['title' => 'Terlalu banyak permintaan', 'message' => 'Mohon tunggu sebentar sebelum mencoba kembali.'],
                500 => ['title' => 'Terjadi kesalahan', 'message' => 'Terjadi kendala pada server. Silakan coba lagi nanti.'],
                503 => ['title' => 'Layanan sementara tidak tersedia', 'message' => 'Layanan sedang dipelihara atau mengalami gangguan sementara.'],
                default => ['title' => 'Permintaan tidak dapat diproses', 'message' => 'Terjadi kendala saat memproses permintaan Anda.'],
            };

            return $exception
                ->render('errors/error', [
                    'status' => $status,
                    'title' => $copy['title'],
                    'message' => $copy['message'],
                ])
                ->withSharedData();
        });
    })->create();
