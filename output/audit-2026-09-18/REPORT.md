# Audit fungsional IK WorkDesk

**Pembaruan 20 September 2026:** enam temuan fungsional dan tiga temuan antarmuka di bawah sudah diperbaiki. Verifikasi gabungan: **213/213 tes lulus**. Lihat [catatan perbaikan dan batas verifikasi](FIXES.md). Isi berikut dipertahankan sebagai bukti kondisi sebelum perbaikan.

Audit browser dan pengujian awal: 18 September 2026. Pemeriksaan lanjutan: 20 September 2026. Target browser: http://127.0.0.1:8000. Kode yang diaudit adalah working tree, termasuk perubahan lokal yang sudah ada sebelum audit.

## Kesimpulan

**Aplikasi belum bersih dari masalah fungsional.** Semua 24 halaman menu aktif pada panel dapat dibuka dengan sesi administrator. Namun, uji transaksi dan akses menemukan **6 masalah utama yang dapat direproduksi**, serta **3 masalah antarmuka**. Tidak ditemukan bukti kebocoran data pada skenario otorisasi yang dijalankan; ini bukan jaminan bahwa seluruh kemungkinan celah keamanan telah tercakup.

Prioritas perbaikan: konsistensi hasil attendance, izin baca detail laporan, kemudian validasi form Department, Employee, dan Attendance Result.

## Metode dan hasil pengujian

| Lapisan | Hasil |
|---|---|
| Browser, sesi admin yang sudah login | 24 halaman menu panel, 18 form tambah, beberapa detail/edit dan relasi KBLI, dua kalender, laporan per periode, dashboard lama, legacy Departments, dan Profile diperiksa |
| Tes bawaan repository | **145 lulus, 511 assertions** |
| Probe audit tambahan | **50 tes: 43 lulus, 7 tidak lulus, 447 assertions**; 7 tes tersebut merepresentasikan 6 masalah utama karena tampilan dan pencarian Employee ID diuji terpisah |
| Pemeriksaan lanjutan 20 September | **6 tes lulus, 68 assertions**: filter dan total laporan, scope antar-department, CRUD KBLI, duplikat KBLI persis, dua file wajib, upload XLSX sampai hasil diproses |
| Verifikasi gabungan terakhir, 20 September | **56 probe: 49 lulus, 4 assertion gagal, 3 exception database; 515 assertions.** Ketujuh probe yang tidak lulus konsisten dengan A01–A06. Bersama tes bawaan, total 201 tes telah dijalankan (194 lulus, 7 tidak lulus) |
| Pencarian/sort tabel | 20 komponen daftar diuji melalui Livewire, termasuk setiap kolom sortable; uji umum memakai kata tanpa hasil. Uji pencarian Employee ID memakai record nyata sintetis dan menemukan kegagalan |
| Transaksi | Create/edit pada 9 master, delete pada 7 master yang menyediakan action; pembuatan kategori request, Service Desk → Work Log, aktivitas harian, reminder, booking ruang/kendaraan, serta create/edit akun |
| Validasi positif | Koordinat/radius tidak valid, kapasitas nol, jam operasional terbalik, tanggal proyek terbalik, email tidak valid, dan aktivitas tumpang tindih ditolak |
| Ekspor | Endpoint download benar-benar menghasilkan XLSX; kedua sheet, kode pegawai dengan nol di depan, dan nilai durasi diverifikasi |
| Otorisasi | Guest ditolak dari halaman index panel; user tanpa izin ditolak dari menu admin/attendance/master; employee nonaktif ditolak; tes bawaan mencakup role, scope department, attachment private, approval, dan assignment |

Pengujian yang menyimpan/mengubah/menghapus record memakai **SQLite `:memory:` dengan Notification/Mail fake**, bukan PostgreSQL aplikasi. Tes bawaan memakai konfigurasi testing repository. Browser menggunakan PostgreSQL lokal untuk navigasi dan pemeriksaan tampilan; tidak ada transaksi bisnis yang disimpan, dihapus, diproses ulang, atau disetujui melalui browser. Tidak ada email nyata yang dikirim. Source aplikasi dan `.env` tidak diubah oleh audit ini.

## Cakupan per menu

“Lulus skenario” berarti skenario yang disebutkan lulus, bukan seluruh kemungkinan variasi bisnis.

