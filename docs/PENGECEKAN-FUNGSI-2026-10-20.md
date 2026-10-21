# Pemeriksaan fungsi IK WorkDesk, 20 Oktober 2026

Pemeriksaan ini menguji aksi penyimpanan, perubahan, penghapusan, validasi, perhitungan, impor berulang, dan pencabutan akses. Hasil halaman terbuka pada audit sebelumnya tidak dipakai sebagai bukti fungsi simpan/edit bekerja.

Ditambahkan 29 pengujian integrasi untuk menutup kekurangan cakupan pada lima master dan LTRO. **PostgreSQL: 25 lulus, 4 gagal. SQLite memory: 23 lulus, 6 gagal.** Empat masalah LTRO terulang di PostgreSQL; dua kegagalan tambahan hanya muncul di SQLite. Bug penghitung nomor aset dari audit sebelumnya juga masih belum diperbaiki.

Tidak dilakukan perubahan pada source perilaku aplikasi, izin akun nyata, atau data operasional. Berkas yang ditambahkan berupa pengujian dan laporan. Test PostgreSQL memakai database sementara dengan nama acak; transaksi dan database tersebut sudah dibersihkan.

## Fungsi per modul

Hasil baseline berikut berasal dari suite 442 test yang sudah dijalankan pada pemeriksaan sebelumnya. Tidak ada perubahan source perilaku aplikasi di antara kedua pemeriksaan. Pengujian tambahan dijalankan ulang pada sesi pemeriksaan fungsi ini.

| Modul | Alur yang tercakup | Hasil dan batas |
| --- | --- | --- |
| Akun dan autentikasi | Login, logout, profil, reset/ubah password, verifikasi email | 25 test baseline lulus; mail pengujian tidak dikirim ke pengguna nyata |
| Service Desk dan Work Logs | Buat request, manpower HR, penugasan PIC, claim, persetujuan Legal, kolaborasi, penyelesaian, batas department dan perubahan status | 73 test baseline lulus; tidak membuktikan setiap kombinasi data bisnis |
| ATK | Request, penyerahan sebagian, penerimaan, stok department, pemakaian, penolakan saldo negatif, impor dan ekspor | 29 test baseline lulus |
| Attendance dan regresi master | Proses upload, hasil, validasi nilai, filter, employee/account unik, kode department unik | 10 test Attendance dan 12 test regresi gabungan lulus |
| Booking meeting dan kendaraan | Pembuatan booking, jadwal bentrok, status, akses dan pembatalan sesuai cakupan tes | 19 test baseline lulus |
| Surat dan penomoran | Draft, issuance, preview tanpa konsumsi nomor, sequence per scope/tahun, keutuhan surat terbit, impor project letter | 29 test baseline lulus; dua migration data APCA pada database lokal masih pending sebagaimana laporan sebelumnya |
| Aktivitas Harian | Perhitungan durasi, penolakan waktu tumpang tindih, sumber kerja, pembatasan data sendiri | 4 dari 7 test baseline lulus; tiga fixture pembuka halaman masih memakai izin lama |
| User Management dan akses | Simpan/muat checklist, pemberian dan pencabutan izin, batas master terpisah dan hierarki role | 26 test baseline lulus; temuan LTRO di bawah menunjukkan cakupan lama belum menjangkau semua komponen legacy |
| Dashboard | Pilihan ringkasan, pembatasan data, penyimpanan preferensi sesuai cakupan tes | 11 test baseline lulus |
| Chat Global | Scope perusahaan, pesan, lampiran dan batas akses sesuai cakupan tes | 11 test baseline lulus; layanan realtime eksternal tidak diuji langsung |
| Tanya Aku | Pilihan provider, kunci terenkripsi, kuota token, riwayat terpisah, respons gagal dan pencabutan provider | 30 test baseline lulus menggunakan respons HTTP tiruan; koneksi provider nyata tidak diuji |
| Reminder | Sumber Work Task, pengingat email, batas akses dan jadwal sesuai cakupan tes | 9 test baseline lulus; antrean notifikasi operasional lokal tetap belum diproses |
| Infrastruktur IT | Simpan perangkat, pemeriksaan/finalisasi, snapshot riwayat, penghapusan yang diizinkan, ekspor dan batas akses | 124 dari 125 test baseline lulus pada SQLite dan PostgreSQL; penghitung aset setelah koreksi manual gagal |
| Lima master yang diperluas | Work Locations, Work Projects, Activity Categories, Task Categories, Permit Companies | Seluruh 17 test tambahan lulus pada SQLite dan PostgreSQL |
| LTRO | Input/edit MTTR, hitung downtime, impor ulang, daily readings dan rata-rata, saldo oli, periode availability, simpan/hapus, validasi dan pencabutan izin | PostgreSQL 8 dari 12 test tambahan lulus; rincian empat masalah di bawah |

