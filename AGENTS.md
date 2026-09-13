# AGENTS.md

SUKIRMAN — Sistem Usulan Kebutuhan Barang Internal Manajemen.
Laravel 13 + Inertia.js 3 + Vue 3 + Vite 8 + Tailwind 4 + Varlet UI.
PHP `^8.3`. Auth: Sanctum (API token) + Spatie Permission (role).

## Bahasa koordinasi

Koordinasi dengan user wajib pakai **bahasa Indonesia yang jelas** — kalimat lengkap, baku, ramah. Jangan pakai caveman style, jangan Inggris/Malay mix kriptik, jangan jawaban terse ambigu. Kode, nama file, nama route, error string tetap asli (English/as-is). Bahasa Indonesia untuk penjelas, bukan untuk kode.

## Ringkasan Cepat (supaya tidak bingung)

- **99 route** total — `routes/web.php` (Inertia/web) + `routes/api.php` (Sanctum API).
  Tinjau via `php artisan route:list` atau MCP `laravel-sukirman_list-routes-tool`.
- **MinIO** sebagai penyimpanan foto item (bukan `storage/app/public`) — lihat section
  "MinIO (foto item)" di bawah.
- **Pengambilan keputusan MR** terpusat di satu controller besar:
  `MaterialRequestController` (web + API duplikat logika).
- **3 role departemen (MTC/IT/HRD)** dibuat via migration `add_jenis_dan_role_departemen`,
  bukan via seeder. Seeder hanya bikin: Supervisor, Manager, FM/GM, Direksi, Gudang,
  Purchasing, admin + permission `teruskan-ke-direksi`.
- **Notifikasi bell** pakai `MrNotification` **tanpa queue** (awalnya pakai `Queueable`,
  bell jadi 0 terus — jangan dipasang lagi).

## Setup & commands

```bash
composer setup        # install + .env + key:generate + migrate + npm + build
composer dev          # concurrently: artisan serve + queue:listen + pail + vite
composer test         # config:clear + php artisan test
npm run dev           # vite dev server only
npm run build         # vite production build
vendor/bin/pint       # formatter (Laravel Pint)
```

- **App di-serve via Apache** (`D:\Apache24\htdocs\sukirman`), bukan `php artisan serve`.
  `APP_URL=http://localhost/sukirman/public` (lihat `.env`).
- Dev DB: **MariaDB** (`sukirman`). Test DB: SQLite `:memory:` (`phpunit.xml`).
- `.env` belum ada APP_KEY yang valid → jalankan `php artisan key:generate` setelah `composer setup` jika `.env` baru.
- `.npmrc` pakai `ignore-scripts=true` (jangan ubah, alasan security).

## Architecture

- **Frontend**: `resources/js/app.ts` (TypeScript) + `resources/js/Pages/` (Inertia pages).
  Alias TS: `@/*` → `resources/js/*` (`tsconfig.json`). `AppInit.vue` di-render sebagai
  **sibling root** (di `app.ts` render), bukan di dalam `App`.
- **Backend jantung**: `app/Http/Controllers/MaterialRequestController.php` —
  satu controller besar yang handle web + API MR (list, store, show, decision, revision,
  gudang verify, purchasing XML export, monitoring, statistik). Method dibedakan per role
  (`*ByManager/ByGM/ByDireksi/ByGudang/ByPurchasing`) — duplikasi dengan route web.
- **Admin**: `app/Http/Controllers/Admin/` (`AdminOverviewController`, `AdminUserController`)
  + `app/Http/Controllers/Master/DepartemenController` + `app/Http/Controllers/BarangController`
  (CRUD barang) + `SettingController` (konfigurasi aplikasi) + `ProfileController`.
- **MCP server internal**: `app/Mcp/`, di-expose lewat `php artisan mcp:start sukirman`.
- **Notifications**: `app/Notifications/` (`MrNotification` tanpa queue + `FcmChannel`)
  — bell icon di bottom nav + push FCM.
- **Auth pages**: `app/Http/Controllers/AuthController` (SSO callback di `/auth/sso` + `/callback`,
  role pending page di `/pending-role`).
- **Routes**: 99 total — `routes/web.php` (Inertia/web) + `routes/api.php` (Sanctum API).
  Lihat via `php artisan route:list` atau MCP `laravel-sukirman`.

## MinIO (foto item)

Foto item MR disimpan di **MinIO** (S3-compatible), BUKAN `storage/app/public`.
Lokal: `http://127.0.0.1:9000` (bucket `sukirman`, creds `minioadmin`/`minioadmin`),
console `http://127.0.0.1:9001`. Server produksi: `s3.gotechdynamics.com` /
console `minio.gotechdynamics.com` (lihat `.env` `AWS_*`).

- Config disk: `config/filesystems.php` disk `s3` — `endpoint` dari env
  `AWS_ENDPOINT`, `use_path_style_endpoint` = `AWS_USE_PATH_STYLE_ENDPOINT` (**wajib true**
  agar path-style, bukan virtual-host), `options.verify=false` (local, cert lokal).
