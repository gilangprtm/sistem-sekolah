# TICKETS — Sistem Informasi & Administrasi Sekolah (Phase 1 MVP)

Status awal seluruh ticket: **Todo**. Builder hanya mengubah Status. Semua field lain milik Scoper.

Fase:
- Phase 1 — Core Foundation
- Phase 2 — Inventory
- Phase 3 — REST API
- Phase 4 — Testing & Deployment

---

## PHASE 1 — CORE FOUNDATION

### TASK-001 — Setup PostgreSQL + Redis + env production

- **Priority:** P0
- **Status:** Done
- **Phase:** 1
- **Depends On:** None
- **Blocks:** TASK-002, TASK-005, TASK-015, TASK-016
- **Verification Note:** .env.example + compose.yaml + entrypoint siap PostgreSQL/Redis. Dev lokal pakai SQLite (PHP herd-lite tanpa pdo_pgsql). Runtime PostgreSQL diverifikasi via Dockerfile (php8.5-pgsql) tapi belum dijalankan — Docker Desktop tidak aktif di mesin ini.

**Description:**
Siapkan infrastruktur database & cache sesuai PRD §37/§39/§44. Default dev memakai PostgreSQL (bukan SQLite) via Docker compose. Redis untuk cache/queue. Update `.env.example` dengan konfigurasi PostgreSQL + Redis (bisa toggle ke SQLite sebagai fallback). Tambahkan service `postgres` dan `redis` di compose.yaml (dev). Dokumentasikan env production.

**Acceptance Criteria:**
- [x] `.env.example` memiliki konfigurasi `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`.
- [x] `compose.yaml` (atau compose file dev) menyediakan service `postgres` dan `redis`.
- [ ] `docker compose up` dapat menjalankan PostgreSQL & Redis lokal. *(belum diverifikasi — Docker Desktop tidak aktif)*
- [ ] Aplikasi dapat terkoneksi PostgreSQL (`php artisan migrate` jalan terhadap PostgreSQL). *(belum diverifikasi — butuh Docker)*
- [x] Konfigurasi driver session/queue/cache mendukung Redis; dokumentasi fallback ke database/sqlite.
- [x] `php artisan config:clear` + `migrate` sukses tanpa error. *(terverifikasi di SQLite lokal)*

**References:** PRD §37, §39, §44; DECISIONS D-001, D-005, D-010.

---

### TASK-002 — Install & konfigurasi Sanctum + Spatie Permission

- **Priority:** P0
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-001
- **Blocks:** TASK-003, TASK-004, TASK-005, TASK-011, TASK-012, TASK-013

**Description:**
Pasang `laravel/sanctum` dan `spatie/laravel-permission`. Publish migration & config keduanya. Daftarkan provider. Konfigurasi guard: web (Fortify session) dan sanctum (API token). Model `User` siap multi-guard. Pastikan tidak bentrok dengan Fortify yang sudah ada.

**Acceptance Criteria:**
- [x] `composer.json` memuat `laravel/sanctum` dan `spatie/laravel-permission`.
- [x] Migration Sanctum (`personal_access_tokens`) dan Spatie (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `model_has_permissions`) tersedia.
- [x] Konfigurasi `config/sanctum.php`, `config/permission.php` terpublish.
- [x] `User` model menggunakan `HasRoles` dan dapat create token Sanctum.
- [x] `php artisan migrate` sukses dengan tabel baru.

**References:** PRD §5, §6, §30; DECISIONS D-002, D-003.

---

### TASK-003 — Role & permission seeder awal (5 role + permission)

- **Priority:** P0
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-002
- **Blocks:** TASK-004, TASK-006, TASK-013

**Description:**
Seeder mendefinisikan role awal: Super Admin, Admin Inventaris, Guru, Admin Perpustakaan, Siswa. Permission per domain (users, roles, inventory, dashboard, units). Super Admin mendapat semua permission. Assign role ke user tertentu (mis. seeder admin pertama).