Sebanyak 22 test keamanan baseline dan 4 test konfigurasi/dasar juga lulus. Ini bukan pernyataan bahwa semua kemungkinan fungsi, data, atau integrasi eksternal bebas masalah.

## Temuan fungsi pada PostgreSQL

### 1. Tinggi: izin LTRO yang dicabut masih dapat dipakai dari halaman terbuka

Pengujian menggunakan siklus HTTP nyata di dalam aplikasi uji: pengguna diberi `ltro.view`, membuka `/ltro/availability`, lalu izinnya dicabut. Membuka URL tersebut kembali menghasilkan 403. Namun update Livewire memakai snapshot halaman yang sudah terbuka masih menghasilkan **200 dan membuat 30 record availability**.

Sumber yang relevan: [RequireLtroAccess](../app/Http/Middleware/RequireLtroAccess.php), route LTRO di [web.php](../routes/web.php), serta `createPeriod()` dan aksi lain pada [AvailabilityLtro1b](../app/Livewire/AvailabilityLtro1b.php). Middleware khusus ini tidak didaftarkan sebagai persistent middleware Livewire, dan aksi komponen tidak memeriksa ulang izin tersebut. Pemeriksaan pencabutan menggunakan endpoint update Livewire dengan header yang diperlukan, bukan hanya pemanggilan method komponen secara langsung.

Reproduksi tersimpan pada `test_revoked_ltro_permission_blocks_a_real_livewire_update_request`. Pengujian ini membuktikan kasus pencabutan izin untuk pembuatan periode availability. Aksi lain pada komponen LTRO perlu diperiksa dalam perbaikan; hasil ini tidak diklaim sebagai pengujian lengkap semua aksi tersebut.

### 2. Tinggi: form edit MTTR gagal menyelesaikan relasi kategori

Pembuatan event melalui form berhasil. Ketika event yang sudah tersimpan dibuka untuk diedit, terjadi exception **`The relationship [category] does not exist on the model [App\Models\LtroMttrRecord]`**. Dengan demikian halaman create yang dapat dibuka sebelumnya belum membuktikan alur edit berfungsi.

[LtroMttrRecord](../app/Models/LtroMttrRecord.php) memiliki kolom teks `category` dan metode relasi bernama `category()`. [LtroMttrRecordResource](../app/Filament/Resources/LtroMttrRecords/LtroMttrRecordResource.php) memakai `relationship('category', 'name')` serta `category.name`. Nama atribut dan relasi yang bertabrakan perlu dipisahkan tanpa membuang data kategori historis.

Reproduksi: `test_existing_mttr_event_can_be_edited_through_the_form`. Test pembuatan event dipisah dan lulus, agar kegagalan edit tidak disamakan dengan kegagalan create.

### 3. Sedang: impor MTTR menerima urutan waktu yang tidak valid

File uji berisi shutdown 19 Oktober 2026 pukul 08:00 dan running pada tanggal yang sama pukul 07:00. Impor tetap menyimpan satu event, dengan downtime dari file sebesar 150 menit. Form MTTR standar menolak running sebelum shutdown, tetapi jalur impor tidak menerapkan validasi yang sama.

Sumber: [LtroMttrImportController](../app/Http/Controllers/LtroMttrImportController.php), pembentukan `$shutdown`, `$running`, dan penyimpanan `$values`. Reproduksi: `test_import_does_not_store_an_event_with_running_before_shutdown`.

Impor valid dan impor ulang sumber/baris yang sama sudah diuji dan lulus: event diperbarui tanpa membuat duplikat. Temuan ini spesifik pada validasi isi waktu impor.

### 4. Sedang: Availability mengubah input angka yang tidak valid menjadi nol

Nilai `not-a-number` pada `flow_rate` diterima oleh aksi simpan dan disimpan sebagai **`0.00`**, tanpa error validasi. Data yang salah menjadi terlihat seperti pengukuran nol yang sah.

