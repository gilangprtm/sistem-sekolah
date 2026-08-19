# Laravel Shadcn Admin Dashboard

Admin dashboard berbasis **Laravel 13** + **React 19 / Inertia 3** + **Tailwind CSS 4** dengan komponen **shadcn/ui**.

## Fitur

- Laravel 13 + Fortify (autentikasi: login, register, two-factor, passkeys)
- React 19 + Inertia 3 (SPA-style pages)
- Tailwind CSS 4 + shadcn/ui components
- Recharts, FullCalendar, dnd-kit, TanStack Table, dan lainnya
- SQLite (session, queue, cache via database driver)

## Persyaratan

- PHP 8.3+
- Composer
- Node.js 20+ (npm)

## Instalasi Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
php artisan serve
```

App berjalan di http://localhost:8000.

## Menjalankan dengan Docker

```bash
export APP_KEY=$(php artisan key:generate --show)
docker compose up -d --build
```

App berjalan di http://localhost:8000 (container: nginx + php8.5-fpm + SQLite).

Data SQLite, storage, dan log disimpan di volume Docker terpisah.

## Scripts

```bash
npm run dev        # Vite dev server (hot reload)
npm run build      # Production build
npm run lint       # ESLint + auto-fix
npm run types:check # TypeScript + PHPStan
composer test      # Pint + PHPStan + PHPUnit
```

## Lisensi

MIT
