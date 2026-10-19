# Hasil Tahap 1 dan Tahap 2 Infrastruktur IT

Tanggal: 16 Oktober 2026. Status: selesai untuk review, belum diterapkan pada database aplikasi.

Spesifikasi final ada di [IT-INFRASTRUCTURE-SPEC.md](IT-INFRASTRUCTURE-SPEC.md). Anti-slop mengikuti pilihan pengguna: selama perencanaan dan implementasi. Tahap ini tidak menghasilkan UI, sehingga pemeriksaan browser, kontras, keyboard dan responsivitas belum berlaku.

## Hasil

- Delapan tabel baru untuk register objek, detail komputer, versi template, item, pemeriksaan, hasil, tindak lanjut dan log.
- Sembilan enum, delapan model dan tiga policy. Cakupan data menggunakan department pengelola, permission existing, status employee aktif dan bypass Sys Administrator existing.
- Delapan permission baru, tanpa pemberian otomatis ke pengguna atau role non-system-admin. Permission masuk melalui AccessControlSeeder untuk instalasi baru dan migration permission untuk penerapan terkontrol di kemudian hari.
- Objek ruang menggunakan work_locations, tanpa nomor aset, serial, pemakai dan lifecycle aset. Objek ruang dikecualikan oleh scope assets.
- Status lifecycle enam pilihan dan lisensi empat pilihan, dengan data verifikasi wajib untuk keputusan lisensi selain Belum Diverifikasi.
- Snapshot identitas dan versi checklist, guard hasil final, validasi temuan/N/A, validasi finalisasi dan tindak lanjut.
- Relasi latestFinalizedInspection menggunakan checked_at lalu id, bukan waktu pembuatan/update. Definisi rekap distinct aset, transaksi pemeriksaan dan temuan belum selesai tercantum pada spesifikasi; dashboard belum dibuat.

| Template versi 1 | Jumlah item |
| --- | ---: |
| Server fisik | 38 |
| Ruang server | 18 |
| UPS | 12 |
| AC | 13 |
| Storage / NAS | 25 |
| Hypervisor | 22 |
| Virtual Machine | 27 |
| FortiGate Firewall | 21 |
| Access Point | 20 |
| Switch | 21 |
| Laptop | 26 |
| PC | 26 |
| Total | 269 |

Setiap item memiliki kode stabil, kelompok, petunjuk, urutan, jenis nilai dan satuan bila relevan. Tidak ada angka batas penilaian yang diasumsikan. Template yang sudah dipakai tidak ditimpa saat seeder diulang; perubahan memerlukan versi baru.

## File baru

34 file baru:

```text
app/Enums/ItAssetLifecycle.php
app/Enums/ItDeviceCategory.php
app/Enums/ItDeviceType.php
app/Enums/ItFollowUpStatus.php
app/Enums/ItInspectionCondition.php
app/Enums/ItInspectionStatus.php
app/Enums/ItLicenseStatus.php
app/Enums/ItObjectKind.php
app/Enums/ItPermission.php
app/Models/ItChecklistItem.php
app/Models/ItChecklistTemplate.php
app/Models/ItDevice.php
app/Models/ItDeviceComputerDetail.php
app/Models/ItInspection.php
app/Models/ItInspectionEvent.php
app/Models/ItInspectionFollowUp.php
app/Models/ItInspectionResult.php
app/Policies/ItDevicePolicy.php
app/Policies/ItInspectionFollowUpPolicy.php
app/Policies/ItInspectionPolicy.php
app/Services/ItInfrastructure/ItInfrastructureAccess.php
database/migrations/2026_10_16_000000_create_it_infrastructure_tables.php
database/migrations/2026_10_16_000100_add_it_infrastructure_permissions.php
database/seeders/ItInfrastructureChecklistSeeder.php
database/seeders/ItInfrastructurePermissionSeeder.php
database/seeders/data/it-infrastructure-checklists.php
docs/IT-INFRASTRUCTURE-SPEC.md
docs/IT-INFRASTRUCTURE-STAGE-2.md
phpunit.it-infrastructure.xml
tests/Feature/ItInfrastructure/ItDeviceTest.php
tests/Feature/ItInfrastructure/ItInfrastructureAccessTest.php
tests/Feature/ItInfrastructure/ItInfrastructureSchemaTest.php
tests/Feature/ItInfrastructure/ItInspectionTest.php
tests/Support/ItInfrastructureTestCase.php
```

## File existing yang diubah

