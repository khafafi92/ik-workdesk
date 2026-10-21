# Audit keamanan lokal IK WorkDesk — 19 Oktober 2026

Status diperbarui 20 Oktober 2026: temuan dependensi, akun database runtime, dan debug lokal sudah diperbaiki. Audit npm dan Composer sekarang tidak melaporkan advisory. HTTPS dan konfigurasi server produksi masih harus diterapkan pada lingkungan tujuan. Lihat [hasil perbaikan](PERBAIKAN-KEAMANAN-2026-10-20.md) untuk perubahan dan verifikasinya.

Pada audit awal, belum ditemukan pembacaan atau perubahan data tanpa izin pada skenario yang diuji. Temuan awal berikut dipertahankan sebagai catatan sebelum perbaikan.

Pemeriksaan mencakup kode dan pengujian otorisasi, modul Infrastruktur IT, login, akses user/role, Work Log, lampiran, dependensi, serta endpoint HTTP lokal. Pemeriksaan ini tidak mengakses server produksi dan tidak membuktikan ada atau tidaknya insiden kebocoran sebelumnya. Log akses produksi, backup, riwayat kredensial, dan seluruh jalur aplikasi belum diaudit secara menyeluruh.

## Temuan audit awal

### 1. Dependensi pengembangan/build memiliki advisory

`npm audit --json` melaporkan **10 paket terdampak: 2 critical, 6 high, 2 moderate**. Angka ini menghitung paket yang mewarisi masalah dependensinya; bukan 10 kerentanan independen. Seluruh temuan berada pada dependensi pengembangan. `npm audit --omit=dev --json` menghasilkan 0 temuan.

