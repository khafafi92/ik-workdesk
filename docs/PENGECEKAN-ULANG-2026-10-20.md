# Pengecekan ulang seluruh modul dan master, 20 Oktober 2026

Lanjutan pengujian transaksi dan validasi, termasuk temuan fungsi LTRO, tersedia pada [Pemeriksaan Fungsi](PENGECEKAN-FUNGSI-2026-10-20.md).

Pemeriksaan mencakup seluruh 99 route halaman panel tanpa parameter record, termasuk halaman daftar, dashboard, laporan, pengaturan, dan form tambah data. Tidak ditemukan HTTP 500 pada 396 permintaan admin lokal. **Hasil keseluruhan belum bersih:** terdapat satu bug penghitung nomor aset, tiga fixture tes yang memakai izin lama, antrean notifikasi lama, dua migration data yang pending, dan temuan format kode.

## Ringkasan verifikasi

| Pemeriksaan | Hasil | Bukti / batas |
| --- | --- | --- |
| Semua halaman panel statis | Sesuai perilaku yang ditetapkan | 99 halaman × 4 putaran: 364 respons 200, 28 respons 403, 4 respons 404; tidak ada 500 |
| Halaman aktif dengan admin | 91/91 menghasilkan 200 | Median server per halaman 292–541,2 ms; satu putaran pemanasan dikeluarkan |
| Akses tanpa login | 99/99 diarahkan ke login | Seluruh respons 302 ke `/login` |
| Ringkasan pemeriksaan dengan ID lokal valid | 200 | `/panel/it-inspection-summary?inspection=1`, melalui transaksi read-only |
| PHPUnit utama, SQLite memory | **438 lulus, 4 gagal** | 442 test, 3.901 assertion, tidak ada error atau skipped |
| Infrastruktur IT, PostgreSQL terpisah | **124 lulus, 1 gagal** | 125 test, 2.457 assertion; bug penghitung yang sama terulang |
| Transaksi dan alokasi nomor bersamaan | Lulus | Satu test transaksi, 12 assertion; dua worker mengalokasikan 40 nomor berbeda, 00035–00074 |
| Sintaks PHP | Lulus | 666 berkas aktif pada app, bootstrap, config, database, routes, tests, dan scripts; cache bootstrap dikecualikan |
| Integritas database lokal | Lulus untuk relasi yang dideklarasikan | 81 tabel, 156 foreign key, 0 orphan, 0 constraint belum divalidasi |
| Keamanan konfigurasi lokal | Lulus | Sembilan pemeriksaan `security:check`; bukan pemeriksaan production |
| Build frontend production | Lulus | Vite selesai; ada peringatan durasi plugin Tailwind, tanpa kegagalan build |
| Pint seluruh workspace | **Gagal** | 32 berkas kode aktif dan 25 berkas salinan/artefak historis memiliki perbedaan format |
| Pint alat pemeriksaan yang diperbarui | Lulus | `scripts/profile-page.php` dan `scripts/check-local-database.php` |
| `git diff --check` | Lulus | Tidak ada masalah whitespace pada diff tracked |

Suite PostgreSQL mengulang subset dari suite utama. Jumlah test kedua suite tidak boleh dijumlahkan sebagai jumlah test unik. Runner PostgreSQL membuat dan membersihkan database sementara; pemeriksaan schema public, daftar migration, dan jumlah record tabel utama sebelum/sesudah tetap sama.

## Master yang diperiksa

Semua halaman daftar berikut menghasilkan 200. Halaman tambah yang terdaftar juga termasuk dalam pemeriksaan 99 route. Waktu adalah median server lokal dalam ms, bukan waktu tampil penuh di browser.

| Master | HTTP | Median server | Maks. query |
| --- | ---: | ---: | ---: |
| Activity Categories | 200 | 336,9 | 7 |
| ATK Categories | 200 | 301,1 | 7 |
| ATK Items | 200 | 367,8 | 7 |
| ATK Units | 200 | 377,3 | 7 |
| Departments | 200 | 388,1 | 7 |
| Document Numbering Templates | 200 | 521,7 | 14 |
| Document Types | 200 | 445,2 | 7 |
| Employees | 200 | 359,2 | 9 |
| IT Devices | 200 | 411,8 | 12 |
| Letter Profiles | 200 | 398,3 | 10 |
| Meeting Rooms | 200 | 392,8 | 18 |
| Permit Companies | 200 | 371,9 | 7 |
| Task Categories | 200 | 410,7 | 7 |
| Ticket Categories | 200 | 378,5 | 8 |
| Users | 200 | 463,8 | 22 |
| Vehicles | 200 | 408,3 | 6 |
| Work Locations | 200 | 447,7 | 7 |
| Work Projects | 200 | 318 | 6 |

