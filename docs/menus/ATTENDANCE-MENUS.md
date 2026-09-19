# Menu Attendance dan Laporan Lokasi

Kelompok Attendance mengolah file attendance dan work hour menjadi hasil pemeriksaan aktivitas, lokasi, checkout, dan total jam kerja.

## 1. Attendance Report Center

### Fungsi

Report Center adalah halaman penghubung untuk memilih periode upload dan membuka Activity Check atau Total Jam Kerja.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Logic halaman dan daftar periode | `app/Filament/Pages/AttendanceReportCenter.php` |
| Tampilan halaman | `resources/views/filament/pages/attendance-report-center.blade.php` |
| Model periode import | `app/Models/AttendanceImport.php` |

### Data yang digunakan

Page membaca maksimal 100 Attendance Import terbaru, termasuk period name, nama file, status, notes, processed at, dan created at.

### Link penting

- Activity Check mengarah ke hasil Attendance Import.
- Total Jam Kerja mengarah ke bagian Work Hour Summary.
- Upload mengarah ke halaman create Attendance Import.

### Permission

Menu memerlukan `attendance.view`.

### Hal yang perlu hati-hati

- `attendanceImportId` harus menunjuk record yang boleh dilihat user.
- URL anchor harus tetap sesuai id pada halaman results.
- Jangan menampilkan path file storage secara langsung.

### Test yang disarankan

- user dengan/tanpa `attendance.view`;
- kondisi belum ada import;
- pemilihan periode;
- URL Activity dan Work Hour.

## 2. Attendance Imports

### Fungsi

Mengunggah file attendance dan, bila tersedia, file work hour untuk satu periode, lalu memproses keduanya menjadi record hasil.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource, permission, query | `app/Filament/Resources/AttendanceImports/AttendanceImportResource.php` |
| Form upload | `AttendanceImports/Schemas/AttendanceImportForm.php` |
| Tabel dan action Process | `AttendanceImports/Tables/AttendanceImportsTable.php` |
| Create/edit | `AttendanceImports/Pages/CreateAttendanceImport.php` dan `EditAttendanceImport.php` |
| Halaman hasil | `AttendanceImports/Pages/ViewAttendanceImportResults.php` |
| Model | `app/Models/AttendanceImport.php` |
| Processor | `app/Services/AttendanceReportProcessor.php` |

### Field form

| Field | Fungsi |
| --- | --- |
| `uploaded_by_user_id` | user yang melakukan upload |
| `period_name` | label periode |
| `attendance_file_path` | file attendance |
| `work_hour_file_path` | file work hour opsional/terkait |
| `status` | status proses |
| `notes` | catatan import |

### Kolom tabel

Period, nama file attendance, nama file work hour, Status, Processed At, dan Created At.

### Action

- `view_results`: membuka hasil import;
- `process`: menjalankan processor.

### Permission

- `attendance.upload`: upload/create;
- `attendance.manage`: mengelola dan memproses;
- `attendance.view`: melihat hasil sesuai aturan tabel.

### Hal yang perlu hati-hati

- File upload harus divalidasi dan disimpan sesuai kebijakan private storage.
- Proses ulang dapat menggandakan data jika processor tidak idempotent.
- Jangan mengubah mapping kolom file tanpa sampel dan regression test.
- Work Location aktif memengaruhi hasil location check.

### Test yang disarankan

- upload format valid/tidak valid;
- processing sukses/gagal;
- process ulang;
- authorization upload/process/view;
- file private tidak dapat diakses langsung.

## 3. Work Hour Imports

### Fungsi

