# Perbaikan keamanan IK WorkDesk — 20 Oktober 2026

Perbaikan diterapkan pada workspace dan PostgreSQL lokal setelah pengguna meminta tindak lanjut audit. Tidak ada perubahan hak akses bisnis pengguna atau isi pemeriksaan yang ditujukan oleh pekerjaan ini. Server produksi belum diakses.

## Hasil

| Temuan | Perbaikan | Verifikasi |
| --- | --- | --- |
| 10 paket npm terdampak advisory | Tailwind dan plugin Vite disatukan ke 4.3.3; concurrently ke 9.2.5 dengan shell-quote 1.12.0; source-map-js ke 1.2.2. Rantai dependensi Tailwind 3 yang menarik braces dihapus. | `npm audit --json`: 0 kerentanan termasuk dev; Composer: 0 advisory/abandoned. Build produksi berhasil. |
| Koneksi web memakai superuser | Role runtime khusus dibuat dengan password acak; aplikasi dialihkan melalui `.env`. Role tidak memiliki SUPERUSER, CREATEDB, CREATEROLE, REPLICATION, BYPASSRLS, keanggotaan role tambahan, maupun CREATE pada schema public. | `php artisan security:check` lulus. SELECT/INSERT/UPDATE/DELETE diuji dengan 0 baris diubah; CREATE TABLE ditolak dengan SQLSTATE 42501. |
| Debug aktif | Debug lokal dimatikan dan default `.env.example` diubah menjadi false. | Konfigurasi efektif dan pemeriksaan CLI lulus. |
| Cookie/deployment produksi | Cookie Secure menjadi default saat APP_ENV=production. Template `.env.production.example` menyertakan debug false, HTTPS, Secure, HttpOnly, SameSite=Lax, dan session encryption. | Pemeriksaan `--production` mendeteksi konfigurasi yang belum sesuai. Template belum diaktifkan pada server tujuan. |
| File environment dapat dibaca pengguna Windows lain | ACL `.env` dan `.env.database-admin` dibatasi ke akun Windows yang menjalankan aplikasi, SYSTEM, dan Administrators. Inheritance dihapus. | Kedua file hanya memiliki tiga entri Allow tersebut, tanpa entri inherited. Helper penulisan konfigurasi menerapkan ACL sebelum menulis rahasia ke file sementara. |

Kredensial administratif hanya disimpan dalam `.env.database-admin` lokal; aplikasi web tidak memuat file itu. File environment rahasia dan file sementara dikecualikan dari Git. Kredensial maupun APP_KEY tidak dicetak atau dimasukkan ke laporan. APP_KEY yang ada dipertahankan.

## Menjalankan aplikasi dan migrasi lokal

Web dan pekerjaan aplikasi tetap menggunakan `.env` dengan role runtime. Migrasi yang membuat/mengubah tabel memerlukan koneksi administratif terpisah:

```powershell
php artisan migrate:status --env=database-admin
php artisan migrate --env=database-admin
```

Perintah status sudah diuji; tidak ada migrasi bisnis yang dijalankan dalam perbaikan ini. Jangan membuat config cache memakai environment admin. Cache aplikasi harus dibuat dari `.env` runtime.

Runner PostgreSQL tetap dapat dipakai:

```powershell
php tests/run-it-infrastructure-postgres.php
```

Runner mengambil kredensial administratif lokal hanya untuk membuat database uji acak. Kredensial tersebut tidak ditambahkan ke konfigurasi koneksi aplikasi web.

Provisioning lokal dapat diulang dengan `php scripts/provision-pgsql-runtime.php`. Script memeriksa environment local, koneksi loopback, target database, dan identitas role; menolak target role yang tidak dikenali. Script tidak mengubah role administrator yang sudah ada. Grant untuk tabel/sekuens baru mengikuti default privileges pemilik tabel yang ditemukan saat provisioning; bila migrasi memakai pemilik baru, tinjau grant atau jalankan provisioning kembali.

Pada Windows, pembatasan ACL dapat diterapkan kembali dengan:

```powershell
powershell.exe -NoProfile -NonInteractive -File scripts/protect-environment.ps1 -Path .env
powershell.exe -NoProfile -NonInteractive -File scripts/protect-environment.ps1 -Path .env.database-admin
```

Helper ACL hanya menerima file environment yang ditentukan di root workspace dan menolak direktori atau reparse point. Helper ini ditujukan untuk pengembangan lokal yang menjalankan PHP dengan akun Windows yang sama.

## Sebelum deployment produksi