| No. | Menu panel | Pemeriksaan | Hasil |
|---|---|---|---|
| 1 | Dashboard | Ringkasan, daftar terbaru, tautan; tes render/scope bawaan | Terbuka; lulus skenario |
| 2 | Departments | Daftar/form, search/sort, create/edit/delete, kode duplikat | **A03**, **U01 terkait Employees** |
| 3 | Employees | Daftar/form, search/sort, create/edit/delete, email invalid, akun duplikat | **A04**, **U01** |
| 4 | User Management | Daftar/form, search/sort, create/edit sintetis, password edit kosong, role/access tests | Lulus skenario |
| 5 | Permit & KBLI | Daftar/form/edit, relasi KBLI termuat, company dan KBLI CRUD, penolakan duplikat persis; tes permit bawaan | Lulus skenario; observasi kualitas data O01 |
| 6 | Projects | Daftar/form, search/sort, create/edit, tanggal terbalik | Lulus; tidak menyediakan action delete pada halaman yang diuji |
| 7 | Activity Categories | Daftar/form, search/sort, create/edit | Lulus; tidak menyediakan action delete pada halaman yang diuji |
| 8 | Report Center | Periode, tautan Activity Check/Total Jam, akses viewer | **A01** |
| 9 | Upload Period | Daftar/form/edit, search/sort, dua file wajib, upload dua XLSX sintetis sampai pemrosesan; reconciliation/rollback pada tes bawaan | Lulus skenario; akses halaman results terkait **A01** |
| 10 | Report Results | Daftar/form, search/sort, pencarian ID, nilai negatif, referensi import | **A02, A05, A06** |
| 11 | Work Locations | Daftar/form, search/sort, CRUD, batas koordinat/radius | Lulus skenario |
| 12 | Meeting Room Calendar | Tampilan mingguan, Next week, klik Available | Tanggal, ruangan, jam 09:00–10:00 terisi benar pada form booking |
| 13 | Meeting Bookings | Daftar/form, search/sort, create sintetis; overlap, kapasitas, complete/cancel pada tes bawaan | Lulus skenario |
| 14 | Meeting Rooms | Daftar/form, search/sort, CRUD, kapasitas/jam invalid | Lulus skenario |
| 15 | Vehicle Calendar | Tampilan mingguan dan Next week | Berfungsi; dataset lokal belum memiliki kendaraan aktif |
| 16 | Vehicle Bookings | Daftar/form, search/sort, create sintetis; aturan jadwal/akses pada tes bawaan | Lulus skenario; browser hanya empty state |
| 17 | Vehicles | Daftar/form, search/sort, CRUD sintetis, kapasitas/jam invalid | Lulus skenario |
| 18 | Reminders | Daftar/form/detail, search/sort, create sintetis; alarm/sumber task pada tes bawaan | Lulus skenario; pengiriman SMTP nyata tidak diuji |
| 19 | Service Desk | Daftar/form/detail, pilihan Legal memunculkan field wajib, search/sort, create → Work Log, tes workflow | Lulus skenario |
| 20 | Request Categories | Daftar/form, search/sort, create kategori; tes workflow/permit | Lulus skenario |
| 21 | Work Logs | Daftar/detail, search/sort, status menunggu CBO; tes PIC, claim, status, completion, approval | Lulus skenario |
| 22 | Aktivitas Harian | Daftar/form, search/sort, create/durasi/overlap; tes kepemilikan | Lulus skenario; dataset browser kosong |
| 23 | Task Categories | Daftar/form, search/sort, CRUD sintetis | Lulus skenario |
| 24 | Laporan Aktivitas | Daftar, panel filter, search/sort; filter periode + user terhadap data sintetis, total durasi sesuai filter, percobaan filter user dari department lain | Lulus skenario; dataset browser kosong |

Tambahan: `/dashboard`, `/admin/departments`, dan `/profile` dapat dibuka. Role Management serta menu attendance legacy yang dinonaktifkan diperiksa lewat tes akses, bukan dianggap menu aktif yang harus dimunculkan.

## Temuan utama

### A01 — Viewer attendance mendapat 403 dari tautan Report Center

**Dampak: sedang; prioritas P2.** Pengguna yang berhak membaca laporan tidak bisa menyelesaikan alur baca detail.

Reproduksi: buat user dengan hanya `attendance.view`, buka `/panel/attendance-report-center` (200), lalu `/panel/attendance-imports/{id}/results` (403). Record import sintetis berstatus `processed`.

Akar masalah: `AttendanceReportCenter::canAccess()` menerima `attendance.view`, tetapi `AttendanceImportResource::canViewAny()`/`canView()` mensyaratkan `attendance.upload` atau `attendance.manage`. Tautan Activity Check dan Total Jam Kerja menuju resource ini.

Lokasi: `app/Filament/Pages/AttendanceReportCenter.php:40`; `app/Filament/Resources/AttendanceImports/AttendanceImportResource.php:27,85,90`.