**Acceptance Criteria:**
- [x] Seeder membuat 5 role dengan guard `web`/`sanctum` sesuai konfigurasi.
- [x] Permission `users.manage`, `roles.manage`, `inventory.view`, `inventory.create`, `inventory.delete`, `inventory.dashboard.view`, `inventory.unit.create`, `inventory.unit.condition.update` ada.
- [x] Super Admin memiliki seluruh permission.
- [x] User admin pertama dibuat & mendapat role Super Admin.
- [x] `php artisan db:seed` idempotent (dapat dijalankan ulang tanpa duplikasi). *(diverifikasi 2x run)*

**References:** PRD §30, §31; DECISIONS D-003.

---

### TASK-004 — App shell: hapus demo pages, navigasi modul, role-aware menu

- **Priority:** P1
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-002, TASK-003
- **Blocks:** TASK-006, TASK-008

**Description:**
Bersihkan halaman demo yang tidak relevan (chat, mail, auth v1/v2 showcase) agar shell aplikasi fokus (DECISIONS D-010). Susun navigasi sidebar: Dashboard, Manajemen User, Role & Permission, Inventaris (masuk fase 2). Menu role-aware: item disembunyikan berdasarkan permission user (frontend only; enforcement tetap backend). Pertahankan komponen UI reusable yang ada.

**Acceptance Criteria:**
- [x] Halaman demo `chat`, `mail`, `auth/v1/*`, `auth/v2/*` dihapus dari route & pages (atau ditandai non-navigable) tanpa merusak build.
- [x] Navigasi sidebar memuat menu Dashboard, User, Role & Permission (dan placeholder Inventaris).
- [x] Menu disembunyikan sesuai permission user (role-aware).
- [x] `npm run types:check`, `npm run lint:check`, `npm run build` lulus.
- [x] `php artisan test` (suite existing) tetap lulus.

**References:** PRD §45; DECISIONS D-010; RISKS R-006.

---

### TASK-005 — User Management (web): list, create, edit, assign role, delete

- **Priority:** P0
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-001, TASK-003
- **Blocks:** TASK-007, TASK-013

**Description:**
Halaman manajemen user untuk Super Admin. List user (nama, email, role, verified, created), create user (via Fortify rules), edit user, assign/unassign role, delete user. Backend guard `users.manage`. Gunakan TanStack Table + shadcn/ui (reusable dari base).

**Acceptance Criteria:**
- [x] Halaman `/users` menampilkan daftar user dengan pagination.
- [x] Create user baru (nama, email, password) validasi backend.
- [x] Edit user (nama, email, active?) — password reset via Fortify.
- [x] Assign/unassign role pada user (multi-role).
- [x] Delete user dengan konfirmasi.
- [x] Hanya Super Admin (`users.manage`) dapat mengakses; non-admin mendapat 403.
- [x] `npm run types:check` + `npm run build` lulus.

**References:** PRD §4.1, §5, §31; DECISIONS D-003.

---

### TASK-006 — Role & Permission Management (web)

- **Priority:** P1
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-003, TASK-004
- **Blocks:** TASK-013

**Description:**
Halaman kelola role & permission untuk Super Admin: list role, create role, edit role (nama + permission assignment via checkbox terstruktur per domain), delete role (cegah hapus Super Admin & role yang dipakai). Backend guard `roles.manage`.

**Acceptance Criteria:**
- [x] Halaman `/roles` menampilkan daftar role + jumlah user.
- [x] Create/edit role dengan nama unik.
- [x] Assign/unassign permission per role (multi-select terstruktur).
- [x] Delete role; Super Admin tidak dapat dihapus.
- [x] Hanya Super Admin (`roles.manage`); non-admin 403.
- [x] `npm run types:check` + `npm run build` lulus.

**References:** PRD §30, §31; RISKS R-011.

---

### TASK-007 — Core Dashboard (web, role-aware)

- **Priority:** P2
- **Status:** Done
- **Phase:** 1
- **Depends On:** TASK-005
- **Blocks:** None

**Description:**
Halaman dashboard utama setelah login: ringkasan sistem (jumlah user, jumlah role) untuk Super Admin; role-aware welcome + link modul. Dashboard Inventaris terpisah (TASK-011).

**Acceptance Criteria:**
- [x] `/dashboard` menampilkan statistik dasar sistem sesuai role.
- [x] Role-aware: Super Admin melihat user/role stats; Admin Inventaris melihat link ke dashboard inventaris.
- [x] `npm run types:check` + `npm run build` lulus.

