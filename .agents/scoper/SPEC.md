# SPEC — Sistem Informasi & Administrasi Sekolah (Phase 1 MVP)

Dokumen ini menjelaskan **WHAT** (perilaku & aturan produk), bukan HOW (implementasi teknis).

---

## 1. Product Scope (Phase 1)

### In Scope
- Core: Authentication (web), User management, Role & Permission (multi-role), Dashboard.
- Inventaris: asset registry — dashboard, CRUD kelompok barang, unit ber-register, kondisi per unit, search, filter, pagination.
- REST API `/api/v1`: auth, users, roles, inventory, inventory dashboard.
- Automated test untuk business rules inti & authorization.

### Out of Scope (Phase 1)
- Modul Guru/Siswa/Perpustakaan/Kurikulum/Kesiswaan/Sarpras/Administrasi lengkap.
- PWA Siswa.
- Export Excel/PDF (open question — asumsi OUT, lihat DECISIONS D-009).
- Generator Kode Barang otomatis.
- Lokasi/ruangan, foto aset, nomor dokumen perolehan.
- Soft delete, restore, delete history, audit history penghapusan.
- Maintenance/mutasi/penghapusan aset kompleks.
- Sistem notifikasi kompleks, integrasi eksternal.

---

## 2. Actors & Roles

| Role | Hak inti |
|---|---|
| Super Admin | Kelola user, role, permission; akses semua modul; bypass permission normal |
| Admin Inventaris | Dashboard inventaris, create/view/delete inventaris, tambah unit, ubah kondisi unit, lihat nilai & statistik aset |
| Guru | Role dasar (belum ada modul khusus Phase 1) |
| Admin Perpustakaan | Disiapkan, belum fokus Phase 1 |
| Siswa | Role disiapkan (PWA masa depan) |

**Multi-role:** satu user dapat memiliki banyak role; permission berasal dari semua role yang dimiliki.

---

## 3. Authentication

- Satu tabel `users`.
- Web admin: Fortify (login, logout, register, password, 2FA, passkeys — sesuai kemampuan base).
- REST API: Sanctum token.
- Email verification aktif (base sudah menyiapkan verifikasi; dipertahankan).

---

## 4. Authorization (RBAC)

- Role & Permission via Spatie Permission.
- Permission contoh: `inventory.view`, `inventory.create`, `inventory.delete`, `inventory.dashboard.view`, `inventory.unit.create`, `inventory.unit.condition.update`, `users.manage`, `roles.manage`.
- Super Admin didefinisikan sebagai role khusus dengan akses penuh (gate `isSuperAdmin`).
- Enforcement selalu di backend; frontend hanya menyembunyikan menu (bukan keamanan).

---

## 5. User Management (Web)

- Daftar user: nama, email, role assignment, status verifikasi, dibuat pada.
- Create user, edit user, assign/unassign role, delete user.
- Password di-reset melalui Fortify (lupa password / admin reset).
- Hanya Super Admin yang dapat mengakses halaman ini.

---

## 6. Role & Permission Management (Web)

- Daftar role: nama, guard, jumlah user.
- Create role, edit role, assign/unassign permission, delete role.
- Menampilkan permission terstruktur per domain.
- Hanya Super Admin.

---

## 7. Inventory Domain

### 7.1 Inventory Item (kelompok barang)
Field (immutable setelah create):

| No | Field | Keterangan |
|---|---|---|
| 1 | Kode Barang | manual, immutable, unik |
| 2 | Nama/Jenis Barang | |
| 3 | Merk/Type | |
| 4 | No Sertifikat/No Pabrik/No Chasis/No Mesin | identitas |
| 5 | Bahan | |
| 6 | Asal/Cara Perolehan Barang | |
| 7 | Tahun Pembelian | |
| 8 | Ukuran/Konstruksi (P,S,D) | |
| 9 | Satuan | |
| 10 | Harga | harga per unit |

Mutable: Keterangan.

### 7.2 Inventory Unit (unit fisik)
- Memiliki Register (`001`, `002`, ...) — dibuat backend, unik per Kode Barang.
- Memiliki Kondisi: `B` (Baik), `KB` (Kurang Baik), `RB` (Rusak Berat).
- Qty pada item = jumlah unit.