- Upload: `Storage::disk('s3')->putFileAs('item-foto/{itemId}', $file, $nama)` di
  `MaterialRequestController` (store + update + resubmit). Simpan **path key** di
  kolom `material_request_items.foto`, bukan file biner.
- Serve: route `item-foto/{id}` (web auth) → `Storage::disk('s3')->get($item->foto)`
  + header `Content-Type` dari `mimeType()`; 404 jika file hilang.
- Hapus: `MaterialRequest::deleteS3Photos()` (saat MR dihapus) &
  `MaterialRequestItem::deleteS3Photo()` (saat item dihapus / edit-revision).
- **Jangan expose URL MinIO langsung** ke frontend — selalu lewat route `item-foto/{id}`.

## Firebase Cloud Messaging (push notification)

Web push notification via Firebase (FCM) — menyala **di samping** bell (database
notification), bukan menggantikan.

- **Frontend**: `resources/js/firebase.ts` (init Firebase + ambil FCM token) +
  `resources/js/AppInit.vue` (dipanggil setelah login) +
  `public/firebase-messaging-sw.js` (service worker, handle push saat app di background).
- **Backend**: `app/Notifications/FcmChannel.php` (kirim push via FCM HTTP v1) +
  `MrNotification.php` `via()` = `['database', FcmChannel::class]` → bell + push sekaligus.
- **DB**: `users.fcm_token` (migration `add_fcm_token_to_users`).
- **Config**: `config/firebase.php` + `.env` `VITE_FIREBASE_*` + `VITE_FIREBASE_VAPID_KEY`.
  `FIREBASE_SERVICE_ACCOUNT` default ke `storage/app/firebase/service-account.json`
  (folder dibuat saat setup, file di-gitignore — jangan commit).
- **Route**: `POST /fcm-token` (web, CSRF) — frontend simpan token setelah Allow.

### Setup env baru / server

```bash
# 1. Firebase Console → Project settings → Cloud Messaging → Web Push certificates → Generate key pair
# 2. isi .env: VITE_FIREBASE_VAPID_KEY="..."
# 3. Firebase Console → Project settings → Service accounts → Generate new private key
# 4. taruh hasil download sbg storage/app/firebase/service-account.json
# 5. php artisan migrate          # add_fcm_token_to_users
# 6. npm run build                # VITE_* di-bundle saat build
# 7. php artisan config:clear
```

### Quirks (hard-earned — jangan diulang)

- **Chrome menolak `Notification.requestPermission()` tanpa user gesture** →
  `AppInit.vue` attach `pointerdown` sekali; prompt "Allow" muncul setelah user
  klik/tap. Jangan ubah jadi auto-on-load.
- **`FcmChannel` pakai `->withoutVerifying()`** (skip SSL verify) — diperlukan di
  local (cert issuer lokal). Di server HTTPS dengan cert valid, bisa dihapus atau
  dibuat conditional `APP_ENV=local`.
- **`AppInit.vue` di-render sebagai sibling root** → `onMounted` hanya jalan sekali;
  wajib `watch(page.props)` agar inisialisasi dipicu saat login (`auth.user` berubah).
- **iOS Safari tidak support FCM web push** — hanya Android Chrome + desktop browser;
  bell tetap jadi fallback untuk pengguna iPhone.
- Base URL di `firebase.ts` ambil dari shared prop `app_url` (subpath `/sukirman/public`),
  jangan hardcode `/`.
- FCM token tidak dihapus saat logout — token tetap valid per device.

### Test cepat

1. Login → dashboard → klik di halaman → dialog "Allow" → token tersimpan di
   `users.fcm_token`.
2. Trigger notif (mis. Direksi approve) → push muncul di browser + bell terisi.

## Domain rules

**6 role utama** (alur MR sesuai `ALUR_SUKIRMAN.md`):
`Supervisor` → `Manager` → `FM/GM` → `Direksi` → `Gudang` → `Purchasing`.

Role dibuat via **migration** (bukan seeder): `add_jenis_dan_role_departemen`
(`MTC`, `IT`, `HRD`) + `add_user_role` (`User`). Seeder `RoleSeeder` hanya
menjamin 7 role inti (Supervisor, Manager, FM/GM, Direksi, Gudang, Purchasing,
admin) + permission `teruskan-ke-direksi` — pakai `firstOrCreate`, aman
di-jalankan ulang.

**3 role tambahan** (`HRD`, `IT`, `MTC`) — hanya terlibat jika supervisor memilih
**jenis MR** yang sesuai saat create:

- MR jenis IT → manager IT ikut approve (cek kelayakan item, mis. laptop).
- MR jenis MTC → manager MTC ikut approve (item maintenance).
- MR jenis HRD → manager HRD ikut approve.
- MR jenis umum → tidak lewat HRD/IT/MTC, langsung ke Manager biasa.

Status MR (urut): `Pending Manager` → `Pending FM/GM` → `Pending Direksi`
→ (`Approve` → `Verifikasi Gudang` | `Reject` | `Revision`)
→ `Fully Approved` (stok ada) | `Purchasing` (stok tak ada → export XML ke Accurate).
Status lengkap (dipakai juga sebagai filter di Monitoring MR): `Pending Manager`,
`Pending FM/GM`, `Pending Direksi`, `Pending MTC`, `Pending IT`, `Pending HRD`,
`Verifikasi Gudang`, `Fully Approved`, `Purchasing`, `Rejected`, `Revision`.