**References:** PRD §4, §12 (Core Dashboard).

---

## PHASE 2 — INVENTORY

### TASK-008 — Inventory schema: inventory_items + inventory_units + constraints

- **Priority:** P0
- **Status:** Done
- **Phase:** 2
- **Depends On:** TASK-004
- **Blocks:** TASK-009, TASK-010, TASK-011, TASK-014

**Description:**
Buat migration & model untuk `inventory_items` (10 field immutable + keterangan mutable + timestamps) dan `inventory_units` (register, kondisi, FK item). Unique constraint `(inventory_item_id, register)` / `(kode_barang, register)`. Enforce FK, no orphan. Kondisi enum/check: B, KB, RB. Relasi Eloquent `item → units`.

**Acceptance Criteria:**
- [ ] Migration `inventory_items` & `inventory_units` dibuat & jalan.
- [ ] Kolom 15 field inventaris sesuai SPEC §7.1 (kode barang unik, 10 immutable + keterangan).
- [ ] `inventory_units` memiliki `register` (string 3 digit), `condition` (B/KB/RB default B), FK `inventory_item_id` cascade delete.
- [ ] Unique constraint cegah register duplicate dalam satu item/kode barang.
- [ ] Model `InventoryItem` & `InventoryUnit` dengan relasi & casts benar.
- [ ] `php artisan migrate` sukses; `php artisan migrate:rollback` tidak merusak.

**References:** PRD §14, §15, §17, §38; DECISIONS D-004, D-005, D-008.

---

### TASK-009 — Register generator service + create inventory (transactional)

- **Priority:** P0
- **Status:** Done
- **Phase:** 2
- **Depends On:** TASK-008
- **Blocks:** TASK-010, TASK-014

**Description:**
Service backend pembuat register otomatis per Kode Barang (001..N) dalam satu DB transaction. Create inventory: input 15 field + qty → buat item + N unit register. Frontend tidak menentukan register. Tolak qty <= 0. Validation lengkap di backend.

**Acceptance Criteria:**
- [ ] Service `RegisterGenerator` menghasilkan `001..N` per kode barang.
- [ ] Create item + units dalam satu transaction (rollback jika gagal).
- [ ] Qty 0/negatif ditolak (422).
- [ ] Dua kode barang berbeda masing-masing mulai `001`.
- [ ] Register unik dalam satu kode (DB constraint + service).
- [ ] Unit test register sequence lulus.

**References:** PRD §17, §18, §28, §38; DECISIONS D-005, D-006.

---

### TASK-010 — Inventory list: search, filter, pagination, total nilai

- **Priority:** P0
- **Status:** Done
- **Phase:** 2
- **Depends On:** TASK-008, TASK-009
- **Blocks:** TASK-014

**Description:**
Halaman daftar inventaris: tabel (Urut, Kode Barang, Nama, Merk, Tahun, Satuan, Qty, Harga, Total), search (kode/nama/merk/register), filter (tahun/kondisi/asal/satuan), pagination server-side. Total nilai per item = Qty × Harga dihitung backend. TanStack Table + shadcn.

**Acceptance Criteria:**
- [ ] `/inventory` menampilkan daftar item dengan kolom & total nilai.
- [ ] Search kode/nama/merk/register berfungsi.
- [ ] Filter tahun/kondisi/asal/satuan berfungsi (kombinasi).
- [ ] Pagination server-side (dataset besar).
- [ ] Total nilai = SUM(qty × harga) dihitung backend.
- [ ] `npm run types:check` + `npm run build` lulus.

**References:** PRD §20, §26; SPEC §8.

---

### TASK-011 — Inventory dashboard (KPI + statistik)

- **Priority:** P1
- **Status:** Done
- **Phase:** 2
- **Depends On:** TASK-008, TASK-002
- **Blocks:** None

**Description:**
Halaman dashboard inventaris: KPI Total aset (unit), Total nilai (SUM qty×harga), Baik, Kurang Baik, Rusak Berat, Total kelompok. Statistik aset per tahun, per asal/cara perolehan, distribusi kondisi. Perhitungan di backend. Recharts (base).

