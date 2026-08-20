# Product Requirements Document
## Sistem Informasi & Administrasi Sekolah

**Status:** Draft  
**Fase:** Phase 1 — MVP Inventaris  
**Arsitektur:** Laravel Modular Monolith + Inertia + REST API  
**Foundation Repository:** `gilangprtm/laravel-shadcn-admin-dashboard`

---

# 1. Ringkasan Produk

Sistem Informasi & Administrasi Sekolah adalah platform terintegrasi untuk mengelola data dan administrasi sekolah dalam satu sistem.

Platform dirancang sebagai **modular monolith** yang dapat dikembangkan bertahap tanpa harus membangun aplikasi backend terpisah untuk setiap modul.

MVP berfokus pada **Modul Inventaris**, tetapi arsitektur dan PRD dirancang untuk mendukung pengembangan:

- Guru
- Siswa
- Perpustakaan
- Kurikulum
- Kesiswaan
- Sarana & Prasarana
- Administrasi sekolah
- PWA Siswa
- Modul lain yang dibutuhkan sekolah

Sistem memiliki satu backend Laravel sebagai pusat business logic dan data.

---

# 2. Product Vision

Membangun platform digital sekolah yang menjadi pusat data dan administrasi sekolah dengan karakteristik:

- Terintegrasi.
- Modular.
- Aman.
- Mudah dikembangkan.
- Memiliki satu sumber data.
- Mendukung multi-role.
- Memiliki REST API.
- Dapat digunakan oleh berbagai client.
- Dapat berkembang sesuai kebutuhan sekolah.

---

# 3. Problem Statement

Pengelolaan administrasi sekolah sering tersebar pada spreadsheet, dokumen, aplikasi berbeda, dan proses manual.

Masalah yang ingin diselesaikan:

- Data tidak terpusat.
- Pengelolaan akun tidak terintegrasi.
- Hak akses sulit dikelola.
- Data inventaris sulit dipantau.
- Rekap aset membutuhkan pekerjaan manual.
- Data yang sama dapat muncul di banyak sistem.
- Belum tersedia backend API yang dapat digunakan oleh aplikasi siswa.
- Penambahan fitur baru berpotensi menghasilkan sistem yang terfragmentasi.

Sistem ini akan menjadi fondasi digital yang dapat berkembang menjadi platform administrasi sekolah terpadu.

---

# 4. Target Users

## 4.1 Super Admin

Mengelola sistem secara keseluruhan.

Hak utama:

- Mengelola akun pengguna.
- Mengelola role.
- Mengelola permission.
- Mengakses seluruh modul.
- Mengakses seluruh data sesuai sistem.

## 4.2 Admin Inventaris

Mengelola seluruh inventaris sekolah.

Hak utama:

- Dashboard inventaris.
- Membuat inventaris.
- Melihat inventaris.
- Menghapus inventaris.
- Menambah unit.
- Mengubah kondisi unit.
- Melihat nilai aset.
- Melihat statistik inventaris.

Admin Inventaris memiliki akses ke seluruh inventaris sekolah.

Tidak ada pembatasan berdasarkan:

- Ruangan.
- Laboratorium.
- Perpustakaan.
- Unit kerja.
- Lokasi.

Lokasi, jika ditambahkan di masa depan, merupakan atribut data dan bukan authorization boundary.

## 4.3 Guru

Role untuk pengguna guru.

Guru dapat memiliki role tambahan.

Contoh:

```text
Guru
+
Admin Inventaris
```

## 4.4 Admin Perpustakaan

Role yang disiapkan untuk modul Perpustakaan.

Belum menjadi fokus Phase 1.

## 4.5 Siswa

Role untuk siswa.

Siswa menggunakan akun dari tabel `users` yang sama.

Pada masa depan siswa akan menggunakan PWA.

---

# 5. Multi-Role

Satu akun dapat memiliki banyak role.

Contoh:

```text
User
├── Guru
└── Admin Inventaris
```

Authorization:

```text
User
  ↓
Role
  ↓
Permission
```

Permission tidak diberikan langsung kepada user.

User mendapatkan seluruh permission dari role yang dimilikinya.

---

# 6. Authentication Architecture

Satu tabel `users` digunakan oleh seluruh pengguna.

Tidak ada tabel login terpisah.

## Web Admin

Authentication menggunakan **Laravel Fortify**.

```text
React + Inertia
        ↓
Laravel Fortify
        ↓
users
```

