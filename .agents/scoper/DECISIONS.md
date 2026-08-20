# DECISIONS — Sistem Informasi & Administrasi Sekolah

## Cycle 1 (2026-08-20) — Initial Planning

### D-001 — PostgreSQL sebagai primary database
- **Konteks:** base project default SQLite (session/queue/cache database driver).
- **Keputusan:** PostgreSQL dipakai sebagai primary DB (sesuai PRD §37). Local dev memakai PostgreSQL via Docker compose agar parity dengan production. SQLite tetap tersedia sebagai fallback, bukan default.
- **Rationale:** PRD menetapkan PostgreSQL; menghindari drift SQLite↔Postgres.

### D-002 — Fortify untuk web, Sanctum untuk API
- **Keputusan:** Web admin pakai Fortify (sudah ada di base). REST API `/api/v1` pakai Sanctum token. Satu tabel `users`.
- **Rationale:** PRD §6.

### D-003 — Spatie Permission untuk RBAC multi-role
- **Keputusan:** Role ↔ Permission via Spatie (`model_has_roles`, `role_has_permissions`). Permission tidak langsung ke user. Super Admin didefinisikan sebagai role dengan bypass permission normal (gate `isSuperAdmin`).
- **Rationale:** PRD §5, §30, §31.

### D-004 — Domain inventaris dua tingkat: InventoryItem + InventoryUnit
- **Keputusan:** `inventory_items` (kelompok barang) dan `inventory_units` (unit fisik ber-register). Kondisi & register melekat di unit.
- **Rationale:** PRD §15, §17, §19. Menghindari duplicate register per kode.

### D-005 — Register digenerate backend, dijamin unik per Kode Barang
- **Keputusan:** Service backend membuat register `001..N` dalam satu transaction saat create/expand qty. Unique constraint `(kode_barang, register)` di DB. Frontend tidak pernah mengirim register.
- **Rationale:** PRD §17, §28, §38.

### D-006 — Qty increase-only, decrease ditolak
- **Keputusan:** Backend menolak penurunan qty (HTTP 422). Kenaikan qty membuat unit baru melanjutkan nomor register terakhir.
- **Rationale:** PRD §18 (aset tidak dihapus/musnahkan di MVP).

### D-007 — Immutable fields dijaga di backend
- **Keputusan:** 10 field data utama (kode, nama, merk, identitas, bahan, asal, tahun, ukuran, satuan, harga) tidak dapat diubah setelah dibuat; update request yang mencoba mengubahnya ditolak/diabaikan. Yang mutable: kondisi unit + keterangan.
- **Rationale:** PRD §22. Koreksi = delete permanen + input ulang (D-008).

### D-008 — Delete permanen (hard delete) dengan konfirmasi
- **Keputusan:** Tanpa soft delete/restore/history. UI wajib konfirmasi sebelum delete.
- **Rationale:** PRD §23.

### D-009 — Default asumsi untuk Open Questions PRD (perlu konfirmasi user di cycle berikutnya)
- **Keputusan (asumsi, bukan fakta):**
  - Lokasi/ruangan: **OUT** MVP (atribut data masa depan, bukan authorization boundary — PRD §4.2).
  - Foto aset: **OUT** MVP (strategi storage PRD §40).
  - Nomor dokumen perolehan: **OUT** (tidak ada di daftar 15 field §14).
  - Export Excel/PDF: **OUT** MVP (open question §50 #4–5; PRD §49 mengecualikan bila belum prioritas). Kandidat cycle 2.
  - Format laporan: ditentukan saat export masuk scope.
- **Rationale:** tidak mengisi open questions dengan asumsi Builder; dicatat sebagai risiko R-009.

### D-010 — Hapus halaman demo yang tidak sesuai produk
- **Keputusan:** Halaman demo `chat`, `mail`, dan auth showcase `auth/v1`, `auth/v2` dihapus dari shell aplikasi agar fokus. Komponen UI yang reusable tetap dipertahankan.
- **Rationale:** foundation dipertahankan selama sesuai kebutuhan produk (PRD §45); halaman demo bukan bagian produk.

### D-011 — API envelope konsisten + rate limiting
- **Keputusan:** Response format `{success, message, data}` / `{success, false, message, errors}`; prefix `/api/v1`; rate limiting API.
- **Rationale:** PRD §29, §41.

### D-012 — Migration database menggunakan transaksi untuk item+units
- **Keputusan:** Semua operasi yang membuat/mengubah `inventory_items` + `inventory_units` dibungkus database transaction.
- **Rationale:** PRD §38 (cegah register duplicate, orphan, qty inconsistency).
