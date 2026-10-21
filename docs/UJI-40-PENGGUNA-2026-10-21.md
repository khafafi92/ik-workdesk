# Uji 40 pengguna bersamaan, 21 Oktober 2026

Pada pengujian lokal, **40 akun dapat login dan mengakses aplikasi tanpa kegagalan HTTP maupun pemeriksaan isi respons**. Namun, hasil ini belum cukup untuk menyatakan aplikasi siap melayani 40 pengguna dengan lancar di server operasional. Simulasi satu proses PHP menunjukkan antrean panjang. Simulasi delapan proses lebih cepat, tetapi waktu respons halaman dan Livewire pada 40 pengguna masih melampaui patokan evaluasi 2 detik.

## Target server operasional

Pengguna menyebut target `10.1.20.97:8093`; spesifikasi CPU/RAM dan web server belum ditentukan atau dikonfirmasi. Pada 21 Oktober 2026 pukul 09:01 WIB, satu GET tanpa autentikasi ke `http://10.1.20.97:8093/login` menghasilkan HTTP 200 dan memuat penanda teks login aplikasi. Durasi permintaan tunggal sekitar 243 ms hanya menunjukkan endpoint dapat dijangkau dari lingkungan ini. Versi aplikasi, spesifikasi, konfigurasi proses PHP, dan kapasitas server tersebut belum diverifikasi.

Tidak ada login, pembuatan akun, perubahan konfigurasi, atau uji beban yang dijalankan pada alamat tersebut. Seluruh hasil 40 pengguna di bawah berasal dari instance lokal terisolasi. Validasi kapasitas target memerlukan informasi CPU/RAM, web server serta jumlah proses PHP, dan instance/akun uji pada lingkungan target dengan versi serta konfigurasi operasional yang akan dipakai. [Catatan pemeriksaan koneksi](../output/concurrency-review-20261021/server-target-check.json).

## Hasil beban

Angka berikut adalah **P95 dalam detik**: 95% sampel memiliki durasi tidak melebihi angka tersebut. Waktu mencakup antrean server dan pengunduhan seluruh respons HTML/JSON melalui HTTP loopback, tanpa mengikuti redirect otomatis. Pengambilan form login, POST login, dan pembukaan halaman setelah login dihitung terpisah.

| Skenario | Jumlah pengguna | Satu proses PHP | Delapan proses PHP |
| --- | ---: | ---: | ---: |
| POST login serentak | 40 | 21,50 | 3,76 |
| Akses halaman campuran | 1 | 0,36 | 0,44 |
| Akses halaman campuran | 10 | 3,26 | 0,73 |
| Akses halaman campuran | 20 | 6,78 | 1,70 |
| Akses halaman campuran | 40 | 10,11 | 3,37 |
| Membuka Service Desk bersama | 40 | 11,14 | 4,26 |
| Pembaruan Livewire Service Desk | 40 | 11,91 | 3,38 |

- Total **1.221 permintaan**, termasuk 3 pemeriksaan awal dan 72 permintaan pemanasan. Sebanyak **1.146 permintaan** berada di fase pengukuran. Tidak ada kegagalan status atau pemeriksaan konten pada putaran yang sah.
- HTTP 302 memang diharapkan untuk login/logout dan penolakan akses sesudah logout. Keberhasilan login juga diperiksa lewat `/profile`: masing-masing dari 40 klien harus melihat alamat email akun miliknya sendiri.
- Fase halaman campuran pada 40 pengguna berisi 120 GET, tiga permintaan per pengguna, dengan jeda 0,5 detik setelah respons. Throughput teramati 3,77 permintaan/detik pada satu proses dan 11,59 pada delapan proses; durasi fase masing-masing 31,84 dan 10,35 detik, termasuk jeda pengguna.
- Livewire diuji dengan snapshot halaman asli, token CSRF, header Livewire, serta tiga panggilan `$refresh` per pengguna. Respons harus berisi snapshot dan HTML komponen yang valid. Ini menguji interaksi baca tabel, bukan penyimpanan data bisnis.
- Patokan evaluasi yang dipilih untuk sesi ini: P95 login paling lama 5 detik, halaman dan Livewire paling lama 2 detik, serta nol kegagalan. Ini bukan SLA yang disepakati dengan pengguna. Login delapan proses memenuhi patokan tersebut; halaman dan Livewire pada 40 pengguna belum memenuhinya.

## Lingkungan dan batas interpretasi