## Temuan yang perlu ditangani

### 1. Tinggi: koreksi nomor aset tidak memajukan penghitung

Reproduksi pada SQLite dan PostgreSQL sama: buat aset `00001/APCA/IT/2025`, koreksi manual menjadi `00035/KPMOG/IT/2025`, lalu buat aset otomatis berikutnya. Nomor yang keluar adalah **`00002/APCA/IT/2025`**, sedangkan perilaku yang diuji mengharapkan **`00036/APCA/IT/2025`**.

`ItAssetNumberGenerator::next()` menghasilkan format satu slash, tetapi `reserve()` masih mencocokkan pola `//IT/`. Akibatnya koreksi nomor valid dengan satu slash tidak dicatat ke penghitung. Sumber: [generator](../app/Services/ItInfrastructure/ItAssetNumberGenerator.php), metode `reserve()`; reproduksi: [ItAssetNumberTest](../tests/Feature/ItInfrastructure/ItAssetNumberTest.php), `test_generated_number_is_editable_and_corrections_keep_the_counter_ahead_without_duplicates`.

Alokasi otomatis oleh dua worker tetap lulus. Jadi temuan ini spesifik pada reservasi setelah koreksi manual, bukan kegagalan locking alokasi otomatis. Belum ada perubahan pada generator atau nomor aset nyata.

### 2. Tinggi: antrean notifikasi lokal tidak diproses

Terdapat **195 job pending**, seluruhnya attempts 0 dan tidak sedang reserved. Kelompoknya: 100 TicketCommentNotification, 30 TicketCreatedNotification, 4 TicketResolvedNotification, dan 61 WorkTaskAssignedNotification. Job tertua tersedia sejak 22 Juli 2026 pukul 10:18:56 WIB. Proses PHP `queue:work`/`queue:listen` tidak terdeteksi pada saat pemeriksaan.

Terdapat pula **18 failed job historis** dari Juli 2026: 10 `UnexpectedResponseException` dari mailer dan 8 `RfcComplianceException` dari MIME. Ini bukan 18 kegagalan baru dari pengujian saat ini. Status SMTP aktual belum diuji dengan pengiriman keluar. Worker, retry, dan pengiriman ulang tidak dijalankan karena dapat mengirim notifikasi lama kepada pengguna.

### 3. Sedang: dua migration data surat APCA belum dijalankan

`migrate:status` menampilkan pending untuk:

- `2026_10_06_000001_update_apca_outgoing_letter_number_format`
- `2026_10_06_000002_correct_apca_general_letter_master`

Keduanya memodifikasi data master/template penomoran APCA. Migration kedua juga memindahkan cakupan sequence dan menonaktifkan template department tertentu. Perlu meninjau dampak pada data surat dan penomoran yang sudah ada sebelum penerapan; pemeriksaan ini tidak menjalankan migration tersebut.

### 4. Sedang: tiga fixture tes Daily Activity belum mengikuti izin menu terbaru

Tiga kegagalan HTTP 403 berasal dari `DailyActivityTest`: form tambah, label daftar, dan laporan personal/admin. Helper `grantDailyAccess()` hanya memberikan `worklogs.view`, sementara resource sekarang memerlukan `daily-activities.manage` dan laporan memerlukan `report.daily-activities`.

Pengujian `MenuFlowAccessTest::test_menu_choice_permissions_control_categories_and_daily_activities` sudah lulus dengan alur checklist menu saat ini. Arah koreksinya adalah menyesuaikan fixture tes dengan izin eksplisit, sambil mempertahankan pengujian penolakan akses; jangan membuka akses resource hanya untuk membuat tes lama lulus. Test source dan izin akun nyata tidak diubah dalam pemeriksaan ini.