| File | Perubahan |
| --- | --- |
| database/seeders/AccessControlSeeder.php | Menambahkan pemanggilan ItInfrastructurePermissionSeeder sebelum sinkronisasi role existing. Tidak mengubah definisi atau grant permission modul lain. |
| database/seeders/DatabaseSeeder.php | Menambahkan pemanggilan ItInfrastructureChecklistSeeder. |

Tidak ada perubahan file Ticket Master, LTRO, ATK, migration APCA, User, middleware, provider panel, route, CSS, view atau Resource existing. File untracked yang sudah ada sebelum pekerjaan ini dipertahankan.

## Pengujian

Perintah pengujian:

```text
php vendor/phpunit/phpunit/phpunit -c phpunit.it-infrastructure.xml --do-not-cache-result
```

Hasil terakhir: PASS, 43 tests, 690 assertions.

Konfigurasi khusus memaksa APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory: dan DB_URL kosong, serta menonaktifkan pemakaian cache konfigurasi aplikasi. TestCase memverifikasi konfigurasi dan koneksi sebelum membuat schema. Hanya sepuluh migration pada allowlist yang dimuat: delapan dependency dasar dan dua migration IT baru. Tidak ada artisan migrate, migrate:fresh, db:seed atau operasi pada database aplikasi. Kedua migration APCA tidak dimuat.

Cakupan pengujian:

- Relasi database, foreign key gabungan yang menolak hasil lintas template dan tindak lanjut lintas pemeriksaan, serta nomor aset unik dengan nilai kosong diperbolehkan.
- Semua jenis template, isi khusus firewall/AP/switch/server/laptop, pengulangan seeder dan tidak mengaktifkan kembali template yang dinonaktifkan.
- Permission idempotent, tidak ada grant otomatis ke role non-system-admin, serta grant ticket existing tetap sama setelah permission IT ditambahkan.
- Ruang tanpa serial/aset, kebutuhan lokasi aktual, lifecycle disposal, siklus relasi parent dan pembatasan detail inventaris komputer.
- Windows/Office default Belum Diverifikasi dan kebutuhan dasar verifikasi status lisensi.
- Temuan Warning/Critical, alasan N/A, penolakan finalisasi kosong/parsial/unchecked/tanpa tindak lanjut.
- Hasil final dan snapshot tetap sama setelah penyelesaian tindak lanjut, penolakan edit melalui instance draft yang sudah kedaluwarsa, penolakan reopen serta log append-only.
- Pemeriksaan berulang tidak menambah total objek; relasi kondisi terakhir mengabaikan draft dan pemeriksaan lama yang baru diinput.
- Akses petugas IT untuk laptop pemakai department lain, kebutuhan permission sekaligus cakupan department, mutasi perangkat tanpa perpindahan akses riwayat, draft sendiri, pemisahan laporan/ekspor, penolakan employee nonaktif dan bypass Sys Administrator.

Pint pada 33 file PHP baru/berubah: PASS. Pemeriksaan sintaks PHP: PASS. git diff --check: PASS.

Suite existing lengkap tidak dijalankan karena RefreshDatabase pada suite tersebut dapat memuat migration APCA yang dikecualikan. Pada delivery awal, PostgreSQL belum diuji. Pengujian PostgreSQL kemudian dilakukan atas permintaan pengguna; hasilnya ada di [IT-INFRASTRUCTURE-POSTGRES-TEST.md](IT-INFRASTRUCTURE-POSTGRES-TEST.md). Browser belum diuji karena tidak ada perubahan UI.

## Batas tahap berikutnya

Belum ada halaman Master Perangkat, form, menu baru, pemetaan pilihan hak akses pada UI Users, nomor otomatis, action transaksi, dashboard, Excel atau referensi ticket. Delapan permission sudah didefinisikan dan dapat diuji dengan mekanisme permission model existing; penyajian pilihan pada Users dilakukan bersama tahap tampilan.

Model guard tidak menggantikan transaksi dan otorisasi action. Tahap form wajib mengambil actor dari akun login, memeriksa target department/perangkat melalui policy, memakai transaksi dan lock induk untuk finalisasi/perubahan hasil, serta mencatat event. Jangan menggunakan bulk update/delete untuk melewati guard. Relasi model mentah, termasuk riwayat dan kondisi terakhir, harus disaring dengan scope/policy pemeriksaan saat disajikan; akses master setelah mutasi tidak otomatis memberi akses riwayat department lama.

Pekerjaan berhenti di fondasi untuk review sebelum tahap tampilan. Migration dan seeder belum diterapkan pada database aplikasi.