### 7.3 Aturan Kunci
1. **Register otomatis**: Qty N → N unit dengan register `001..N` per Kode Barang.
2. **Qty tidak boleh berkurang** (ditolak). **Qty boleh bertambah** → unit baru melanjutkan register terakhir (`5→8` → `006,007,008`).
3. **Kondisi per unit** dapat diubah kapan saja; RB tetap tercatat (tidak dihapus).
4. **Tahun berbeda = kelompok baru** (walaupun nama sama).
5. **Harga per unit**; total = Qty × Harga (dihitung backend).
6. **Immutable fields**: tidak dapat diedit; koreksi via delete + create ulang.
7. **Delete permanen** dengan konfirmasi UI; cascade hapus unit.
8. **Frontend tidak menentukan register/qty-validation/permission/total**.

---

## 8. Inventory UI (Web)

### Daftar Inventaris
- Tabel: Urut (auto/incremental global atau per tampilan), Kode Barang, Nama/Jenis, Merk/Type, Tahun, Satuan, Qty, Harga, Total, Kondisi ringkas, Aksi.
- **Search**: kode barang, nama/jenis, merk/type, register.
- **Filter**: tahun pembelian, kondisi, asal/cara perolehan, satuan.
- **Pagination** wajib (server-side).

### Form Create/Edit Inventaris
- Create: input 15 field + qty; register dihasilkan backend.
- Edit: hanya keterangan (dan qty increase + tambah unit) yang bisa diubah; field immutable tampil read-only.

### Detail Item (opsional lanjutan)
- Menampilkan daftar unit + register + kondisi, aksi ubah kondisi per unit.

### Delete
- Konfirmasi eksplisit; hapus item + seluruh unit.

### Dashboard Inventaris
- KPI: Total aset (jumlah unit), Total nilai aset (SUM qty×harga), Aset Baik, Aset Kurang Baik, Aset Rusak Berat, Total kelompok inventaris.
- Statistik: aset per tahun, aset per asal/cara perolehan, distribusi kondisi.

---

## 9. REST API `/api/v1`

### Envelope
```json
{ "success": true, "message": "Data berhasil diproses", "data": {} }
{ "success": false, "message": "Data tidak dapat diproses", "errors": {} }
```

### Auth
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/user` (me)

### Users (Super Admin)
- `GET/POST /api/v1/users`
- `GET/PATCH/DELETE /api/v1/users/{id}`
- `POST /api/v1/users/{id}/roles` (assign), `DELETE /api/v1/users/{id}/roles/{role}`

### Roles (Super Admin)
- `GET/POST /api/v1/roles`
- `GET/PATCH/DELETE /api/v1/roles/{id}`
- `POST /api/v1/roles/{id}/permissions` (assign), `DELETE /api/v1/roles/{id}/permissions/{permission}`

### Inventory
- `GET /api/v1/inventory` (list + search/filter/pagination)
- `POST /api/v1/inventory` (create item + units)
- `GET /api/v1/inventory/{id}` (item + units)
- `DELETE /api/v1/inventory/{id}`
- `PATCH /api/v1/inventory/{id}` (update keterangan; qty increase = tambah unit)
- `POST /api/v1/inventory/{id}/units` (tambah unit/expand qty)
- `PATCH /api/v1/inventory/{id}/units/{unitId}/condition` (ubah kondisi)
- `GET /api/v1/inventory/dashboard` (KPI + statistik)

### Keamanan API
- Semua route `/api/v1/*` dilindungi Sanctum + permission.
- Rate limiting.
- Backend validation semua input.

---

## 10. Dashboard (Core)

- Halaman utama setelah login (role-aware).
- Ringkasan umum sistem: jumlah user, jumlah role; untuk Super Admin; link ke modul.
- Dashboard Inventaris terpisah (`/inventory` atau `/inventory/dashboard`) — lihat §8.

---

## 11. Non-Functional Requirements

- Pagination + indexed query untuk list besar.
- DB transaction untuk operasi item+units.
- Unique constraint register per kode barang; FK integrity; no orphan.
- Automated test: register sequence, qty increase/decrease, immutable, kondisi, delete, dashboard calculation, auth, multi-role, forbidden action, API unauthorized/forbidden.
- TypeScript & lint clean; build production sukses.

---

## 12. Definition of Done (Phase 1)

- [x] Core: auth Fortify, user management, multi-role, permission, authorization.
- [x] Inventaris: dashboard, CRUD, register otomatis & incremental, qty increase-only, kondisi per unit, immutable fields, delete permanen, search/filter/pagination.
- [x] API: `/api/v1` auth/users/roles/inventory + dashboard, authorization, business rules.
- [x] Deployment: Docker + PostgreSQL + Redis, siap Coolify; env production terdokumentasi.
- [x] Quality: `composer test` (Pint+PHPStan+PHPUnit) dan `npm run types:check` + `npm run build` lulus.
