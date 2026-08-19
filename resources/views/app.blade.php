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
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