1. Sesuaikan domain HTTPS, database, password runtime, mail, dan konfigurasi layanan pada lingkungan tujuan menggunakan `.env.production.example` sebagai acuan. Pertahankan APP_KEY yang benar untuk data existing; jangan menggantinya sembarang.
2. Pasang HTTPS pada web server/reverse proxy dan arahkan document root ke `public`. File `public/hot` dari Vite development server tidak boleh ada pada deployment produksi.
3. Jangan sertakan `.env.database-admin` atau kredensial administrator dalam paket deployment. Jalankan migrasi melalui kredensial administratif yang hanya tersedia pada proses migrasi, kemudian gunakan kembali konfigurasi runtime sebelum membuat config cache.
4. Bangun aset dari lockfile dan periksa konfigurasi efektif:

```powershell
npm ci --ignore-scripts
npm run build
php artisan config:cache
php artisan security:check --production
```

Semua pemeriksaan produksi harus lulus sebelum aplikasi dibuka kepada pengguna. Perintah ini memeriksa beberapa persyaratan konfigurasi dan hak database; bukan pengganti audit menyeluruh atau pemeriksaan sertifikat/jaringan dari luar server.

Pada workspace lokal sekarang, `--production` sengaja gagal untuk environment local, URL HTTP, cookie non-Secure lokal, Vite yang masih berjalan, dan keberadaan file admin lokal. HTTPS tidak dipaksakan pada `127.0.0.1` agar login pengembangan tetap bekerja. Sertifikat, domain, dan konfigurasi reverse proxy produksi belum tersedia untuk diterapkan dari sesi ini.

## Pengujian setelah perbaikan

- **185 tes SQLite / 2.425 assertion lulus**: seluruh suite Infrastruktur IT, SecurityHardening, SecurityReadiness, Auth, UserAccessHierarchy, UserAdditionalAccess, MenuFlowAccess, dan WorkLogAuthorization.
- **21 tes PostgreSQL / 269 assertion lulus**, ditambah 40 nomor aset unik dari dua worker. Database uji sudah dibersihkan; fingerprint schema, daftar migration, dan jumlah record tabel utama pada database asal tidak berubah.
- Dua workbook Excel berhasil dibuat melalui koneksi runtime terbatas: ringkasan 9.588 byte dan rincian 12.969 byte. Payload merupakan XLSX/ZIP. Penantian event download melalui alat browser tidak memberikan konfirmasi unduhan; pembuatan workbook diverifikasi terpisah melalui backend.
- `npm run build`, `npm audit --json`, `composer audit --locked --format=json`, Pint, sintaks PHP, dan `git diff --check` lulus.
- Probe tanpa login: `.env`, `.env.database-admin`, file sementara environment yang diproba, dan `.git/config` menghasilkan 404; log Laravel menghasilkan 403; halaman laporan menghasilkan redirect login.

## Verifikasi tampilan

Pembaruan build mempertahankan desain Internal9 yang ada, dengan antislop diterapkan selama pekerjaan dan ENERGY/RHYTHM/MOTION tetap 1. Tidak ada aset visual baru. Warna gray/slate/indigo/blue/red/green, line height, shadow, rounded, dan outline lama dipertahankan melalui konfigurasi CSS kompatibilitas.

- Dashboard: 12 elemen yang dibandingkan memiliki posisi vertikal, tinggi, warna teks, dan warna border yang sama dengan baseline sebelum upgrade.
- Profile: navigasi terbuka; dropdown menampilkan Profile/Log Out; Escape menutup dropdown dan mengembalikan fokus; Tab memindahkan fokus input dengan ring indigo terlihat. Tidak ada formulir akun yang dikirim.
- Layar 390px: halaman Profile memiliki lebar/scroll 375px; input berada di dalam layar. Laporan memiliki lebar/scroll 375px dengan wrapper tabel 343px.
- Rekap: tab Kondisi terakhir bekerja; filter kategori tanpa data menampilkan keadaan kosong; filter dikembalikan; PK-000003 membuka ringkasan; tautan kembali membuka rekap.
- Console browser tidak melaporkan error pada halaman yang diperiksa. Viewport sementara sudah direset. Alur penyimpanan, validasi, dan pembatasan aksi diuji pada database pengujian.
- Kontras warna komponen yang dipertahankan: gray-700/putih sekitar 10,3:1, gray-900/putih sekitar 17,7:1, slate-500/putih sekitar 4,76:1; ring fokus indigo-500/putih sekitar 4,47:1. Pemeriksaan ini tidak menyatakan seluruh halaman aplikasi telah diaudit aksesibilitasnya.

![Laporan setelah perbaikan](../output/security-hardening-20261019/rekap-setelah-perbaikan.png)
