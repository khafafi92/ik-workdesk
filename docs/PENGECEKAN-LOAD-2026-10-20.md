# Pengecekan waktu muat IK WorkDesk, 20 Oktober 2026

Pemeriksaan lanjutan mencakup 23 halaman lokal. Seluruh 92 permintaan menghasilkan HTTP 200: 23 permintaan pemanasan dan 69 sampel terukur. Median waktu server sesudah pemanasan berkisar **217,1–481,3 ms**. Angka ini bukan waktu sampai halaman terlihat di browser dan bukan hasil pengujian production.

## Metode dan batas hasil

- PHP 8.4.22, PostgreSQL lokal, OPcache CLI aktif, konfigurasi Laravel/Filament sudah di-cache.
- Profiler menjalankan GET melalui HTTP kernel Laravel dari proses CLI, menggunakan akun admin lokal yang sudah ada. Authentication dan rendering aplikasi tetap dijalankan; pemilihan akun dilakukan sebelum render.
- Satu putaran pemanasan per halaman dikeluarkan dari statistik, dilanjutkan tiga putaran berurutan. Total mencakup bootstrap, pemilihan akun, dan render; SQL dicatat selama render dan terminasi, termasuk perintah transaksi read-only. Median setiap kolom dihitung terpisah, sehingga penjumlahan median bootstrap dan render tidak harus sama dengan median total.
- Database berada dalam transaksi read-only yang di-rollback. Session/cache memakai array, queue memakai null, dan mail memakai array untuk proses profiler saja. Tidak ada pengiriman notifikasi atau perubahan data bisnis dari profiler.
- Durasi query, ukuran HTML, dan jumlah elemen mengacu pada data lokal saat pengujian. Data besar, role lain, paginasi berikutnya, Livewire update, beban bersamaan, jaringan pengguna, dan production belum diuji.
- Browser lokal memakai Vite development yang aktif di `http://[::1]:5173`. Tidak ada perubahan atau penghentian proses development.
- FCP, LCP, dan INP belum diukur. API browser yang tersedia tidak mengekspos Performance Timeline. Timeout kontrol browser sempat terjadi; timeout tersebut tidak dipakai sebagai angka waktu aplikasi.

## Hasil server

Satuan waktu dalam ms. Semua halaman menghasilkan 200 pada ketiga sampel. Jumlah query adalah nilai maksimum per halaman.

| Halaman | Median total | Min–maks total | Median SQL | Query |
| --- | ---: | ---: | ---: | ---: |
| `/panel` | 252,8 | 249,8–358,8 | 48,92 | 42 |
| `/panel/service-desk` | 349,7 | 296,3–381,2 | 33,35 | 20 |
| `/panel/service-desk/create` | 256,2 | 247–279,1 | 17,94 | 6 |
| `/panel/work-tasks` | 334 | 331,3–612,6 | 30,95 | 20 |
| `/panel/it-devices` | 355,3 | 244,4–395,4 | 23,96 | 12 |
| `/panel/it-infrastructure-reports` | 263,9 | 257,9–366,3 | 31,21 | 15 |
| `/panel/users` | 306,8 | 278,6–387,5 | 34,65 | 22 |
| `/panel/users/create` | 277,4 | 238–399,3 | 23,7 | 7 |
| `/panel/attendance-report-center` | 246,4 | 229,3–293,3 | 21,09 | 8 |
| `/panel/attendance-results` | 347,5 | 272,1–519,8 | 25,53 | 7 |
| `/panel/activity-reports` | 297,8 | 264,6–355,4 | 26,81 | 10 |
| `/panel/daily-activities` | 281,6 | 260,4–516,4 | 25,97 | 12 |
| `/panel/meeting-room-calendar` | 248,3 | 227,7–319,6 | 21,14 | 7 |
| `/panel/meeting-bookings` | 393,7 | 262,6–469,5 | 25,54 | 7 |
| `/panel/vehicle-bookings` | 388,9 | 248,6–438,7 | 20,39 | 7 |
| `/panel/reminders` | 374 | 239,4–422 | 23,12 | 10 |
| `/panel/it-inspections` | 481,3 | 358,4–554,6 | 71,35 | 132 |
| `/panel/atk-dashboard` | 217,1 | 215,4–332,3 | 22,55 | 10 |
| `/panel/ltro-daily-reports` | 338,5 | 226,5–436,9 | 21,17 | 8 |
| `/panel/reports/service-desk` | 279 | 238,6–342,4 | 25,95 | 17 |
| `/panel/reports/work-logs` | 369,8 | 297,1–387,3 | 35,24 | 16 |
| `/panel/reports/workload` | 257,4 | 246,8–430,6 | 21,24 | 9 |
| `/panel/reports/sla-ticket-aging` | 263,1 | 242,4–485,9 | 24,69 | 14 |