| Dependensi terpasang | Temuan | Prioritas dan penanganan |
| --- | --- | --- |
| `shell-quote` 1.9.0 melalui `concurrently` 9.2.4 | Command injection pada fungsi `quote()`, critical. [Advisory GitHub](https://api.github.com/advisories/GHSA-pqg4-j6r4-53mv). | Perbarui ke versi yang menarik `shell-quote` >=1.11.0, lalu uji perintah pengembangan dan build. |
| `braces` 3.0.3 melalui rantai Tailwind 3.4.19 | Penolakan layanan melalui pola bersarang, high. Advisory belum mencantumkan versi perbaikan. [Advisory GitHub](https://api.github.com/advisories/GHSA-vfj7-8cjw-p6xm). | Tinjau penggantian/peningkatan rantai dependensi Tailwind. Jangan menjalankan perbaikan otomatis yang memaksa perpindahan versi mayor tanpa menguji CSS. |
| `source-map-js` 1.2.1 | Penolakan layanan saat membaca source map tertentu, high. [Advisory GitHub](https://api.github.com/advisories/GHSA-68fv-2mgg-jv7q). | Perbarui ke >=1.2.2 melalui dependensi yang kompatibel. |
| `postcss-selector-parser` 6.1.4 | Penggunaan CPU berlebih saat parsing selector, moderate. [Advisory GitHub](https://api.github.com/advisories/GHSA-rj75-hqrm-r3gf). | Perbarui rantai dependensi ke parser >=7.1.6 dan uji hasil CSS. |

Metadata advisory diverifikasi melalui API GitHub. Tidak ditemukan jalur dari input pengguna web menuju fungsi rentan tersebut dalam penelusuran yang dilakukan. Label critical pada paket tidak membuktikan pengguna dapat mengambil alih aplikasi web ini. Tetap prioritaskan perbaikan alat build dan jangan menjalankan Vite development server sebagai layanan produksi.

`composer audit --locked --format=json`: **0 advisory dan 0 paket abandoned**. Ini menunjukkan hasil basis advisory saat audit, bukan jaminan bahwa seluruh kode PHP bebas masalah.

### 2. Akun koneksi database aplikasi memiliki hak superuser

Pembacaan `pg_roles` untuk `current_user` menghasilkan `rolsuper=true`, `rolcreatedb=true`, dan `rolcreaterole=true`. Jika kredensial atau proses aplikasi disusupi, hak tersebut memperbesar dampak hingga seluruh cluster PostgreSQL. PostgreSQL menjelaskan bahwa superuser melewati hampir semua pemeriksaan izin; pengguna aplikasi sebaiknya memiliki hak yang diperlukan saja. [Dokumentasi PostgreSQL](https://www.postgresql.org/docs/current/role-attributes.html).

Prioritas tinggi sebelum produksi: gunakan akun runtime khusus dengan `NOSUPERUSER`, `NOCREATEDB`, dan `NOCREATEROLE`; berikan akses hanya ke database/schema aplikasi. Pisahkan akun migrasi bila perlu. Audit ini tidak mengubah akun database atau kredensial.

Port 5432 mendengarkan pada semua alamat IPv4/IPv6, tetapi aturan `pg_hba_file_rules` yang terbaca hanya mengizinkan loopback dengan `scram-sha-256`. Semua profil Windows Firewall aktif dengan default inbound `Block`. Karena itu, listener ini saja **tidak membuktikan database dapat diakses dari jaringan luar**. Jangkauan dari host lain belum diuji.

### 3. Konfigurasi lokal belum sesuai untuk produksi

Konfigurasi efektif: `app.env=local`, `app.debug=true`, URL HTTP, dan cookie sesi tanpa atribut `Secure`. Cookie sesi sudah memakai `HttpOnly` dan `SameSite=Lax`. Web server saat diperiksa hanya mendengarkan pada `127.0.0.1:8000`; Vite pada loopback IPv6.

Sebelum dipublikasikan, pasang HTTPS dan gunakan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, serta `SESSION_SECURE_COOKIE=true`; bangun ulang cache konfigurasi pada lingkungan tujuan. Root web harus menunjuk ke direktori `public`. Debug aktif di produksi dapat membocorkan konfigurasi sensitif saat terjadi error. [Panduan deployment Laravel](https://laravel.com/docs/13.x/deployment#debug-mode).

Ini merupakan temuan kesiapan deployment pada mesin lokal. Konfigurasi server produksi belum diverifikasi. `.env` lokal tidak diubah agar pengembangan tetap berjalan.

## Perlindungan yang berhasil diverifikasi

- Empat halaman Infrastruktur IT tanpa sesi login mengarahkan ke `/login`.
- Permintaan HTTP ke `/.env`, `/.git/config`, dan `/composer.json` menghasilkan 404; `/storage/logs/laravel.log` menghasilkan 403. Tidak ada isi file yang terbuka pada probe tersebut.
- Pendaftaran publik `/register` tidak tersedia (404).
- POST tanpa token CSRF ke login, endpoint update Livewire yang terdaftar, dan upload Livewire ditolak (419).
- Penggantian ID pemeriksaan setelah komponen Livewire dimuat ditolak untuk departemen lain; ID halaman ringkasan juga terkunci dari perubahan klien.
- Pemanggilan langsung aksi tindak lanjut memakai ID departemen lain ditolak dan tidak mengubah record maupun event pemeriksaan.
- Pembaca laporan tidak mendapat izin tulis, akses draft, atau ekspor secara otomatis. Aksi ekspor memeriksa izin di server; hasil ekspor mengikuti cakupan departemen.
- Pemindahan perangkat tidak memindahkan hak akses riwayat pemeriksaan lama ke departemen baru.
- Payload yang memalsukan petugas, pembuat finalisasi, departemen, dan snapshot tidak mengganti identitas yang ditentukan server.
- Payload `<script>` yang disimpan dalam catatan tampil sebagai teks yang di-escape pada ringkasan; uji formula Excel juga lulus.
- Pengguna nonaktif ditolak meskipun memiliki izin atau flag admin, sesuai pengujian modul IT.
- Pemeriksaan seluruh file pada disk `public` lokal hanya menemukan `.gitignore`. Tidak ditemukan lampiran sensitif di disk itu pada saat audit.
- Pemindaian terbatas terhadap 851 file teks terlacak tidak menemukan pola private key, AWS access key, GitHub token, OpenAI token, atau literal `APP_KEY` yang diperiksa. `.env` tidak terlacak saat ini. Pemindaian ini tidak mencakup seluruh format rahasia atau sejarah Git.

## Hasil pengujian

| Kelompok | Hasil |
| --- | --- |
| Akses IT, ekspor, transfer departemen, ringkasan, formula Excel | 17 tes / 189 assertion lulus pada SQLite |
| `SecurityHardeningTest` (termasuk lampiran dan pembatasan role) | 19 tes / 46 assertion lulus pada SQLite |
| Auth, UserAccessHierarchy, UserAdditionalAccess, MenuFlowAccess, WorkLogAuthorization | 38 tes / 139 assertion lulus pada SQLite |
| `ItSecurityBoundaryTest` baru: manipulasi ID, tindak lanjut asing, identitas palsu, escape script | 4 tes / 38 assertion lulus pada SQLite |
| Kelompok IT di atas dan tes keamanan baru pada PostgreSQL terpisah | 21 tes / 269 assertion lulus |
| Uji alokasi nomor aset dengan dua worker PostgreSQL | 40 nomor berbeda, lulus |

Total SQLite: **78 tes / 412 assertion lulus**. PostgreSQL memakai database uji sementara yang sudah dibersihkan; pemeriksaan runner memastikan schema public, daftar migration, dan jumlah record tabel utama pada database asal tetap sama. Pint untuk file tes baru lulus. Seluruh suite aplikasi tidak dijalankan dalam audit ini.

Pada tahap audit awal, perubahan hanya berupa laporan ini dan `tests/Feature/ItInfrastructure/ItSecurityBoundaryTest.php`. Perbaikan yang kemudian diminta pengguna dicatat terpisah dalam laporan 20 Oktober 2026.