- Windows, Intel Core i7-1185G7, 4 inti/8 logical processor, RAM terpasang sekitar 16 GB; PHP 8.4.22 dan PostgreSQL 18.4 lokal.
- Konfigurasi Laravel di-cache khusus instance uji, OPcache CLI aktif, session dan cache menggunakan file, bcrypt cost 12. Password diverifikasi melalui alur autentikasi aplikasi asli.
- Database uji merupakan salinan database lokal. Volume awal mencakup 13 user, 12 employee, 31 ticket, 60 work task, 2 perangkat IT, 4 inspeksi, dan 14.035 attendance result. Ditambahkan 40 akun admin sintetis hanya pada salinan tersebut. Data ini belum mewakili volume besar di masa depan atau kombinasi role pengguna operasional.
- Pembanding memakai satu atau delapan server PHP bawaan pada port loopback terpisah. Klien dibagi secara round-robin ke proses dan tetap memakai proses tersebut selama skenario. **Ini bukan benchmark PHP-FPM, Apache, reverse proxy, ataupun server produksi.** Empat puluh sesi aktif juga tidak berarti 40 proses PHP atau koneksi database mengeksekusi bersamaan.
- Semua delapan proses disiapkan pada awal putaran. Pada pembanding satu proses, tujuh proses lain tidak melayani klien. View hasil kompilasi dipakai bersama oleh instance uji. Pemanasan dikeluarkan dari tabel; permintaan awal dashboard dan Service Desk pada proses pertama sempat memerlukan 12,63 dan 9,90 detik.
- CPU yang direkam adalah total mesin, termasuk generator beban dan aplikasi lain. Pada fase 40 pengguna/delapan proses, rata-rata total CPU sekitar 99,1% untuk halaman campuran dan 100% untuk Livewire. RAM tersedia minimum sekitar 1,52 GB pada fase Livewire tersebut. Pada fase login satu proses, RAM tersedia sempat hanya 280 MB. Beban aplikasi lain dan kondisi memori berubah selama putaran, sehingga selisih hasil tidak boleh dinyatakan sebagai persentase peningkatan murni karena perubahan jumlah proses.
- Pengujian berbentuk rangkaian burst singkat, bukan uji ketahanan berjam-jam. Belum mencakup aset CSS/JS/gambar, hydration/render browser, internet pengguna, unggah/import, ekspor besar, penyimpanan 40 transaksi bisnis serentak, SMTP, provider AI, atau koneksi realtime Reverb.
- Persentil menggunakan nearest rank. Sampel 1 pengguna hanya tiga GET dan bukan pengukuran stabil kapasitas tunggal untuk seluruh halaman.

Halaman campuran: `/panel`, `/panel/service-desk`, `/panel/work-tasks`, `/panel/it-devices`, `/panel/it-inspections`, `/panel/attendance-results`, `/panel/reports/work-logs`, dan `/panel/meeting-bookings`. Setiap respons panel diperiksa status 200 dan keberadaan snapshot Livewire agar halaman login tidak keliru dihitung sebagai sukses.

## Uji fungsi aplikasi

Seluruh suite dijalankan ulang pada SQLite memory yang terisolasi: **500 tes, 4.725 assertion, 489 lulus, 8 assertion failure, dan 3 error**, durasi 228,54 detik. Hasil gagal dipertahankan sebagai temuan; perilaku aplikasi dan ekspektasi tes tidak diubah.

| Temuan | Hasil |
| --- | --- |
| Akses LTRO setelah izin dicabut | Update Livewire dari halaman yang sudah terbuka tetap membuat 30 record; ekspektasi penolakan 403 gagal. Prioritas tinggi. |
| Edit event MTTR | Form gagal karena relasi `category` tidak dapat diselesaikan. |
| Impor MTTR dengan running sebelum shutdown | Record tidak valid masih tersimpan. |
| Angka Availability tidak valid | Input teks berubah menjadi `0.00` tanpa penolakan validasi. |
| Penghitung nomor aset setelah koreksi manual | Ekspektasi nomor `00036/APCA/IT/2025` menghasilkan `00002/APCA/IT/2025`. |
| Tiga tes Aktivitas Harian | Respons 403; fixture memberi `worklogs.view`, sedangkan resource mensyaratkan `daily-activities.manage`. Ini ketidaksesuaian fixture/kontrak izin, bukan bukti semua akun operasional kehilangan akses. |
| Katalog checklist akses menu | Monitoring Harian IT terlihat oleh admin tetapi tidak ditemukan padanannya dalam katalog checklist yang diperiksa tes. |
| Dua error tanggal di SQLite | Penyimpanan ulang Daily Report dan pembuatan ulang periode Availability terkena unique constraint. Hasil SQLite tidak langsung membuktikan kegagalan PostgreSQL. |