Sumber: `save()` dan `nullableNumber()` pada [AvailabilityLtro1b](../app/Livewire/AvailabilityLtro1b.php). Reproduksi: `test_availability_does_not_silently_convert_invalid_numeric_input_to_zero`. Nilai desimal valid `12.5` dan `98.5` berhasil tersimpan dengan presisi dua desimal pada test terpisah.

## Temuan khusus SQLite

Dua alur berikut lulus pada PostgreSQL tetapi gagal pada SQLite memory:

- **Menyimpan ulang Daily Report unit/tanggal yang sama** mencoba INSERT baru dan terkena unique constraint `unit_code, report_date`.
- **Membuat ulang periode availability yang sama** mencoba INSERT duplikat dan terkena unique constraint `period_start, report_date`.

Model dengan cast `date` menyimpan nilai SQLite sebagai `YYYY-MM-DD 00:00:00`, sedangkan `firstOrNew`/`firstOrCreate` mencari string tanggal `YYYY-MM-DD`. Data lama tidak ditemukan oleh pencarian string tersebut. Ini masalah kompatibilitas SQLite pada alur simpan ulang, bukan bukti kegagalan PostgreSQL yang sedang dipakai aplikasi lokal.

Reproduksi: `test_daily_readings_averages_oil_balance_and_edit_round_trip` serta `test_availability_period_creation_is_idempotent_and_preserves_period_boundaries`.

## Fungsi tambahan yang lulus

Seluruh 17 test master tambahan menjalankan alur berikut:

- Create dan edit melalui form Livewire pada lima master.
- Penolakan pengguna tanpa izin pada form create dan edit kelima master, dengan record tetap sama.
- Hapus Work Location dan Permit Company yang belum digunakan melalui aksi yang tersedia.
- Penolakan duplikat kode project, activity category, dan company saat create maupun edit.
- Penolakan latitude, longitude, dan radius di luar batas form; tidak ada record invalid tersimpan.
- Beberapa titik GPS dalam satu kelompok nama lokasi tetap dapat disimpan.
- Tanggal akhir project sebelum tanggal mulai ditolak.

Pada PostgreSQL, fungsi LTRO yang lulus meliputi create MTTR; penolakan tanggal running terbalik dan downtime negatif pada form; perhitungan serta penyimpanan downtime dari monitoring; impor ulang tanpa duplikat; simpan/edit Daily Report dengan reading, rata-rata, total jam, dan saldo oli yang benar; penolakan unit atau running hours tidak valid; periode availability yang idempotent; simpan nilai desimal; penomoran ulang setelah satu baris dihapus; serta penghapusan satu periode tanpa menghapus periode lain. Beberapa aksi tersebut merupakan satu rangkaian test, bukan masing-masing satu test terpisah.

## Perubahan dan bukti

Test baru: [MasterDataFunctionalFlowTest](../tests/Feature/MasterDataFunctionalFlowTest.php), [LtroFunctionalFlowTest](../tests/Feature/LtroFunctionalFlowTest.php), dan basis database terisolasi [FunctionalDatabaseTestCase](../tests/Support/FunctionalDatabaseTestCase.php).

Artefak berada di [output/function-review-20261020](../output/function-review-20261020/):

- `functions-postgres.xml`: 29 test, 392 assertion, 25 lulus, 3 assertion failure, 1 error render edit.
- `functions-sqlite.xml`: 29 test, 327 assertion, 23 lulus, 3 assertion failure, 3 error.
- `functions-postgres.txt`: hasil runner dan konfirmasi pembersihan database sementara serta pemeriksaan database utama.
- `baseline-functional-coverage.json`: pemetaan 442 test sebelumnya ke modul dan nama skenario; bukan hasil menjalankan ulang seluruh suite pada sesi ini.

Sintaks PHP dan Pint untuk tiga berkas test baru lulus. `git diff --check` juga lulus. Test yang menemukan bug tetap mempertahankan ekspektasi perilaku yang benar; tidak diubah menjadi hijau dengan menerima input rusak atau membuka hak akses.

Prioritas perbaikan: pencabutan izin LTRO, form edit MTTR, penghitung nomor aset dari laporan sebelumnya, keseragaman validasi impor dan availability, lalu kompatibilitas tanggal SQLite. SMTP, Reverb, dan provider AI nyata tetap memerlukan pengujian integrasi terpisah; tidak ada pesan atau email operasional dikirim pada pemeriksaan ini.