### 5. Sedang: query berulang pada Pengecekan Infrastruktur masih ada

Halaman `/panel/it-inspections` tetap mencatat 132 query, termasuk 118 pembacaan daftar ID department. Temuan dan rantai pemanggilnya konsisten dengan [laporan load sebelumnya](PENGECEKAN-LOAD-2026-10-20.md). Perhitungan akses perlu dibatasi pada satu request/render dengan tetap melakukan authorization pada aksi server.

### 6. Rendah: standar format kode belum lulus

Pint melaporkan perbedaan format pada 32 berkas kode aktif. Sebanyak 25 temuan lain berasal dari salinan di `tmp` dan artefak audit di `output`, sehingga perlu dipisahkan dari kode aplikasi saat menata pemeriksaan format. Tidak dilakukan pemformatan massal. Daftar berkas aktif disimpan pada `pint-active-files.json`.

## Penolakan akses yang memang diharapkan

- `/panel/roles` dan `/panel/roles/create`: resource Role Management dinonaktifkan dengan `canViewAny()`/`canCreate()` mengembalikan false.
- `/panel/location-reports` dan `/panel/location-reports/create`: halaman Attendance lama dinonaktifkan.
- `/panel/work-hour-imports`, `/panel/work-hour-imports/create`, dan `/panel/work-hour-records`: halaman Work Hours lama dinonaktifkan.
- `/panel/it-inspection-summary` tanpa query ID pemeriksaan: 404 sesuai validasi. Dengan ID lokal valid, hasilnya 200. Ringkasan juga tercakup pada pengujian Livewire Infrastruktur IT.

Respons tersebut tetap dicatat dalam data mentah. Setelah dicocokkan dengan sumber, tidak dikategorikan sebagai halaman rusak atau sebagai respons sukses 200. Aturan ekspektasi eksplisit disimpan pada `scripts/profile-expected-statuses.json` dan runner diverifikasi menggunakan kombinasi halaman 200, 403, dan 404.

## Lingkup dan batas

Pengujian mencakup rendering seluruh route panel statis dan suite perilaku yang tersedia untuk Service Desk, Work Logs, Daily Activity, Attendance, booking, ATK, surat, AI, chat, akses, keamanan, dan Infrastruktur IT. Ini bukan bukti setiap kombinasi data, setiap record/edit URL, setiap klik browser, atau integrasi eksternal sudah diuji. Foreign key hanya membuktikan relasi yang dideklarasikan di database; aturan bisnis tambahan bergantung pada cakupan tes.

Pengukuran server memakai profiler CLI dengan session/cache array dan database read-only. Beberapa putaran berlangsung bersamaan dengan pengujian dan lint, sehingga angka kecepatan tidak boleh digunakan untuk menyatakan regresi atau peningkatan terhadap hasil sebelumnya. FCP, LCP, INP, jaringan pengguna, beban production, SMTP, dan provider AI eksternal tidak diukur.

Perubahan sesi ini terbatas pada alat pemeriksaan, katalog route, hasil build lokal, serta laporan. Source perilaku aplikasi, policy, fixture test, data master, dan konfigurasi runtime tidak diubah. Tidak ada email atau pesan pengguna dikirim untuk pengujian.

## Bukti dan pengulangan

Artefak berada di [output/full-review-20261020](../output/full-review-20261020/): `phpunit.xml`, `postgres.xml`, `postgres-concurrency.txt`, `continued-samples.json`, `continued-summary.json`, `page-assessment.json`, `guest-check.json`, `database-integrity.json`, `php-lint.json`, `pint-active-files.json`, `security-check.txt`, `migrations.txt`, dan `build.txt`.

```powershell
./scripts/measure-page-load.ps1 -AllPages -OutputDirectory output/full-review-20261020
php scripts/check-local-database.php
php artisan security:check
```

Suite utama dijalankan dengan `APP_ENV=testing`, `APP_CONFIG_CACHE` dan `APP_ROUTES_CACHE` menuju berkas tidak ada di folder output, serta `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, dan `DB_URL` kosong. Suite PostgreSQL dijalankan menggunakan `tests/run-it-infrastructure-postgres.php`, yang membuat database uji terpisah. Jangan menjalankan refresh/fresh migration terhadap database aplikasi untuk mengulang pengujian.