Pemeriksaan ulang terpilih pada PostgreSQL memakai database sementara kosong dan mencakup Master Data Functional Flow, LTRO Functional Flow, serta ItAssetNumberTest: **45 tes, 693 assertion, 40 lulus, 4 assertion failure, dan 1 error**, durasi PHPUnit 83,18 detik. Lima temuan pertama dalam tabel tetap gagal pada PostgreSQL: pencabutan izin LTRO, edit MTTR, validasi waktu impor, angka Availability, dan penghitung aset. Dua alur tanggal yang error pada SQLite lulus pada PostgreSQL. Pemeriksaan ini bukan pengulangan seluruh 500 tes dengan PostgreSQL.

Runner mengonfirmasi database fungsi sementara telah dihapus dan schema public, daftar migration, serta jumlah record tabel utama database sumber tetap sama. Hasil lengkap dicatat pada artefak `functions-postgres.xml` dan `functions-postgres.txt`.

## Isolasi dan tindak lanjut

Alat uji memakai database sementara dengan nama acak, direktori session/cache/view terpisah, queue null, mail array, dan broadcasting null. Setiap request uji memeriksa bahwa konfigurasi cache, nama database, URL koneksi kosong, dan direktori session sesuai instance sementara sebelum memproses request. Dump, kredensial sementara, direktori runtime, dan database salinan sudah dibersihkan. Schema serta jumlah record seluruh tabel public database sumber tetap sama sebelum/sesudah uji; pemeriksaan ini tidak membandingkan setiap nilai di setiap baris.

Pemeriksaan awal alat sempat gagal karena penanganan path cache Windows dan pencocokan header redirect. Sampel dari tahap tersebut tidak dipakai sebagai hasil kapasitas. Putaran yang dilaporkan dilakukan setelah pemeriksaan satu pengguna dan identitas seluruh akun berhasil.

Untuk penggunaan 40 orang, tindak lanjut yang disarankan adalah menguji staging dengan spesifikasi dan web server yang sama dengan operasional, workload role serta transaksi yang nyata, dan volume data yang representatif. Gunakan hasil ini untuk menghindari asumsi bahwa satu proses server pengembangan sudah mencukupi. Tetapkan target waktu respons bersama pengguna, profilkan halaman yang lambat, lalu ulangi skenario setelah konfigurasi/optimasi disiapkan. Temuan pencabutan izin LTRO serta fungsi lain di atas perlu ditangani terpisah dari kapasitas server.

## Berkas dan cara menjalankan ulang

```powershell
php scripts/test-concurrent-users.php --output=output/concurrency-review-20261021
php vendor/phpunit/phpunit/phpunit -c output/concurrency-review-20261021/phpunit.xml --do-not-cache-result --log-junit output/concurrency-review-20261021/functions.xml
php tests/run-it-infrastructure-postgres.php --configuration output/concurrency-review-20261021/postgres.xml --log-junit output/concurrency-review-20261021/functions-postgres.xml
```

Runner beban khusus CLI lokal dengan PostgreSQL loopback; secara default memakai utilitas PostgreSQL Windows di `C:/Program Files/PostgreSQL/18/bin`, yang dapat diganti lewat `--pg-bin`. Hindari menjalankan suite fungsi bersamaan dengan benchmark. Konfigurasi XML tes dalam direktori output memakai path workspace saat pengujian.

- [Runner isolasi](../scripts/test-concurrent-users.php) dan [klien HTTP](../scripts/test-concurrent-users.py).
- [Ringkasan dan evaluasi](../output/concurrency-review-20261021/analysis.json), [sampel HTTP](../output/concurrency-review-20261021/http-samples.json), [ringkasan fase](../output/concurrency-review-20261021/http-summary.json).
- [Lingkungan dan volume data](../output/concurrency-review-20261021/environment.json), [metrik sistem](../output/concurrency-review-20261021/system-resources.json), [verifikasi isolasi](../output/concurrency-review-20261021/isolation.json).
- [Hasil fungsi SQLite](../output/concurrency-review-20261021/functions-summary.json) dan [JUnit](../output/concurrency-review-20261021/functions.xml).
- [Hasil fungsi PostgreSQL](../output/concurrency-review-20261021/functions-postgres.xml).
- [Ringkasan fungsi PostgreSQL](../output/concurrency-review-20261021/functions-postgres-summary.json).

Lint PHP, Pint runner PHP, dan pemeriksaan sintaks Python lulus. Skenario HTTP selesai dijalankan. Perubahan sesi ini berupa alat uji dan laporan; tidak ada perbaikan perilaku bisnis atau hak akses aplikasi yang diterapkan.