## Temuan dan prioritas

1. **Pengecekan Infrastruktur mengulang query department 118 kali per render.** Dari 132 query, 118 memiliki fingerprint `select "id" from "departments"`. Sumber yang sesuai dengan pola ini adalah `User::accessibleDepartmentIds()` pada cabang admin. `ItInspectionPolicy::create()` memanggilnya, sedangkan `ItInspectionPage::isReadOnly()` dipanggil berulang pada input checklist. Optimasi berikutnya sebaiknya membatasi perhitungan ini pada satu request/render, dengan tetap menjalankan authorization pada aksi server dan menguji perubahan permission. Belum ada perubahan pada policy atau akses pengguna dalam pemeriksaan ini.

2. **Vite development menambahkan variasi waktu awal.** Permintaan HTTP langsung ke `/resources/css/app.css` mencatat 1.941,19 ms, lalu 1,20 dan 1,36 ms. Satu pemeriksaan awal terpisah juga mencatat sekitar 3 detik, tanpa disimpan sebagai sampel terstruktur. CSS hasil build yang sudah tersedia mencatat 41,72 ms, lalu 1,04 dan 0,96 ms. Ini bukti variasi penyajian aset development; bukan perbandingan total load production. Untuk benchmark deployment, gunakan build statis pada instance pengujian terpisah dari Vite aktif.

3. **Payload tabel cukup besar sebelum kompresi.** HTML Service Desk berukuran 1.098.912 byte, Work Logs 1.134.421 byte, dan Attendance Results 918.593 byte. Simulasi gzip level 6 menghasilkan masing-masing 41.195, 71.435, dan 37.037 byte. Ukuran gzip adalah perhitungan offline, bukan bukti kompresi respons HTML sudah aktif. Respons aset server PHP lokal tidak menyediakan `Content-Encoding`, `Cache-Control`, atau `ETag` pada pemeriksaan ini. Periksa kompresi dan cache aset di web server production sebelum mengubah tampilan tabel.

4. **Gambar latar login berukuran 2.675.276 byte.** `resources/views/layouts/guest.blade.php` memuat `public/img/office-login-background.png` pada halaman login. Optimasi format dan dimensi merupakan kandidat untuk jaringan lambat. Ukuran file saja belum membuktikan gambar ini menjadi elemen LCP.

5. **Hasil sebelum/sesudah lama tidak cukup untuk menyatakan persentase peningkatan.** `comparison.json` lama menunjukkan perlambatan besar, tetapi tidak memiliki konteks pemanasan dan kondisi runtime yang sebanding. Pengukuran ulang awal delapan halaman mencatat 239,2–649,5 ms; putaran terkontrol yang lebih luas menghasilkan tabel di atas. Selisih ini tidak boleh diklaim sebagai efek perubahan kode karena tidak ada pembandingan terkontrol dengan versi sebelum optimasi.

## Verifikasi browser dan skrip

- Dashboard panel dan Service Desk berhasil dibuka melalui sesi browser lokal yang sudah aktif. Service Desk menampilkan 25 dari 31 record, dengan 2.980 elemen DOM dan 278 SVG setelah hydration. Log yang tertangkap tidak berisi warning/error pada pemeriksaan ini.
- Font panel berasal dari aset Inter lokal. Avatar masih merujuk `ui-avatars.com`; layout aplikasi non-Filament masih mendeklarasikan font eksternal di `resources/views/layouts/app.blade.php`. Dependensi tersebut belum diukur durasinya.
- `PerformanceConfigurationTest`: lulus, 2 test dan 7 assertion. Cakupannya font lokal dan konfigurasi pengirim mail; bukan uji regresi seluruh aplikasi.
- Lint PHP, Pint untuk `scripts/profile-page.php`, dan parser PowerShell untuk runner: lulus. Runner menyelesaikan 92 permintaan dengan hasil terstruktur.
- Perubahan pada sesi ini terbatas pada alat pemeriksaan dan laporan. Tampilan, policy, data bisnis, serta konfigurasi runtime tidak diubah.

## Menjalankan ulang

```powershell
./scripts/measure-page-load.ps1 -Rounds 3 -OutputDirectory output/performance-review-20261020
php scripts/profile-page.php --path=/panel/service-desk --details
```

Runner hanya dapat memakai allowlist GET di profiler, environment local, dan host database loopback. Perintah transaksi read-only pada profiler ditujukan untuk PostgreSQL lokal proyek ini.

Data mentah: [sampel](../output/performance-review-20261020/continued-samples.json), [ringkasan](../output/performance-review-20261020/continued-summary.json), [aset](../output/performance-review-20261020/asset-check.json), dan [pengukuran ulang awal](../output/performance-review-20261020/recheck-server.json).
