# RISKS — Sistem Informasi & Administrasi Sekolah

## Cycle 1 (2026-08-20) — Initial Planning

### R-001 — Register duplicate / race condition saat generate register
- **Dampak:** data inventaris korup (duplicate register).
- **Mitigasi:** generate dalam DB transaction + unique constraint `(kode_barang, register)` + automated test register sequence.

### R-002 — Immutable fields berhasil diubah lewat jalur lain (API/update)
- **Dampak:** aturan inti PRD §22 dilanggar.
- **Mitigasi:** guard di backend (update request hanya menerima field mutable), test immutable field.

### R-003 — Inkonsistensi qty vs jumlah unit
- **Dampak:** dashboard salah, register tidak sinkron.
- **Mitigasi:** jumlah unit dihitung dari data unit (bukan kolom redundan), validation qty decrease, transaksi, test.

### R-004 — Kebingungan Fortify (web) vs Sanctum (API)
- **Dampak:** auth bocor antar guard.
- **Mitigasi:** guard jelas — route web pakai Fortify session, route `/api/v1/*` pakai Sanctum token; dokumentasi + test unauthorized access.

### R-005 — Drift SQLite (base) vs PostgreSQL (PRD)
- **Dampak:** fitur jalan lokal tapi error production.
- **Mitigasi:** default dev memakai PostgreSQL via Docker compose; test dijalankan terhadap konfigurasi yang sama; `.env.example` menyediakan kedua opsi.

### R-006 — Penghapusan halaman demo (chat/mail/auth v1/v2) merusak build
- **Dampak:** tsc/build gagal, lint error.
- **Mitigasi:** hapus bersamaan dengan verifikasi `npm run types:check`, `npm run lint:check`, `npm run build`; komponen shared tidak ikut terhapus.

### R-007 — Super Admin bypass permission menjadi kompleks
- **Dampak:** akses tidak konsisten.
- **Mitigasi:** satu gate sederhana (`isSuperAdmin`), dipakai konsisten; test forbidden action untuk non-super admin.

### R-008 — Kinerja dashboard/list pada dataset besar
- **Dampak:** halaman lambat.
- **Mitigasi:** pagination wajib, indexed query (kode_barang, tahun, kondisi, asal, satuan), dashboard query efisien, Redis cache bila perlu.

### R-009 — Open Questions PRD belum dikonfirmasi user (lokasi, foto, export)
- **Dampak:** scope asumsi bisa salah; export Excel/PDF mungkin dibutuhkan segera.
- **Mitigasi:** dicatat sebagai asumsi (D-009); jangan dibangun di Phase 1; konfirmasi user sebelum cycle 2; bila wajib, jadi cycle tersendiri.

### R-010 — Redis tidak tersedia saat deployment
- **Dampak:** cache/queue gagal.
- **Mitigasi:** compose production menyertakan Redis; fallback driver terdokumentasi di README/env docs.

### R-011 — Backdoor permission via role management UI
- **Dampak:** user biasa mengubah permission sendiri.
- **Mitigasi:** role/permission management hanya untuk Super Admin; enforcement backend + test.

### R-012 — API tanpa auth/authorization
- **Dampak:** data inventaris bocor via `/api/v1`.
- **Mitigasi:** semua route `/api/v1/*` dilindungi Sanctum + middleware permission; test unauthorized/forbidden.
