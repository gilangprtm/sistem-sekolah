# Sistem Sekolah

Sistem administrasi sekolah berbasis Laravel, React, dan Inertia. Repository ini saat ini berisi fondasi autentikasi, role dan permission, dashboard, inventaris, serta API inventaris versi `v1`.

## Stack

- Laravel 13 dan PHP 8.3+
- React 19, Inertia 3, dan TypeScript
- Tailwind CSS 4 dan shadcn/ui
- PostgreSQL untuk database utama
- Redis untuk cache dan queue pada konfigurasi production
- Fortify, Sanctum, dan Spatie Permission
- Nginx dan PHP-FPM pada image Docker

## Prasyarat

Untuk instalasi lokal:

- PHP 8.3 atau lebih baru dengan extension yang dibutuhkan Laravel
- Composer
- Node.js dan npm
- PostgreSQL jika memakai konfigurasi database default
- Redis jika menjalankan cache atau queue melalui Redis

Untuk jalur Docker:

- Docker Engine
- Docker Compose plugin

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
npm run build
php artisan serve
```

Aplikasi lokal tersedia di `http://localhost:8000`.

Jangan menjalankan `php artisan migrate` pada database bersama tanpa memastikan backup dan urutan migration telah ditinjau.

## Development lokal

Jalankan backend Laravel dan Vite secara terpisah pada dua terminal:

Terminal pertama:

```bash
php artisan serve
```

Terminal kedua:

```bash
npm run dev
```

Jika fitur queue sedang dikembangkan, jalankan worker pada terminal tambahan:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Workflow ini adalah jalur development utama. Docker bukan prasyarat untuk menjalankan aplikasi secara lokal.

## Docker

Compose menjalankan aplikasi, PostgreSQL, dan Redis. Konfigurasi default pada `compose.yaml` ditujukan untuk development lokal, bukan deployment internet langsung.

```bash
export APP_KEY="$(php artisan key:generate --show)"
docker compose up -d --build
```

Aplikasi tersedia di `http://localhost:8000`.

Sebelum memakai compose pada environment bersama atau production:

- ganti seluruh credential database dengan secret dari environment atau secret manager;
- jangan membuka port PostgreSQL dan Redis ke jaringan yang tidak diperlukan;
- pastikan `APP_KEY` tersedia dan tidak menggunakan nilai kosong;
- tinjau volume, backup, log, healthcheck, dan kebijakan restart.

## Quality checks

Setelah dependency backend tersedia, jalankan:

```bash
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer test
```

`npm run build` menggunakan Wayfinder dan membutuhkan `php artisan` untuk menghasilkan route dan action types. Karena itu build frontend tidak dapat dianggap mandiri dari runtime Laravel.

## API

Endpoint inventaris berada di bawah `/api/v1` dan menggunakan token Sanctum. Detail route, permission, response, pagination, dan rate limit harus mengikuti implementasi yang telah diverifikasi dari source dan test; README ini tidak mengklaim contract yang belum memiliki evidence runtime terbaru.

## Database

Database saat ini menggunakan taxonomy domain yang telah diterapkan pada modul Inventaris, termasuk `m_inventory_*` untuk master/reference dan `tr_inventory_*` untuk transaksi. Tabel Laravel, package, dan system tetap mengikuti kontraknya. Perubahan schema berikutnya wajib dilakukan melalui migration kompatibel setelah foreign key, model, seeder, test, upgrade, dan rollback diverifikasi.

## Status verifikasi repository

Pada audit 18 September 2026:

- `npm install`: berhasil.
- `npm audit`: berhasil dengan 0 vulnerability setelah patch dependency non-major.
- `composer install`: belum dapat dijalankan karena Composer tidak tersedia pada environment audit.
- `npm run types:check`: belum lulus karena generated Wayfinder route/action types belum tersedia.
- `npm run build`: belum lulus karena membutuhkan PHP dan `php artisan wayfinder:generate`.
- Test PHP, migration, PHPStan, Pint, dan smoke test aplikasi: belum dijalankan.
- Docker fallback: belum dijalankan karena Docker daemon tidak aktif pada environment audit.

Status ini bukan klaim production-ready. Gunakan hasil command terbaru sebagai evidence sebelum menyatakan build, test, migration, atau deployment berhasil.

## License

MIT