Fortify menangani kebutuhan authentication aplikasi web seperti login, logout, password, dan mekanisme authentication Laravel yang relevan.

## REST API

REST API menggunakan **Laravel Sanctum**.

```text
PWA
 ↓
Sanctum
 ↓
Laravel API
```

Web admin dan PWA tetap menggunakan sumber akun yang sama.

---

# 7. Technology Stack

## Backend

- Laravel 13
- PHP
- Laravel Fortify
- Laravel Sanctum
- Spatie Permission
- REST API
- API versioning

## Admin Frontend

- React 19
- Inertia 3
- TypeScript
- Tailwind CSS 4
- shadcn/ui

Foundation menggunakan repository:

`laravel-shadcn-admin-dashboard`

## Database

- PostgreSQL

## Cache / Queue

- Redis

## Infrastructure

- Docker
- Coolify
- VPS

---

# 8. Application Architecture

Sistem menggunakan Laravel sebagai application core.

```text
                         Laravel 13
                              │
             ┌────────────────┼────────────────┐
             │                │                │
             ▼                ▼                ▼
         Web Layer         API Layer       Business Logic
             │                │                │
             ▼                ▼                │
        Inertia + React    REST API           │
             │                │                │
             └────────────────┼────────────────┘
                              │
                    ┌─────────┴─────────┐
                    ▼                   ▼
               PostgreSQL             Redis
```

Laravel menangani:

- Authentication.
- Authorization.
- Validation.
- Business logic.
- Database.
- API.
- Web application.
- Queue.
- Cache.

---

# 9. Client Architecture

## Admin Web

Admin menggunakan:

```text
Laravel
+
Inertia
+
React
+
shadcn/ui
```

Admin tidak membutuhkan Next.js.

Inertia menjadi penghubung antara Laravel dan React.

## PWA Siswa

PWA dikembangkan sebagai client terpisah pada fase berikutnya.

```text
PWA Siswa
     ↓
REST API
     ↓
Laravel
```

PWA tidak mengakses database secara langsung.

---

# 10. REST API Strategy

REST API tetap menjadi bagian penting dari arsitektur walaupun Admin menggunakan Inertia.

Tujuan API:

1. PWA Siswa.
2. Mobile application masa depan.
3. Client eksternal.
4. Integrasi sistem.
5. Pengembangan aplikasi lain tanpa mengubah backend utama.

API menggunakan:

```text
/api/v1
```

Contoh:

```text
/api/v1/auth/*
/api/v1/users/*
/api/v1/roles/*
/api/v1/inventory/*
```

Web Admin tidak diwajibkan menggunakan REST API untuk setiap halaman karena dapat menggunakan Inertia secara langsung.

---

# 11. Product Module Map

```text
Sistem Informasi & Administrasi Sekolah
│
├── Core
│   ├── Authentication
│   ├── Users
│   ├── Roles
│   ├── Permissions
│   ├── Dashboard
│   ├── Notifications
│   └── Settings
│
├── Inventaris
│
├── Guru
│
├── Siswa
│
├── Perpustakaan
│
├── Kurikulum
│
├── Kesiswaan
│
├── Sarana & Prasarana
│
├── Administrasi
│
└── PWA Siswa
```

---

# 12. Development Phases

## Phase 1 — MVP

Core + Inventaris.

### Core

- Authentication.
- User management.
- Role.
- Permission.
- Dashboard.

### Inventaris

- Inventory dashboard.
- Inventory CRUD.
- Register.
- Inventory unit.
- Condition management.
- Search.
- Filter.
- Pagination.

### API

- Authentication API.
- User API.
- Role API.
- Inventory API.
- Inventory dashboard API.

---

## Phase 2 — Guru

Potensi:

- Profil guru.
- Data kepegawaian.
- Mata pelajaran.
- Jadwal.
- Data mengajar.
- Dokumen.

---

## Phase 3 — Siswa + PWA

Potensi:

- Profil siswa.
- Kelas.
- Kehadiran.
- Prestasi.
- Ekstrakurikuler.
- Dokumen.
- Layanan siswa.

PWA menggunakan REST API.

---

## Phase 4 — Perpustakaan

Potensi:

- Koleksi.
- Kategori.
- Anggota.
- Peminjaman.
- Pengembalian.
- Denda.
- Laporan.

---

## Phase 5 — Kurikulum & Kesiswaan

Potensi:

- Kurikulum.
- Jadwal.
- Pembagian tugas.
- Kehadiran.
- Pelanggaran.
- Prestasi.
- Ekstrakurikuler.

---

## Phase 6 — Sarana & Administrasi

Potensi:

- Ruangan.
- Fasilitas.
- Pemeliharaan.
- Surat masuk.
- Surat keluar.
- Arsip.
- Dokumen sekolah.

---

# 13. MVP Inventory

Modul Inventaris merupakan sistem **asset registry** sekolah.

MVP bukan sistem stock warehouse.

Tujuan utama:

- Mendata aset.
- Memberikan register pada setiap unit.
- Mengetahui kondisi aset.
- Mengetahui nilai aset.
- Menampilkan statistik aset.
- Memudahkan pencarian dan administrasi aset.

---

# 14. Inventory Data

Field utama:

| No | Field |
|---:|---|
| 1 | Urut |
| 2 | Kode Barang |
| 3 | Register |
| 4 | Nama/Jenis Barang |
| 5 | Merk/Type |
| 6 | No Sertifikat/No Pabrik/No Chasis/No Mesin |
| 7 | Bahan |
| 8 | Asal/Cara Perolehan Barang |
| 9 | Tahun Pembelian |
| 10 | Ukuran/Konstruksi (P,S,D) |
| 11 | Satuan |
| 12 | Keadaan Barang |
| 13 | Qty |
| 14 | Harga |
| 15 | Keterangan |

---

# 15. Inventory Domain Model

Konsep:

```text
Inventory Item
│
├── Kode Barang
├── Nama/Jenis
├── Merk/Type
├── Identitas
├── Bahan
├── Asal
├── Tahun
├── Ukuran
├── Satuan
├── Harga
├── Keterangan
│
└── Inventory Units
      ├── Register 001
      ├── Register 002
      ├── Register 003
      └── ...
```

Inventory Item adalah kelompok barang.

Inventory Unit adalah unit fisik yang memiliki register.

---

# 16. Kode Barang

Kode Barang diinput secara manual.

MVP tidak menentukan format kode.

Tidak ada generator kode.

Tidak ada auto-numbering Kode Barang.

Kode Barang bersifat immutable.

Setelah disimpan:

```text
Kode Barang
    ↓
Tidak dapat diedit
```

Jika salah:

```text
Delete
 ↓
Create ulang
```

Format otomatis dapat dikembangkan pada fase berikutnya.

---

# 17. Register

Register dibuat otomatis oleh backend.

Format:

```text
001
002
003
...
```

Register incremental berdasarkan Kode Barang.

Contoh:

```text
Kode A.01.01
Qty 3

001
002
003
```

Kode berbeda:

```text
Kode B.01.01
Qty 2

001
002
```

Sequence register dimulai kembali dari 001 untuk setiap Kode Barang.

Frontend tidak boleh menentukan register.

Laravel bertanggung jawab membuat register.

---

# 18. Quantity Rules

Qty menunjukkan jumlah unit.

Jika:

```text
Qty = 5
```

maka:

```text
001
002
003
004
005
```

Qty tidak boleh berkurang.

```text
5 → 4
```

ditolak.

Qty dapat bertambah.

```text
5 → 8
```

menghasilkan:

```text
006
007
008
```

Tidak ada pengurangan unit karena aset tidak dihapus/musnahkan dalam sistem MVP.

---

# 19. Condition Rules

Kondisi disimpan pada setiap unit/register.

Nilai:

```text
B  = Baik
KB = Kurang Baik
RB = Rusak Berat
```

Contoh:

```text
001 → B
002 → B
003 → KB
004 → RB
005 → B
```

Qty tetap 5.

Barang Rusak Berat tetap tercatat.

---

# 20. Price Rules

Harga adalah harga per unit.

Contoh:

```text
Qty = 5
Harga = Rp2.000.000

Total = Rp10.000.000
```

Total dihitung:

```text
Total = Qty × Harga
```

Total bukan input manual.

---

# 21. Purchase Year

Jika tahun pembelian berbeda, dianggap sebagai kelompok inventaris baru.

Contoh:

```text
Laptop
2025
Kode A.01.01
```

berbeda dari:

```text
Laptop
2026
Kode A.01.02
```

Walaupun nama barang sama.

---

# 22. Inventory Update Rules

Data utama tidak dapat diedit setelah dibuat.

Immutable:

- Kode Barang.
- Nama/Jenis Barang.
- Merk/Type.
- Nomor identifikasi.
- Bahan.
- Asal/Cara Perolehan.
- Tahun Pembelian.
- Ukuran/Konstruksi.
- Satuan.
- Harga.

Dapat diperbarui:

- Kondisi register.
- Keterangan sesuai kebutuhan.

---

# 23. Inventory Delete

Delete bersifat permanent.

Tidak menggunakan:

- Soft delete.
- Restore.
- Delete history.

Delete digunakan sebagai mekanisme koreksi apabila terjadi kesalahan input.

Contoh:

```text
Data salah
   ↓
Delete
   ↓
Input ulang
```

Operasi delete harus memiliki confirmation pada UI.

---

# 24. Inventory Dashboard

Dashboard Inventaris menampilkan informasi penting secara cepat.

KPI minimal:

- Total aset.
- Total nilai aset.
- Aset Baik.
- Aset Kurang Baik.
- Aset Rusak Berat.
- Total kelompok inventaris.

Statistik tambahan:

- Aset berdasarkan tahun.
- Aset berdasarkan asal/cara perolehan.
- Distribusi kondisi.

Dashboard menghitung berdasarkan unit/register.

---

# 25. Dashboard Calculation

Contoh:

```text
001 → B
002 → B
003 → B
004 → KB
005 → RB
```

Dashboard:

```text
Total       = 5
Baik        = 3
Kurang Baik = 1
Rusak Berat = 1
```

Total nilai:

```text
SUM(Qty × Harga)
```

Perhitungan dilakukan di backend.

---

# 26. Inventory Search & Filter

Daftar inventaris mendukung:

### Search

- Kode Barang.
- Nama/Jenis.
- Merk/Type.
- Register.

### Filter

- Tahun Pembelian.
- Kondisi.
- Asal/Cara Perolehan.
- Satuan.

### Pagination

Pagination wajib digunakan untuk dataset besar.

---

# 27. API Inventory

Endpoint utama:

```text
GET    /api/v1/inventory
POST   /api/v1/inventory
GET    /api/v1/inventory/{id}
DELETE /api/v1/inventory/{id}
```

Dashboard:

```text
GET /api/v1/inventory/dashboard
```

Unit:

```text
POST /api/v1/inventory/{id}/units
PATCH /api/v1/inventory/{id}/units/{unitId}/condition
```

Endpoint final ditentukan pada API specification.

---

# 28. API Business Rules

Laravel adalah sumber kebenaran.

Frontend tidak menentukan:

- Register.
- Qty validation.
- Permission.
- Total aset.
- Total nilai.
- Validitas perubahan immutable.
- Validitas kondisi.

Request:

```text
Client
 ↓
Laravel
 ↓
Authentication
 ↓
Authorization
 ↓
Validation
 ↓
Business Logic
 ↓
Database
```

---

# 29. API Response

Format response dibuat konsisten.

Success:

```json
{
  "success": true,
  "message": "Data berhasil diproses",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Data tidak dapat diproses",
  "errors": {}
}
```

Format final ditentukan pada technical specification.

---

# 30. Role & Permission

Role awal:

```text
Super Admin
Admin Inventaris
Guru
Admin Perpustakaan
Siswa
```

Permission diberikan kepada role.

Contoh:

```text
inventory.view
inventory.create
inventory.delete
inventory.dashboard.view
inventory.unit.create
inventory.unit.condition.update
```

Satu user dapat memiliki banyak role.

---

# 31. Super Admin

Super Admin memiliki akses penuh terhadap sistem.

Super Admin dapat:

- Mengelola user.
- Mengelola role.
- Mengelola permission.
- Mengakses seluruh modul.

Super Admin tidak perlu dibatasi oleh permission normal ketika sistem mendefinisikan role tersebut sebagai administrator penuh.

---

# 32. Future Module — Guru

Modul Guru akan menggunakan sistem user yang sama.

Konsep:

```text
users
   │
   └── teacher profile
```

Detail implementasi ditentukan pada phase Guru.

---

# 33. Future Module — Siswa

Konsep:

```text
users
   │
   └── student profile
```

Siswa tetap menggunakan akun dari `users`.

PWA menggunakan REST API.

---

# 34. Future Module — Perpustakaan

Role:

```text
Admin Perpustakaan
```

Potensi domain:

```text
Library
├── Books
├── Categories
├── Members
├── Loans
├── Returns
└── Reports
```

