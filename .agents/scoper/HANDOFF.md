# HANDOFF — Sistem Informasi & Administrasi Sekolah (Phase 1 MVP)

**Cycle:** 1 — Initial Planning (greenfield)
**Tanggal:** 2026-08-20
**Scope:** Core + Inventaris + REST API `/api/v1`

---

## Current Phase

Phase 1 — Core Foundation (baru mulai; belum ada implementasi).

## Ready Tickets

Urutan eksekusi yang disarankan (dependency-aware):

1. **TASK-001** — Setup PostgreSQL + Redis + env production (P0, blocker)
2. **TASK-002** — Install & konfigurasi Sanctum + Spatie Permission (P0)
3. **TASK-003** — Role & permission seeder awal (P0)
4. **TASK-004** — App shell: hapus demo pages, navigasi modul, role-aware menu (P1)
5. **TASK-005** — User Management web (P0)
6. **TASK-006** — Role & Permission Management web (P1)
7. **TASK-007** — Core Dashboard (P2)
8. **TASK-008** — Inventory schema (P0)
9. **TASK-009** — Register generator service + create inventory (P0)
10. **TASK-010** — Inventory list: search/filter/pagination (P0)
11. **TASK-011** — Inventory dashboard (P1)
12. **TASK-012** — Inventory detail: units, kondisi, tambah unit, delete, update keterangan (P0)
13. **TASK-013** — API auth + users + roles (P0)
14. **TASK-014** — API inventory + dashboard (P0)
15. **TASK-015** — Automated tests business rules + RBAC (P0)
16. **TASK-016** — Docker production + Coolify (P1)
17. **TASK-017** — Final integration & QA (P0)

## Blocked Tickets

- None (semua Todo; dependency sudah diekspresikan di TICKETS.md).

## Critical Risks

1. **R-001 Register duplicate/race** — mitiga: transaction + unique constraint + test.
2. **R-002 Immutable fields bocor** — mitiga: guard backend + test.
3. **R-003 Qty vs unit inkonsisten** — mitiga: jumlah unit dari data unit, tolak decrease.
4. **R-005 Drift SQLite↔PostgreSQL** — mitiga: default dev PostgreSQL via Docker.
5. **R-009 Open Questions PRD belum dikonfirmasi** — asumsi D-009 (lokasi/foto/export OUT). Jangan bangun di Phase 1; konfirmasi user sebelum cycle 2.

## Important Notes

- **Foundation dipertahankan**: komponen & struktur `laravel-shadcn-admin-dashboard` (Fortify, dashboard shell, TanStack Table, Recharts, shadcn/ui) dipakai, bukan rewrite. Halaman demo (chat/mail/auth v1/v2) boleh dihapus (TASK-004).
- **Laravel = source of truth**: register, qty validation, permission, total, immutable, kondisi — semua backend.
- **PostgreSQL primary**; SQLite hanya fallback (D-001). Base project default SQLite — TASK-001 harus mengubah default.
- **Frontend bukan authorization**: menu disembunyikan ≠ aman; enforcement backend (403).
- **Delete permanen** tanpa soft delete (D-008); UI wajib konfirmasi.
- **Multi-role**: satu user banyak role; permission via role (D-003).
- **Halaman role/permission & user** hanya untuk Super Admin.
- **Quality gate**: `composer test` (Pint + PHPStan + PHPUnit), `npm run types:check`, `npm run lint:check`, `npm run build` harus lulus sebelum Done.
- **Dokumentasi append-only**: jangan rewrite artifact `.agents/scoper/` antar cycle; append section baru.

## Handoff Checklist (untuk Builder)

- [ ] Baca PROJECT.md, SPEC.md, TICKETS.md, DECISIONS.md, RISKS.md sebelum mulai.
- [ ] Mulai dari TASK-001; selesaikan satu ticket per sesi; update Status di TICKETS.md.
- [ ] Jangan ubah acceptance criteria / dependency / phase.
- [ ] Jika blocker, set Status: Blocked + catat alasan, stop.
- [ ] Verifikasi quality gate sebelum menandai Done.