**Skip FM/GM**: Manager dengan permission `teruskan-ke-direksi` bisa mengirim MR
langsung ke Direksi (tanpa FM/GM). Flag `skipFmGm` di `MaterialRequestController::print`
menyembunyikan blok tanda tangan FM/GM di halaman print.

**Edit MR**: pemilik bisa edit/hapus usulan selama status belum `Fully Approved`
atau `Purchasing` (route `material-requests/{id}/edit` + PUT `/update`,
halaman `EditPending.vue`). Direksi bisa minta revisi → supervisor perbaiki via
`edit-revision` + POST `/resubmit` (item yang ditolak bisa diubah/hapus; foto item
yang dibuang dihapus dari MinIO via `deleteS3Photo()`).

`ALUR_SUKIRMAN.md` adalah source of truth untuk alur lengkap + hak akses per role.

## Halaman tambahan (selain approval MR)

- **Monitoring MR** `/monitoring-mr` (`admin|Purchasing|Gudang`, read-only) —
  semua status termasuk `Purchasing`, filter search + factory + status
  (`monitoringIndex`). Gudang sudah bisa akses sejak awal.
- **Statistik Direksi** `/statistik-direksi` & **Pending Direksi per direksi**
  `/pending-direksi/{id}` (`admin|Purchasing`).
- **Statistik Pengaju** `/statistik-pengaju` (`StatistikPengajuController`,
  auth biasa).
- **My MRs** `/my-mrs` (`material-requests.my-mrs`) — usulan milik user login.
- **PO Detail** `/po-detail/{id}` — lihat `ItemPoLine` per MR. Tracking PO:
  kolom `item_po_lines.no_po` + `tgl_po`, status PO (Belum/Sudah/Sebagian) dilihat
  lewat subquery di `gudangIndex`/`purchasingIndex`.
- **Barang master** `/barangs` (CRUD + import + `barangs/search-api` autocomplete).
- **Settings** `/settings` & **Profile** `/profile` (auth biasa).

## Toolchain quirks

- **Auto-import Varlet** via `unplugin-vue-components` + `unplugin-auto-import`.
  Hasilkan `auto-imports.d.ts` & `components.d.ts` di root — **jangan edit manual**.
  File ini di-regenerate saat `npm run dev`/`build`.
- **Vite** (`vite.config.js`): host `0.0.0.0`, HMR host `localhost`,
  watch abaikan `**/storage/framework/views/**`.
- **TypeScript** (`tsconfig.json`): `strict: true`, types include `@varlet/ui/types`.
- **Migrations** ada 31 file (per Agustus 2026), banyak yang `add_*` — schema
  masih iteratif, jangan refactor tanpa diskusi.
- **`.editorconfig`**: indent 4 spasi, LF, final newline.

## Testing

```bash
composer test                         # semua suite
php artisan test --filter=ClassName   # satu test class
php artisan test tests/Feature/X.php  # satu file
```

- Cuma 2 file test (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`) —
  coverage tipis, kontribusi test baru sangat bernilai.
- Test env override (`phpunit.xml`): SQLite `:memory:`, queue sync, session array,
  broadcast null, mail array — tidak perlu DB/service eksternal untuk jalan.
- **Jangan** tulis test yang butuh DB/queue eksternal — env test sudah di-isolate.

## MCP & tools

`opencode.json` aktifkan MCP `laravel-sukirman` (`php artisan mcp:start sukirman`).
Tersedia 3 tool:

- `laravel-sukirman_list-routes-tool` — semua 99 route (method, URI, middleware, controller).
- `laravel-sukirman_list-models-tool` — model Eloquent + relasi + fillable.
- `laravel-sukirman_database-schema-tool` — skema DB (tabel, kolom, index).

Gunakan tool ini **sebelum** mengubah controller/model/migration — lebih cepat dari
grep manual di `routes/` dan `app/Models/`.

## Common pitfalls

- Jangan pakai `auth()` di route API — pakai `auth:sanctum`. Lihat beda middleware
  di `php artisan route:list` untuk grouping `web` vs `api`.
- Role pakai Spatie: `auth()->user()->hasRole('Manager')` atau middleware `role:NamaRole`.
  Nama role **case-sensitive** dan pakai `/` (mis. `FM/GM`).
- Saat buat MR controller logic, cek juga `MaterialRequestController::class` method
  `*ByManager/ByGM/ByDireksi/ByGudang/ByPurchasing` di API — duplikasi logic dengan
  route web, jadi perubahan harus sinkron.
- Export purchasing ada di 2 endpoint: `approval/purchasing/export` (bulk) dan
  `material-requests/{id}/xml` (per-MR). Keduanya return XML untuk Accurate.
- Foto item MR di-serve lewat `item-foto/{id}` route (web auth) — penyimpanan di
  **MinIO** (disk `s3`), bukan `storage/app/public` langsung.