---

# 35. Future Module — Sarana & Prasarana

Dapat menggunakan data inventaris sebagai salah satu sumber aset.

Potensi:

- Ruangan.
- Fasilitas.
- Pemeliharaan.
- Kondisi sarana.
- Penanggung jawab.

---

# 36. Future PWA

PWA bukan aplikasi terpisah dari backend.

```text
PWA
 ↓
Laravel API
 ↓
PostgreSQL
```

API harus dirancang sejak awal agar dapat digunakan PWA tanpa perlu membongkar backend.

---

# 37. Database

PostgreSQL digunakan sebagai primary database.

MVP secara konseptual memiliki:

```text
users

roles
permissions
model_has_roles
role_has_permissions

inventory_items
inventory_units
```

Struktur database final dibuat pada technical specification.

---

# 38. Database Integrity

Database harus mencegah:

- Register duplicate.
- Data orphan.
- Kondisi invalid.
- Foreign key invalid.
- Quantity inconsistency.

Operasi yang membuat Inventory Item dan Inventory Units harus menggunakan database transaction.

---

# 39. Redis

Redis digunakan untuk:

- Cache.
- Queue.
- Background job.
- Optimasi proses yang membutuhkan asynchronous processing.

Redis bukan primary data store.

---

# 40. File Storage

Future modules kemungkinan membutuhkan:

- Foto aset.
- Dokumen guru.
- Dokumen siswa.
- Dokumen sekolah.
- Lampiran administrasi.

Strategi storage akan ditentukan ketika modul tersebut mulai dikembangkan.

---

# 41. Security

Minimum security requirements:

- Fortify untuk web authentication.
- Sanctum untuk API.
- Spatie Permission untuk authorization.
- Backend validation.
- Protected API routes.
- Policy/permission enforcement.
- Password hashing Laravel.
- HTTPS production.
- Environment secrets.
- Database tidak diekspos publik.
- CORS sesuai kebutuhan client.
- Rate limiting API sesuai kebutuhan.

---

# 42. Frontend Security

Frontend tidak boleh menjadi sumber authorization.

Contoh:

```text
Menu Inventory disembunyikan
```

bukan berarti user aman.

Laravel tetap harus menolak:

```text
User tanpa inventory.delete
        ↓
DELETE /api/v1/inventory/123
        ↓
403 Forbidden
```

---

# 43. Deployment

Deployment menggunakan Coolify pada VPS.

Resource:

```text
Coolify
│
├── School Application
│   └── Laravel + Inertia + React
│
├── PostgreSQL
│
└── Redis
```

Reverse proxy dan HTTPS dikelola melalui infrastructure Coolify.

---

# 44. Docker

Aplikasi menggunakan Docker.

Target production:

```text
Laravel Application
PostgreSQL
Redis
```

Repository foundation sudah memiliki Docker setup yang akan menjadi basis deployment.

Konfigurasi production harus menggunakan environment variables.

---

# 45. Repository Foundation

Repository:

```text
laravel-shadcn-admin-dashboard
```

digunakan sebagai foundation.

Pengembangan harus mempertahankan komponen dan struktur yang sudah tersedia selama masih sesuai dengan kebutuhan produk.

Tidak melakukan rewrite framework tanpa alasan teknis yang kuat.

---

# 46. Modular Monolith

Sistem tidak menggunakan microservices pada fase awal.

Modul dikelola dalam satu Laravel application.

Konsep:

```text
Laravel Application
│
├── Core
├── Inventory
├── Teacher
├── Student
├── Library
├── Curriculum
└── ...
```

Modularitas berada pada level domain dan code organization.

REST API menjadi interface untuk external clients.

---

# 47. Non-Functional Requirements

## Performance

- Pagination.
- Indexed query.
- Efficient dashboard query.
- Redis cache jika diperlukan.
- Queue untuk proses berat.

## Reliability

- Database transaction.
- Validation.
- Foreign keys.
- Unique constraints.
- Automated tests.

## Maintainability

- TypeScript.
- Laravel conventions.
- Consistent API naming.
- Domain-oriented organization.
- Reusable UI components.

## Extensibility

Penambahan modul tidak boleh membutuhkan perubahan fundamental pada:

- Authentication.
- User model.
- Role/Permission.
- API architecture.
- Deployment architecture.

---

# 48. Testing Strategy

Testing wajib mencakup business rules penting.

