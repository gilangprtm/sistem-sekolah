<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-mode="{{ $page['props']['preferences']['theme_mode'] ?? 'light' }}"
    data-theme-preset="{{ $page['props']['preferences']['theme_preset'] ?? 'default' }}"
    data-content-layout="{{ $page['props']['preferences']['content_layout'] ?? 'centered' }}"
    data-navbar-style="{{ $page['props']['preferences']['navbar_style'] ?? 'sticky' }}"
    data-sidebar-variant="{{ $page['props']['preferences']['sidebar_variant'] ?? 'sidebar' }}"
    data-sidebar-collapsible="{{ $page['props']['preferences']['sidebar_collapsible'] ?? 'icon' }}"
    data-font="{{ $page['props']['preferences']['font'] ?? 'inter' }}"
    @class(['dark' => ($appearance ?? 'system') == 'dark'])
    suppressHydrationWarning>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        <meta name="application-name" content="Portal SMPN 17 Denpasar">
        <meta name="apple-mobile-web-app-title" content="Portal SMPN 17 Denpasar">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="icon" type="image/x-icon" sizes="32x32" href="/favicon.ico?v=school-logo-3">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple_touch_icon.png?v=school-logo-3">
        <link rel="manifest" href="/manifest.webmanifest?v=school-logo-2">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Sistem Sekolah SMP Negeri 17 Denpasar') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