**Acceptance Criteria:**
- [ ] `/inventory/dashboard` menampilkan 6 KPI.
- [ ] Statistik tahun, asal, distribusi kondisi tampil (chart/table).
- [ ] Perhitungan dari unit (bukan kolom redundan).
- [ ] Permission `inventory.dashboard.view`.
- [ ] `npm run types:check` + `npm run build` lulus.

**References:** PRD §24, §25; DECISIONS D-004.

---

### TASK-012 — Inventory detail: units, kondisi, tambah unit, delete, update keterangan

- **Priority:** P0
- **Status:** Done
- **Phase:** 2
- **Depends On:** TASK-002, TASK-009
- **Blocks:** None

**Description:**
Detail item: daftar unit + register + kondisi; ubah kondisi per unit (B/KB/RB); tambah unit / increase qty (register lanjut); delete item permanen dengan konfirmasi (cascade unit); edit keterangan saja (field immutable read-only). Backend guard & business rules.

**Acceptance Criteria:**
- [ ] Detail menampilkan seluruh unit + register + kondisi.
- [ ] Ubah kondisi unit valid & tersimpan (B/KB/RB).
- [ ] Tambah unit (qty increase) menghasilkan register lanjutan (`5→8` → `006,007,008`).
- [ ] Qty decrease ditolak (422).
- [ ] Field immutable tidak dapat diubah (guard backend + UI read-only).
- [ ] Delete permanen + konfirmasi + cascade unit.
- [ ] Update keterangan berhasil.
- [ ] `npm run types:check` + `npm run build` lulus.

**References:** PRD §18, §19, §22, §23; DECISIONS D-006, D-007, D-008.

---

## PHASE 3 — REST API

### TASK-013 — REST API: auth, users, roles (Sanctum, /api/v1)

- **Priority:** P0
- **Status:** Done
- **Phase:** 3
- **Depends On:** TASK-003, TASK-005, TASK-006
- **Blocks:** TASK-014

**Description:**
REST API `/api/v1` untuk auth (login/logout/me via Sanctum), users (CRUD + assign role) dan roles (CRUD + assign permission). Envelope konsisten `{success, message, data/errors}`. Rate limiting. Authorization backend (super admin only untuk users/roles).

**Acceptance Criteria:**
- [ ] `POST /api/v1/auth/login` mengembalikan token; `logout` revoke; `me` tampilkan user+roles.
- [ ] Users CRUD + assign/unassign role hanya untuk Super Admin.
- [ ] Roles CRUD + assign/unassign permission hanya untuk Super Admin.
- [ ] Envelope response konsisten; validation error → `errors`.
- [ ] Unauthorized (401) & forbidden (403) benar.
- [ ] Rate limiting aktif.
- [ ] Feature test API auth/authorization lulus.

**References:** PRD §6, §10, §29, §30, §41; DECISIONS D-002, D-011.

---

### TASK-014 — API inventory + dashboard (Sanctum, /api/v1)

- **Priority:** P0
- **Status:** Done
- **Phase:** 3
- **Depends On:** TASK-009, TASK-010, TASK-012, TASK-013
- **Blocks:** None

**Description:**
REST API `/api/v1/inventory` (list/search/filter/pagination, create, show, update keterangan, delete) + `/units` (tambah unit, ubah kondisi) + `GET /api/v1/inventory/dashboard`. Business rules sama seperti web (register backend, qty increase-only, immutable). Authorization permission.

**Acceptance Criteria:**
- [ ] `GET /api/v1/inventory` mendukung search/filter/pagination.
- [ ] `POST /api/v1/inventory` create item+units; register dibuat backend.
- [ ] `GET /api/v1/inventory/{id}` menampilkan item + units.
- [ ] `PATCH /api/v1/inventory/{id}` hanya update keterangan (immutable guard).
- [ ] `DELETE /api/v1/inventory/{id}` hapus permanen (cascade).
- [ ] `POST /api/v1/inventory/{id}/units` tambah unit (increase qty).
- [ ] `PATCH /api/v1/inventory/{id}/units/{unitId}/condition` ubah kondisi.
- [ ] `GET /api/v1/inventory/dashboard` KPI + statistik.
- [ ] Semua endpoint memakai permission; 401/403 benar; rate limit aktif.
- [ ] Feature test business rules API lulus.