Minimum:

### Authentication

- Login.
- Logout.
- Unauthorized access.

### Authorization

- Role.
- Multiple role.
- Permission.
- Forbidden action.

### Inventory

- Create inventory.
- Generate register.
- Register sequence.
- Increase Qty.
- Reject decrease Qty.
- Condition change.
- Immutable field.
- Delete.
- Dashboard calculation.

Business rules register dan quantity harus memiliki automated test karena merupakan aturan inti inventaris.

---

# 49. Out of Scope Phase 1

Belum dibangun:

- Modul Guru lengkap.
- Modul Siswa lengkap.
- PWA Siswa.
- Perpustakaan.
- Kurikulum.
- Kesiswaan.
- Keuangan.
- Surat menyurat.
- Maintenance aset.
- Mutasi aset kompleks.
- Penghapusan/musnah aset.
- Generator Kode Barang.
- Delete history.
- Audit history penghapusan.
- Sistem notifikasi kompleks.
- Integrasi eksternal.
- Export PDF/Excel apabila belum masuk prioritas implementasi.

---

# 50. Open Questions

Requirement berikut masih terbuka:

1. Apakah inventaris membutuhkan lokasi/ruangan sebagai field?
2. Apakah inventaris membutuhkan foto aset?
3. Apakah perlu nomor dokumen perolehan?
4. Apakah export Excel wajib pada MVP?
5. Apakah export PDF wajib pada MVP?
6. Format laporan inventaris sekolah.
7. Strategi backup PostgreSQL di VPS.
8. Storage file production.
9. Detail profil Guru.
10. Detail profil Siswa.
11. Detail PWA.
12. Detail modul Perpustakaan.

Open Questions tidak boleh diisi berdasarkan asumsi Builder.

---

# 51. Product Principles

1. Satu akun untuk seluruh pengguna.
2. Multi-role.
3. Permission melalui role.
4. Laravel sebagai sumber kebenaran business logic.
5. PostgreSQL sebagai primary data store.
6. Inertia digunakan untuk Admin Web.
7. REST API digunakan untuk external clients/PWA.
8. Frontend tidak mengakses database.
9. Authorization selalu dilakukan backend.
10. Modul dikembangkan bertahap.
11. Tidak membangun future feature sebelum masuk scope.
12. Tidak menggunakan microservices tanpa kebutuhan.
13. Business rule kritis harus memiliki automated test.
14. Data inventaris penting bersifat immutable.

---

# 52. MVP Definition of Done

MVP dianggap selesai apabila:

## Core

- Authentication berjalan dengan Fortify.
- User management berjalan.
- Multi-role berjalan.
- Role & permission berjalan.
- Authorization berjalan.

## Inventory

- Dashboard tersedia.
- CRUD inventaris tersedia.
- Kode Barang manual.
- Register otomatis.
- Register incremental.
- Qty tidak dapat berkurang.
- Qty dapat bertambah.
- Kondisi per register dapat diubah.
- Immutable fields terlindungi.
- Delete permanen tersedia.
- Search tersedia.
- Filter tersedia.
- Pagination tersedia.
- Total nilai dihitung otomatis.

## API

- `/api/v1` tersedia.
- Authentication API tersedia sesuai kebutuhan Sanctum.
- Inventory API tersedia.
- Dashboard API tersedia.
- Authorization API berjalan.
- API business rules tervalidasi.

## Deployment

- Application berjalan di Docker.
- PostgreSQL berjalan.
- Redis berjalan.
- Deployment berhasil melalui Coolify.
- HTTPS aktif.
- Environment production terdokumentasi.

---

# 53. Next Step

Setelah PRD disetujui, proses dilanjutkan:

```text
PRD
 ↓
Technical Specification
 ↓
Database Schema
 ↓
API Contract
 ↓
Application Architecture
 ↓
UI / Information Architecture
 ↓
MVP Tickets
 ↓
Implementation
 ↓
Code Review
 ↓
Deployment
```

Phase 1 hanya mengimplementasikan:

```text
Core
├── Authentication
├── Users
├── Roles
├── Permissions
└── Dashboard

Inventory
├── Dashboard
├── Inventory
├── Register/Units
└── Conditions

API
├── Auth
├── Users
├── Roles
└── Inventory
```

Seluruh modul lainnya tetap menjadi bagian dari **product vision dan architecture**, tetapi tidak menjadi scope implementation Phase 1.