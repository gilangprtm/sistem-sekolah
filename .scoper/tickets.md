# Chatbot Sistem Sekolah — 9Router MVP

Status: **Rancangan implementasi — belum dimulai**

Base revision: `0684e00` (`feat: add shared searchable combobox`)

## Tujuan

Menyediakan assistant internal sederhana yang menjawab pertanyaan berbasis data aplikasi melalui 9Router, tanpa akses database langsung dari model dan tanpa menyimpan percakapan secara permanen.

## Keputusan MVP

- Provider dipanggil hanya dari backend Laravel; API key dan URL 9Router tidak pernah dikirim ke browser.
- Frontend menggunakan history sementara di memory React (Opsi A). Reload/reset/penutupan halaman memulai konteks baru.
- Mode awal request-response non-streaming. Streaming ditunda sampai kontrak SSE 9Router lulus.
- Read-only saja. Tidak ada create/update/delete/import/export melalui assistant.
- Model hanya dapat memilih tool yang dikirim backend untuk permission user saat ini.
- Tool tidak boleh menjalankan arbitrary SQL, PHP, HTTP, atau code dari model.
- Setiap tool melakukan authorization server-side dan validasi input ulang.
- Feature flag/kill switch wajib tersedia sebelum UI diaktifkan.

## Role → tool boundary

Backend membentuk daftar tool setiap request berdasarkan permission aktual user:

- Permission inventori → `inventory.summary`, `inventory.search`, `inventory.condition_summary`.
- Permission guru → tool guru yang kelak didefinisikan dalam modul guru.
- User dengan kedua permission → gabungan tool dari kedua modul.
- User tanpa permission modul → tidak menerima tool modul tersebut.

System prompt bukan security boundary. Nama tool yang tidak terdaftar, permission yang tidak sesuai, schema invalid, object di luar scope, dan parameter berbahaya harus ditolak.

## Fase 0 — Compatibility spike (bounded, wajib sebelum implementasi)

1. Tambahkan konfigurasi server-side tanpa secret:
   - `NINEROUTER_BASE_URL`
   - `NINEROUTER_API_KEY`
   - `NINEROUTER_MODEL`
   - connect/request timeout
   - streaming default `false`
2. Pin versi/instance 9Router pada environment disposable/staging.
3. Verifikasi `GET /v1/models` dan model ID aktual.
4. Verifikasi satu `POST /v1/chat/completions` non-streaming.
5. Catat contract: response content, finish reason, usage, error JSON, timeout, 401, 404, 429, dan 5xx.
6. Uji tool-call dan SSE secara terpisah; jangan mengaktifkannya berdasarkan asumsi dokumentasi.
7. Tambahkan `Http::fake()` contract tests; tidak ada API key nyata di test.
8. Hasilkan ADR dan keputusan go/no-go.

**Gate:** bila contract non-stream gagal atau model/provider belum disetujui, berhenti di Fase 0.

## Fase 1 — Gateway dan endpoint ephemeral

Target artefak:

- `config/services.php` entry 9Router dengan nilai dari env;
- server-side `NineRouterClient`/adapter dengan timeout dan bounded retry hanya untuk error yang terbukti aman;
- `POST /api/assistant/chat` authenticated;
- request validation: message size, history size, total context budget;
- feature flag dan kill switch;
- correlation ID;
- safe error response tanpa provider detail/secret.

Input minimal:

```json
{
  "message": "Berapa jumlah kursi?",
  "history": []
}
```

Tidak membuat tabel conversation/message. History hanya memory pada frontend dan dibatasi jumlah pesan/karakter.

## Fase 2 — Tool registry read-only

Buat registry server-side dengan metadata:

- `name`;
- JSON schema input;
- permission;
- risk level (`read`);
- handler;
- timeout;
- result cap/pagination;
- provenance formatter.

Tool inventory awal:

- `inventory.summary`;
- `inventory.search`;
- `inventory.condition_summary`.

Implementasi memakai query/model/service domain yang sudah ada, bukan SQL dari model. Hasil tool harus disanitasi, dibatasi, dan dikembalikan dengan identitas sumber/waktu bila sesuai.

Flow tool:

1. Laravel memilih tool berdasarkan permission user;
2. model mengembalikan text atau tool call;
3. backend mem-parse dan memvalidasi strict schema;
4. backend mengecek allowlist + permission + scope;
5. handler read-only dieksekusi;
6. hasil dibatasi lalu dikirim kembali ke model;
7. maksimum 2–3 putaran tool dan global timeout/budget;
8. tool invalid/unauthorized menghasilkan penolakan aman dan tidak blind retry.

## Fase 3 — Chat UI

- panel chat authenticated di React/Inertia;
- history hanya state memory;
- composer, loading, error, retry aman, reset chat;
- indikator bahwa jawaban berasal dari data aplikasi;
- tidak menampilkan API key, raw prompt internal, atau detail exception provider;
- reset menghapus state lokal dan memulai session baru.

## Fase 4 — Security, QA, dan operational hardening

Wajib diuji:

- role inventori hanya mendapat tool inventori;
- role guru hanya mendapat tool guru;
- kombinasi role mendapat gabungan yang benar;
- prompt tidak dapat menambahkan tool/permission;
- unknown tool dan malformed arguments ditolak;
- cross-user/cross-school data tidak bocor;
- inventory names/notes dianggap untrusted data, bukan instruksi;
- secret tidak muncul di response/log/browser;
- timeout/429/5xx menghasilkan error aman tanpa hasil karangan;
- authorization service failure fail-closed;
- rate limit, context cap, concurrency cap, dan kill switch;
- audit minimal untuk request, actor, tool decision, outcome, duration, correlation ID dengan redaction.

## Acceptance criteria

- [ ] Authenticated user dapat mengirim pertanyaan ephemeral.
- [ ] Reload/reset tidak memulihkan history lama.
- [ ] API key tidak pernah masuk browser, bundle, atau log.
- [ ] Minimal tiga tool inventory read-only tersedia sesuai permission.
- [ ] Tool tidak dapat dipanggil di luar allowlist atau permission user.
- [ ] Tidak ada arbitrary SQL/HTTP/code execution dari model.
- [ ] Hasil dibatasi, disanitasi, dan tidak membocorkan record di luar scope.
- [ ] Error provider, timeout, dan invalid tool menghasilkan respons aman.
- [ ] Audit/correlation data tersedia dengan redaction.
- [ ] Unit/integration/security tests lulus.
- [ ] Lint, typecheck, build, dan relevant PHP tests lulus.
- [ ] Contract test 9Router lulus pada staging/disposable environment.

## Out of scope

Persistent conversations, mutations, bulk operation, arbitrary SQL/HTTP/code, RAG/vector DB, file/vision/audio, web search/fetch, autonomous jobs, external channels, dan production rollout tidak termasuk MVP.

## Approval gates

Sebelum Fase 0: Owner menetapkan deployment boundary/data residency, provider/model/fallback, budget/latency, dan environment staging/disposable.

Sebelum Fase 1: contract spike lulus dan go/no-go disetujui.

Sebelum staging UAT: seluruh critical security test tidak boleh `FAIL` atau `UNKNOWN`.

Production deployment memerlukan keputusan dan approval terpisah; dokumen ini bukan otorisasi deployment.