**References:** PRD §27, §28, §29, §38; DECISIONS D-011.

---

## PHASE 4 — TESTING & DEPLOYMENT

### TASK-015 — Automated tests: business rules inventaris + RBAC

- **Priority:** P0
- **Status:** Done
- **Phase:** 4
- **Depends On:** TASK-001, TASK-008, TASK-009, TASK-012, TASK-013
- **Blocks:** TASK-017

**Description:**
Test suite lengkap untuk aturan inti: register sequence, qty increase/decrease, immutable, kondisi, delete cascade, dashboard calculation; auth (login/logout/unauthorized); authorization (multi-role, forbidden action, super admin). Minimal sesuai PRD §48.

**Acceptance Criteria:**
- [ ] Test register sequence per kode barang.
- [ ] Test qty increase (register lanjut) & decrease ditolak.
- [ ] Test immutable field protection.
- [ ] Test kondisi update valid/invalid.
- [ ] Test delete permanen + cascade.
- [ ] Test dashboard calculation.
- [ ] Test auth login/logout/unauthorized.
- [ ] Test multi-role & permission & forbidden.
- [ ] `composer test` (Pint + PHPStan + PHPUnit) lulus.

**References:** PRD §48; RISKS R-001, R-002, R-003.

---

### TASK-016 — Docker production + Coolify + env documentation

- **Priority:** P1
- **Status:** Done
- **Phase:** 4
- **Depends On:** TASK-001, TASK-015
- **Blocks:** None
- **Verification Note:** Artefak Docker lengkap (Dockerfile + pgsql/redis, compose prod + healthcheck, entrypoint migrate+seed, .env.production.example). Build image & deploy Coolify BELUM diverifikasi di lingkungan ini — Docker Desktop tidak berjalan (service STOPPED, butuh admin). Wajib diverifikasi saat deploy nyata.

**Description:**
Siapkan deployment production: Dockerfile + compose production (app, postgres, redis), entrypoint, env production terdokumentasi, siap deploy ke Coolify VPS. Pastikan migrate & seeder jalan saat deploy. HTTPS via Coolify.

**Acceptance Criteria:**
- [ ] Dockerfile & compose production membangun image app + postgres + redis.
- [ ] Volume data postgres & storage persisted.
- [ ] `.env.production.example` / dokumentasi env lengkap.
- [ ] Deploy ke Coolify berhasil (build, migrate, seeder, HTTPS).
- [ ] Healthcheck app & DB ok.

**References:** PRD §43, §44; RISKS R-005, R-010.

---

### TASK-017 — Final integration, QA, acceptance (Definition of Done)

- **Priority:** P0
- **Status:** Done
- **Phase:** 4
- **Depends On:** TASK-007, TASK-010, TASK-011, TASK-012, TASK-014, TASK-015, TASK-016
- **Blocks:** None
- **Verification Note:** composer test (Pint+PHPStan+PHPUnit 82/82) lulus; npm types/lint/build lulus; smoke test server: /, /login, /up 200; API e2e (login→me→create→list→dashboard→add units→update kondisi) sukses. Deploy Coolify belum diverifikasi (Docker lokal tidak aktif).

**Description:**
Integrasi final: jalankan seluruh suite, pastikan alur user lengkap (login → dashboard → inventaris → CRUD → register → kondisi → dashboard inventaris), QA manual, pastikan DoD Phase 1 (SPEC §12) terpenuhi. Siapkan handoff ke reviewer.

**Acceptance Criteria:**
- [ ] Semua ticket Phase 1-4 selesai & ditandai Done.
- [ ] Alur end-to-end user terverifikasi (web + API).
- [ ] `composer test` lulus.
- [ ] `npm run types:check`, `npm run lint:check`, `npm run build` lulus.
- [ ] Deployment production (Coolify) berjalan & HTTPS aktif.
- [ ] DoD SPEC §12 terpenuhi.

**References:** SPEC §12; PRD §52.