Perbaikan: berikan akses baca results berdasarkan `attendance.view`, dengan guard terpisah untuk upload/edit/delete. **Jangan memberikan izin upload/manage kepada viewer hanya untuk mengatasi 403.**

Bukti: `test_attendance_viewer_can_follow_report_center_result_link` gagal dengan expected 200, actual 403.

### A02 — Report Results salah membaca dan mencari Employee ID

**Dampak: sedang; prioritas P2.** Kode pegawai yang ada pada hasil processor tidak tampil dan tidak dapat ditemukan lewat pencarian kode pada menu Report Results.

Reproduksi: simpan hasil dengan `employee_code = AUDIT-EMP-987`; buka Report Results. Kode tersebut tidak terlihat. Cari `AUDIT-EMP-987`; record tidak ditemukan walaupun ada.

Akar masalah: tabel memakai `employee_id`, sedangkan processor menulis `employee_code`. Kolom “Cek Waktu” juga masih memakai `time_check`, sementara processor menulis `duration_text`; ketidaksesuaian kedua ini teridentifikasi dari kode. Halaman detail per periode dan ekspor memakai field baru, sehingga antarhalaman berpotensi menampilkan informasi berbeda.

Lokasi: `app/Filament/Resources/AttendanceResults/Tables/AttendanceResultsTable.php:29,68`; `app/Services/AttendanceReportProcessor.php:191,214`.

Perbaikan: selaraskan kolom dan pencarian dengan field processor; uji record hasil import pada list, detail, dan ekspor.

Bukti: `test_report_results_show_processor_employee_code` dan `test_report_result_employee_code_search_finds_processed_rows` gagal. Ekspor XLSX dengan kode `00123` justru lulus.

### A03 — Kode Department duplikat memicu exception database

**Dampak: sedang; prioritas P2.** Kesalahan input umum berubah menjadi kegagalan server, bukan pesan validasi.

Reproduksi: buat Department dengan kode `AUDIT`, kemudian buat Department lain dengan kode yang sama. Livewire melempar `UNIQUE constraint failed: departments.code`.

Lokasi: `app/Filament/Resources/Departments/Schemas/DepartmentForm.php:17`; form hanya `required()`, sedangkan migration menetapkan unique.

Perbaikan: `unique(ignoreRecord: true)` dan batas panjang sesuai database; uji duplicate pada create/edit. Record lama tidak tertimpa dalam probe.

Bukti: `test_duplicate_department_is_validation_error_not_server_error` menghasilkan QueryException.

### A04 — Employee dapat memilih akun yang sudah terhubung ke Employee lain

**Dampak: sedang; prioritas P2.** Penyimpanan gagal dengan exception relasi unik.

Reproduksi: buat Employee A terhubung ke user U, lalu isi form Employee B dengan user U. Opsi tidak memfilter akun yang sudah dipakai dan form tidak memberi validasi unique; database menolak `employees.user_id`.

Lokasi: `app/Filament/Resources/Employees/Schemas/EmployeeForm.php:18`.

Perbaikan: batasi pilihan akun yang belum terhubung, izinkan akun milik record saat edit, dan tambahkan validasi unique backend. Jangan hanya memfilter dropdown.

Bukti: `test_duplicate_employee_account_is_validation_error_not_server_error` menghasilkan QueryException. Constraint database tetap melindungi keunikan relasi.

### A05 — Attendance Result menerima durasi kerja negatif

**Dampak: sedang; prioritas P2 (integritas data).** Operator dengan izin manage dapat menyimpan hasil yang secara bisnis tidak valid.

Reproduksi pada form Livewire: `employee_name = Audit`, `work_minutes = -60`, lalu create. Penyimpanan berhasil tanpa error validasi. Hasil juga dapat dibuat tanpa periode/tanggal karena field tersebut opsional.

Lokasi: `app/Filament/Resources/AttendanceResults/Schemas/AttendanceResultForm.php:30`.

Perbaikan: tentukan apakah hasil processor memang boleh dibuat/diubah manual. Jika ya, validasi integer non-negatif, periode dan tanggal yang relevan, serta konsistensi jam/status. Jika tidak, jadikan hasil read-only dan sediakan alur koreksi terkontrol.

Bukti: `test_attendance_negative_duration_is_rejected` gagal karena komponen tidak mempunyai error.

### A06 — Referensi import tidak valid pada Attendance Result memicu exception

**Dampak: sedang; prioritas P2.** Field ID numerik bebas dapat mengirim foreign key yang tidak ada dan menimbulkan error server.