Jalur khusus untuk mengunggah file total jam kerja pada sebuah periode Attendance Import.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/WorkHourImports/WorkHourImportResource.php` |
| Form | `WorkHourImports/Schemas/WorkHourImportForm.php` |
| Tabel | `WorkHourImports/Tables/WorkHourImportsTable.php` |
| Pages | `WorkHourImports/Pages` |
| Model periode | `app/Models/AttendanceImport.php` |
| Model hasil | `app/Models/WorkHourRecord.php` |
| Processor | `app/Services/AttendanceReportProcessor.php` |

### Field form

`uploaded_by_user_id`, `period_name`, hidden attendance path, `work_hour_file_path`, `status`, dan `notes`.

### Kolom tabel

Period, file work hour, Status, Processed At, dan Created At.

### Action

`view_results` dan `process`.

### Hal yang perlu hati-hati

- Resource ini memakai model `AttendanceImport`, bukan model khusus import terpisah.
- Attendance path dibuat hidden agar satu record periode dapat menampung kedua sumber.
- Perubahan processor dapat memengaruhi Attendance Imports dan Work Hour Imports sekaligus.

### Test yang disarankan

- upload hanya file work hour;
- parsing employee dan periode;
- total menit/jam;
- process ulang;
- hubungan ke Attendance Import.

## 4. Work Hour Records

### Fungsi

Menampilkan hasil ringkasan total jam kerja yang sudah diproses.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource read-only dan query | `app/Filament/Resources/WorkHourRecords/WorkHourRecordResource.php` |
| Tabel | `WorkHourRecords/Tables/WorkHourRecordsTable.php` |
| Model | `app/Models/WorkHourRecord.php` |

### Kolom

- periode import;
- employee id dari raw data;
- employee name;
- work hours text;
- work minutes;
- period name dari raw data;
- created at.

### Karakteristik

- Resource tidak tampil sebagai menu utama.
- Form kosong karena data bersifat read-only.
- Query memilih record summary yang `work_date`-nya kosong.

### Hal yang perlu hati-hati

- Jangan menambahkan edit tanpa aturan koreksi data yang jelas.
- `raw_data` adalah struktur sumber; akses key harus aman bila data tidak lengkap.
- Work minutes adalah sumber perhitungan, sedangkan work hours text adalah tampilan.

### Test yang disarankan

- parsing raw data yang tidak lengkap;
- filter summary vs daily record;
- format jam dan menit;
- scope import.

## 5. Attendance Results

### Fungsi

Menyimpan hasil pemeriksaan attendance per employee dan tanggal.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan permission | `app/Filament/Resources/AttendanceResults/AttendanceResultResource.php` |
| Form | `AttendanceResults/Schemas/AttendanceResultForm.php` |
| Tabel | `AttendanceResults/Tables/AttendanceResultsTable.php` |
| Model | `app/Models/AttendanceResult.php` |
| Processor | `app/Services/AttendanceReportProcessor.php` |

### Field data

| Kelompok | Field |
| --- | --- |
| Sumber | `attendance_import_id`, employee id/name, attendance date |
| Waktu | `clock_in`, `clock_out`, `work_minutes`, `expected_checkout` |
| Lokasi | `location_gps_name`, `distance_meters` |
| Status mentah | `clock_in_status`, `location_status`, `checkout_status`, `work_hour_status` |
| Hasil akhir | `final_status`, `notes` |

### Kolom tambahan tabel

Tabel juga menampilkan `location_check`, `time_check`, `checkout_check`, Created At, dan Updated At.

### Permission

- `attendance.view` untuk melihat;
- `attendance.manage` untuk pengelolaan sesuai aturan Resource.

### Hal yang perlu hati-hati

- Status akhir biasanya hasil gabungan beberapa check.
- Mengubah label status tidak sama dengan mengubah algoritma processor.
- Distance menggunakan meter dan terkait Work Location.
- Koreksi manual harus tetap menyimpan histori atau alasan jika nanti diimplementasikan.

### Test yang disarankan

- clock in terlambat/tepat waktu;
- lokasi valid/tidak valid;
- checkout sesuai/tidak sesuai;
- work hour memenuhi/tidak memenuhi;
- kombinasi final status.

## 6. Location Reports

### Fungsi

Jalur upload dan laporan yang berfokus pada pemeriksaan lokasi attendance.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/LocationReports/LocationReportResource.php` |
| Form | `LocationReports/Schemas/LocationReportForm.php` |
| Tabel | `LocationReports/Tables/LocationReportsTable.php` |
| Pages | `LocationReports/Pages` |
| Model periode | `app/Models/AttendanceImport.php` |
| Work Location | `app/Models/WorkLocation.php` |
| Processor | `app/Services/AttendanceReportProcessor.php` |

### Field form

`uploaded_by_user_id`, `period_name`, `attendance_file_path`, hidden work hour path, `status`, dan `notes`.

### Kolom tabel

Period, attendance file path, Status, Processed At, dan Created At.

### Action

`view_results` dan `process`.

### Hal yang perlu hati-hati

- Resource memakai `AttendanceImport` yang sama dengan jalur upload lain.
- Hanya file attendance yang terlihat; work hour path dibuat hidden.
- Hasil lokasi bergantung pada master Work Locations saat proses dijalankan.
- Jangan membuka raw path file sebagai link publik.

### Test yang disarankan

- mapping nama GPS;
- radius dan distance;
- lokasi flexible;
- Work Location nonaktif;
- authorization upload dan report.

## Alur data Attendance

```text
File attendance/work hour
    |
    v
AttendanceImport
    |
    v
AttendanceReportProcessor
    |
    +--> AttendanceResult
    +--> WorkHourRecord
    |
    v
Report Center / Location Report / Work Hour Summary
```

Jika processor diubah, test semua output pada diagram tersebut.
