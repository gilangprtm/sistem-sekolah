# PROJECT — Sistem Informasi & Administrasi Sekolah

**Status:** Phase 1 — MVP (Core + Inventaris + API)
**PRD:** `PRD — Sistem Informasi & Administrasi Sekolah.md` (root repo, Draft)
**Arsitektur:** Laravel Modular Monolith + Inertia + REST API
**Foundation:** `gilangprtm/laravel-shadcn-admin-dashboard` (sudah di-clone ke repo ini)

---

## 1. Overview

Sistem Informasi & Administrasi Sekolah adalah platform terintegrasi untuk mengelola data dan administrasi sekolah dalam satu sistem. Dibangun sebagai **modular monolith** sehingga modul dapat ditambahkan bertahap (Guru, Siswa, Perpustakaan, Kurikulum, Kesiswaan, Sarpras, Administrasi, PWA Siswa) tanpa membangun backend terpisah.

**Phase 1 (scope implementasi sekarang):**

- Core: Authentication, User management, Role, Permission, Dashboard.
- Inventaris: asset registry sekolah (bukan stock warehouse) — dashboard, CRUD, register, unit, kondisi, search, filter, pagination.
- REST API `/api/v1`: auth, users, roles, inventory, inventory dashboard.

## 2. Goals Phase 1

1. Fondasi auth & RBAC (Fortify web + Sanctum API + Spatie Permission, multi-role).
2. Modul Inventaris lengkap sesuai aturan bisnis PRD (register otomatis, qty increase-only, kondisi per unit, immutable fields, delete permanen).
3. REST API `/api/v1` siap dipakai PWA/client eksternal masa depan.
4. PostgreSQL + Redis sebagai infrastruktur data; deployment Docker + Coolify di VPS.
5. Automated test untuk business rules inti (register & qty) dan authorization.

## 3. Technology Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.3+, Laravel Fortify, Laravel Sanctum, Spatie Permission |
| API | REST `/api/v1`, API versioning, rate limiting |
| Frontend | React 19, Inertia 3, TypeScript, Tailwind CSS 4, shadcn/ui |
| Database | PostgreSQL (primary), SQLite hanya untuk fallback lokal |
| Cache / Queue | Redis |
| Infrastruktur | Docker, Coolify, VPS, HTTPS |
| Quality | PHPUnit, PHPStan, Pint, ESLint, Prettier, `tsc --noEmit`, `npm run build` |

## 4. Constraints

- **Pertahankan foundation**: komponen & struktur yang sudah ada (`laravel-shadcn-admin-dashboard`) dipertahankan selama sesuai kebutuhan; tidak rewrite framework.
- **Laravel adalah sumber kebenaran**: register, qty validation, permission, total aset/nilai, validitas immutable, validitas kondisi — semua diputuskan backend.
- **Frontend bukan authorization boundary**: menu disembunyikan ≠ aman; backend tetap 403.
- **PostgreSQL primary data store**; Redis bukan primary data store.
- **Satu tabel `users`** untuk semua pengguna; tidak ada tabel login terpisah.
- **Multi-role**: satu user bisa punya banyak role; permission hanya melalui role.
- **Tidak microservices** pada fase awal; modularitas level domain & code organization.
- **Docs `.agents/scoper/` append-only**: jangan pernah rewrite/hapus isi lama antar cycle.

## 5. Struktur Repo Relevan (base)

```
app/
  Actions/Fortify/          # CreateNewUser, ResetUserPassword (exists)
  Http/Controllers/         # DashboardController, Settings/...
  Http/Middleware/          # HandleInertiaRequests, HandleAppearance
  Models/User.php           # Fortify user (PasskeyAuthenticatable, 2FA)
  Providers/                # FortifyServiceProvider, AppServiceProvider
config/                     # fortify.php, database.php, session.php, ...
database/migrations/        # users, cache, jobs, passkeys, 2FA columns
resources/js/
  pages/                    # welcome, auth (demo v1/v2), dashboard, chat, mail, settings
  layouts/ navigation/ components/ lib/ ...
routes/
  web.php                   # welcome + auth demo + dashboard.php
  dashboard.php             # /dashboard, /chat, /mail, settings
tests/                      # Feature (Auth, Settings, Dashboard), Unit
compose.yaml                # Docker app (nginx + php-fpm + sqlite volume)
Dockerfile, docker/         # entrypoint, nginx.conf, php.ini
```

## 6. Phase & Deliverable

- **Phase 1 — Core Foundation**: infra PostgreSQL/Redis, Sanctum+Spatie, user management, role/permission management, app shell + navigasi modul.
- **Phase 2 — Inventory**: schema, create + register otomatis, list/search/filter/pagination, update/delete, increase qty, kondisi unit, dashboard inventaris.
- **Phase 3 — REST API**: foundation `/api/v1`, auth/users/roles API, inventory API.
- **Phase 4 — Testing & Deployment**: automated tests business rules, Docker production + Coolify.