Reproduksi: isi `attendance_import_id = 999999`, nama pegawai, dan durasi 60; create memicu `FOREIGN KEY constraint failed` pada database uji.

Lokasi: `app/Filament/Resources/AttendanceResults/Schemas/AttendanceResultForm.php:19`.

Perbaikan: gunakan pilihan periode yang berlabel dan validasi `exists`; pertimbangkan hasil read-only seperti A05.

Bukti: `test_attendance_invalid_import_reference_is_validation_error` menghasilkan QueryException.

## Temuan antarmuka dan kualitas data

| ID | Dampak | Bukti dan rekomendasi |
|---|---|---|
| U01 | Rendah/P3 | Employees menampilkan kolom `Department id` dengan angka, bukan nama department. Gunakan relasi `department.name`. Lokasi `app/Filament/Resources/Employees/Tables/EmployeesTable.php:20`. Terlihat langsung di browser. |
| U02 | Rendah/P3 | Menu Transactions, Reports, Settings pada dashboard lama semuanya menunjuk `#`. Tidak membuka fitur yang dijanjikan label. Hubungkan ke fitur yang ada atau sembunyikan sampai tersedia. Lokasi `resources/views/partials/sidebar.blade.php`. |
| U03 | Rendah/P3 | Sapaan dashboard lama menampilkan karakter rusak pengganti emoji, terlihat di browser dan literal template. Simpan ulang teks UTF-8 atau gunakan ikon SVG. Lokasi `resources/views/admin/dashboard.blade.php:17`. |

**O01, observasi kualitas data:** daftar KBLI pada satu company memuat kode 42911 dan 43291 berulang dengan nama yang berbeda hanya kapitalisasinya. Ini bukti data yang perlu ditinjau, bukan kesimpulan bahwa semua kode KBLI berulang dilarang. Pertimbangkan normalisasi huruf/spasi sesuai aturan bisnis dan deduplikasi dengan mempertahankan histori referensi. Tidak ada data yang digabung/dihapus selama audit.

## Bukti yang disimpan dan cara menjalankan ulang

- `MenuAuditTest.php`: probe audit mandiri; sengaja ditempatkan di folder output, tidak ditambahkan ke suite aplikasi. Tes yang gagal menjadi bukti sebelum perbaikan.
- `probes.xml` / `probes.txt`: hasil akhir 50 probe, bukan hasil percobaan awal.
- `FollowUpAuditTest.php`, `followup.xml`, dan `followup.txt`: 6 pemeriksaan tambahan yang seluruhnya lulus pada 20 September.
- `final-audit.xml` / `final-audit.txt`: verifikasi gabungan terakhir seluruh 56 probe setelah guard database dipasang sebelum migration.
- `summary.json`: ringkasan jumlah tes dan enam temuan utama.
- `../audit-2026-09-18-tests.xml` / `../audit-2026-09-18-tests.txt`: hasil 145 tes bawaan.
- `../audit-2026-09-18-routes.json`: inventaris route saat audit.

Jalankan dari root repository, dengan `phpunit.xml` yang menetapkan SQLite `:memory:`:

```powershell
php vendor/phpunit/phpunit/phpunit
php vendor/phpunit/phpunit/phpunit output/audit-2026-09-18/MenuAuditTest.php
php vendor/phpunit/phpunit/phpunit output/audit-2026-09-18/FollowUpAuditTest.php
```

Kedua kelas probe memeriksa jenis database dan nama `:memory:` sebelum RefreshDatabase menjalankan migration, serta memeriksanya lagi pada setup. Jangan mengganti konfigurasi pengujian ke database bisnis.

## Batas pemeriksaan

Audit ini adalah pemeriksaan fungsional dan akses pada skenario yang dijalankan, bukan sertifikasi keamanan atau pengujian penetrasi menyeluruh. Transaksi otomatis menggunakan SQLite; exception yang dicatat berasal dari database uji, bukan klaim bahwa error tersebut sudah ditimbulkan pada data PostgreSQL asli.

Tidak dilakukan pengiriman SMTP nyata, pengujian worker/scheduler jangka panjang, load/concurrency test PostgreSQL, pemindaian CVE dependency, verifikasi backup/restore, atau pengujian seluruh ukuran layar/browser. Upload/processor, attachment, dan workflow kompleks terutama diuji melalui suite otomatis, bukan setiap variasi melalui browser. Data Vehicle, Projects, dan Daily Activity di browser masih kosong; alur simpan diuji dengan data sintetis.

Setelah perbaikan, jalankan kembali tes bawaan dan seluruh probe, lalu ulangi enam reproduksi utama pada staging PostgreSQL dengan akun admin serta viewer attendance.
