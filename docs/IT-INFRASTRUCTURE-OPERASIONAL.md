# Infrastruktur IT operasional

Modul diaktifkan pada PostgreSQL aplikasi lokal pada 16 Oktober 2026 atas permintaan pengguna untuk mengganti simulasi menjadi register asli. Tampilan tabel pengecekan dan isian langsung dipertahankan. Master dan pemeriksaan sekarang menggunakan model IT serta transaksi database; penyimpanan contoh dalam sesi dihapus dari kode aplikasi.

## Menambah banyak perangkat

1. Buka Infrastruktur IT → Master Perangkat (`/panel/it-devices`).
2. Klik Tambah Perangkat. Pilih jenis Laptop, PC, Server Fisik, FortiGate, Switch, Access Point, atau objek lain yang sesuai.
3. Isi nama serta department pengelola. Kode register dibuat otomatis dan dapat diganti dengan kode internal yang unik. Nomor aset, serial, lokasi, spesifikasi, pemakai dan siklus hidup berada pada tab terkait. Ruang server wajib memakai Work Locations existing dan tidak memiliki serial/nomor aset/lifecycle perangkat.
4. Untuk Laptop/PC, isi OS, Windows, Office, RAM dan HDD/SSD pada tab OS & lisensi. Media penyimpanan dapat ditambahkan lebih dari satu. Status lisensi awal Belum Diverifikasi; keputusan lisensi lainnya memerlukan dasar verifikasi. Identitas petugas dan waktu verifikasi diambil dari akun login.
5. Klik Simpan & tambah lagi untuk memasukkan perangkat berikutnya, atau Simpan Perangkat untuk membuka data yang tersimpan. Banyak laptop dengan jenis yang sama tetap menjadi record terpisah. Jumlah perangkat tidak dibatasi pada objek awal simulasi.

Daftar mendukung pencarian nama/kode/aset/serial/pemakai, filter kategori, jenis, pemakaian dan aktif, serta paginasi. Data awal register kosong: tidak ada perangkat atau hasil pemeriksaan fiktif yang diimpor dari sesi review. Pengujian penyimpanan dengan perangkat uji dilakukan hanya pada database uji terisolasi.

## Pengecekan, riwayat dan tindak lanjut

- Setelah perangkat tersimpan, gunakan Periksa perangkat ini atau buka Pengecekan Infrastruktur. Daftar pilihan berasal dari perangkat aktif yang diizinkan bagi petugas.
- Template aktif dipilih sesuai jenis perangkat. Dua belas jenis checklist tetap terpisah menurut fungsi. Nilai, catatan, temuan, tindak lanjut, penanggung jawab serta alasan N/A diisi langsung pada tabel; tidak ada modal Detail atau keterangan jumlah pada nama kelompok. Checklist Server Fisik versi 2 memiliki 37 butir tanpa AC ruang; pemeriksaan lama mempertahankan versi sebelumnya.
- Semua kelompok tampil berurutan dalam satu lembar tabel seperti pola LTRO Daily Input. Lingkungan, Virtualisasi dan kelompok lain menjadi judul tebal di dalam tabel; tidak ada dropdown kelompok atau tombol Sebelumnya/Berikutnya. Satu baris judul kolom berlaku untuk seluruh checklist.
- Simpan Draft menyimpan seluruh kelompok dalam satu transaksi. Warning/Critical memerlukan temuan, rencana dan penanggung jawab aktif. N/A memerlukan alasan. Finalisasi mensyaratkan seluruh item diperiksa; catatan pemeriksaan dan catatan per butir opsional, termasuk saat finalisasi.
- Nomor pemeriksaan dan actor berasal dari server. Identitas perangkat, petugas, lokasi, versi template serta item disalin sebagai snapshot. Perubahan master berikutnya tidak mengubah riwayat. Draft lain tidak dapat ditimpa tanpa permission pengelolaan.
- Hasil final dikunci pada UI, policy, service dan model. Penyelesaian temuan melalui Rekap & Laporan menggunakan catatan penyelesaian, actor login dan waktu server. Penyelesaian hanya mengubah tindak lanjut, bukan hasil final. Event created/draft_saved/finalized/follow_up_started/follow_up_resolved dicatat terpisah.
- Riwayat pada master menampilkan 20 pemeriksaan terbaru yang diizinkan; tombol seluruh riwayat membuka laporan berpaginasi. Riwayat lama mempertahankan department pada saat pemeriksaan setelah perangkat berpindah pengelola.

### Pencarian perangkat pada pemeriksaan

Pilihan **Perangkat / ruang yang diperiksa** menggunakan dropdown pencarian Filament. Klik pilihan, lalu ketik nama, kode register, nomor aset atau serial number. Pencarian tidak membedakan huruf besar/kecil. Hasil menampilkan nama dan kode agar unit bernama sama dapat dibedakan. Pilih hasil dengan klik atau Arrow Down dan Enter; Escape menutup dropdown.

Server mengembalikan maksimal 50 hasil setiap pencarian. Perangkat di luar 50 pilihan awal tetap dapat ditemukan dengan kata kunci; daftar seluruh perangkat tidak dikirim ke browser. Hasil hanya memuat perangkat aktif, belum dihapuskan dan dalam cakupan department/permission petugas. Pilihan pada pemeriksaan tersimpan tetap memakai identitas historis, termasuk ketika master kemudian berganti nama atau nonaktif. Pembaca laporan tetap tidak dapat mengganti pilihan.

Perubahan hanya pada `app/Filament/Pages/ItInspectionPage.php`, view pemeriksaan dan test operasional; tidak ada perubahan tabel, migration atau workflow penyimpanan/finalisasi. Test tambahan memeriksa pencarian nama/kode/aset/serial, pencarian di luar 50 opsi awal, pengecualian department lain/nonaktif, pergantian jenis perangkat dan penyimpanan draft ke perangkat yang dipilih, serta label historis dan dropdown disabled bagi pembaca laporan.

Verifikasi pencarian: suite operasional SQLite 27 tests / 346 assertions PASS; suite IT PostgreSQL 18.4 lengkap 70 tests / 1176 assertions PASS. Database sementara dibersihkan; schema public, daftar migration dan jumlah record tabel bisnis utama tetap sama. Log: `output/it-infrastructure-review/device-search-tests.log` dan `device-search-postgres.log`. Sintaks, Pint dan whitespace PASS.

Delivery gate pencarian perangkat (anti-slop selama implementasi, arah Filament/ATK, ENERGY 1 / RHYTHM 1 / MOTION 1):

- **Hard Gate PASS**: pencarian nama dan kode diuji lewat browser pada perangkat nyata, hasil kosong menampilkan pesan, loading terlihat, pilihan Arrow Down/Enter dan buka/tutup Enter/Escape bekerja. Console tanpa error. Teks pencarian #111827 pada putih memiliki rasio 17.74:1. Tidak menambah menu, aset visual atau data contoh.
- **Purpose-Gate PASS**: dropdown bawaan Filament mengganti select biasa pada posisi yang sama; pencarian server dan batas 50 hasil menghindari daftar panjang. Nama bersama kode membedakan unit, tanpa baris form tambahan.
- **Liveliness PASS**: fokus tetap pada perangkat dan checklist, dengan tipografi, fokus biru dan navigasi panel existing. Tidak ada dekorasi atau animasi baru.
- **Craftsmanship PASS pada cakupan yang diperiksa**: browser memverifikasi pencarian Dell, kode Thinkpad, pergantian dari Server Fisik 38 item ke Laptop 26 item, pesan tidak ditemukan dan keyboard. Tidak menyimpan pemeriksaan uji ke database aplikasi. Lebar desktop 1521px tanpa overflow horizontal halaman; CSS responsive existing tidak diubah dan uji ponsel tidak diklaim ulang. Screenshot: `output/it-infrastructure-review/pencarian-perangkat-pemeriksaan.jpg`.

Rekap membedakan aset unik, ruang, jumlah pemeriksaan, draft, kondisi final terbaru dan temuan belum selesai. Filter periode/petugas menyaring pemeriksaan; filter kondisi menyaring kondisi terakhir dan ekspor. Ekspor Excel memuat hasil final beserta detail temuan/tindak lanjut dalam cakupan akses yang sama. Teks masukan disimpan sebagai teks di Excel agar tidak dieksekusi sebagai formula.

## Ekspor ringkasan perawatan

Pada Infrastruktur IT > Rekap & Laporan, gunakan **Ekspor Ringkasan Excel** untuk mengunduh workbook dengan dua lembar:

- **Ringkasan Perangkat**: satu baris per perangkat/ruang, identitas serta pemakaian dari master saat ini, jumlah pemeriksaan final, pemeriksaan terakhir dalam filter, petugas, kondisi terakhir, jumlah temuan terbuka/dalam proses/selesai dan total yang belum selesai. Perangkat tanpa hasil final tetap tercantum sebagai Belum Diperiksa dengan jumlah pemeriksaan nol. Angka jumlah disimpan sebagai angka Excel.
- **Temuan dan Perawatan**: satu baris per temuan, identitas/lokasi dari snapshot pemeriksaan, hasil saat diperiksa, rencana tindakan, penanggung jawab, batas waktu bila tersedia, status tindak lanjut saat ekspor, catatan penyelesaian, petugas penyelesai dan waktunya.

Pemeriksaan berulang tidak menggandakan perangkat. Hasil final Normal yang lebih baru tidak menutup temuan lama. Periode dan petugas membatasi pemeriksaan final yang diringkas; status penyelesaian mencerminkan keadaan saat file diunduh, bukan rekonstruksi status pada akhir periode. Draft tidak masuk workbook. Semua halaman data diekspor, bukan hanya 25 baris yang sedang ditampilkan.

Filter kategori/perangkat dan hak akses department tetap berlaku. Filter kondisi pada ringkasan memilih perangkat berdasarkan kondisi final terakhir dalam filter, lalu memasukkan seluruh temuan yang diizinkan untuk perangkat tersebut dalam filter yang sama. Tanpa filter kondisi, lembar temuan juga mempertahankan riwayat department yang diizinkan setelah aset berpindah pengelola. Identitas master saat ini dan identitas historis diberi judul kolom yang berbeda. Tombol **Ekspor Rincian Checklist** tetap tersedia untuk seluruh hasil checklist final; filter kondisinya berlaku pada hasil keseluruhan masing-masing pemeriksaan final.

Tambahan file: `app/Exports/ItMaintenanceSummaryExport.php`, `ItDeviceSummarySheet.php`, dan `ItMaintenanceFindingsSheet.php`. Page laporan, view laporan dan test operasional diperbarui. Tidak ada migration, perubahan tabel, pemberian akses atau perubahan workflow modul lain pada penambahan ekspor ini.

Verifikasi ekspor ringkasan: 68 tests / 1007 assertions SQLite dan 68 tests / 1143 assertions PostgreSQL 18.4 PASS. Dua test tambahan memeriksa workbook XLSX nyata dengan dua lembar, angka nol dan jumlah bertipe numerik, teks formula tetap teks, perangkat unik meskipun memiliki beberapa pemeriksaan/draft, temuan lama yang masih diproses, penyelesaian, cakupan department termasuk temuan department lain, filter kondisi/periode/petugas/perangkat, penolakan filter tanggal tidak sah, permission ekspor dan seluruh 26 perangkat melampaui batas halaman 25. Log: `output/it-infrastructure-review/maintenance-summary-suite.log` dan `maintenance-summary-postgres.log`. Pint serta diff whitespace PASS. Runner PostgreSQL memverifikasi schema, daftar migration dan jumlah record tabel bisnis utama tetap sama.

Delivery gate penambahan ekspor, mengikuti pilihan anti-slop selama implementasi:

- **Hard Gate PASS**: dua tombol memiliki aksi server yang diuji, permission ekspor wajib, workbook menggunakan data nyata yang diizinkan dan pemeriksaan final. State loading terlihat ketika tombol diklik dan kembali aktif tanpa error UI; tidak ada data contoh yang dimasukkan ke aplikasi. Fokus keyboard terlihat pada tombol rincian. Tampilan terang panel dan palet existing dipertahankan.
- **Purpose-Gate PASS**: label ringkasan dan rincian membedakan tujuan unduhan. Tidak ada tabel tambahan atau perubahan layout halaman; kedua tombol memakai komponen Filament dan flex-wrap existing agar tetap ringkas.
- **Liveliness PASS**: ENERGY 1 / RHYTHM 1 / MOTION 1, arah operasional Filament/ATK tetap berlaku. Tabel laporan tetap menjadi fokus, aksen biru dan pola navigasi Infrastruktur IT dipertahankan, tanpa dekorasi/aset baru.
- **Craftsmanship PASS pada cakupan yang diperiksa**: workbook nyata, angka/teks, filter dan otorisasi diuji pada SQLite/PostgreSQL. Browser memeriksa kedua tombol, loading selesai, fokus keyboard dan console tanpa error pada desktop 1521px. Override viewport ponsel pada sesi ini tidak mengubah ukuran browser, sehingga pengujian ponsel tidak diklaim ulang; tidak ada perubahan CSS responsive. Screenshot halaman nyata: `output/it-infrastructure-review/ringkasan-perawatan-excel.jpg`.

## Hak akses

User Management kini menampilkan pilihan Infrastruktur IT melalui `UserAdditionalAccessService` existing. Tidak ada pemberian permission atau perubahan role/cakupan department pengguna secara otomatis.

| Kemampuan | Permission |
| --- | --- |
| Lihat / kelola master | `it.devices.view` / `it.devices.manage` |
| Lihat / isi / kelola pengecekan | `it.inspections.view` / `it.inspections.create` / `it.inspections.manage` |
| Kelola tindak lanjut | `it.follow-ups.manage` |
| Lihat laporan / ekspor | `it.reports.view` / `it.reports.export` |

Semua akses tetap memeriksa pengguna aktif dan department pengelola melalui policy serta mekanisme department existing. Department pemakai laptop tidak menjadi dasar cakupan. Sys Administrator tetap memakai bypass existing. Pengguna laporan tanpa permission pengecekan hanya dapat membuka hasil final; akses laporan tidak memberikan hak edit atau ekspor secara otomatis.

## Aktivasi database

Hanya dua migration berikut diterapkan, dalam batch 73:

```text
2026_10_16_000000_create_it_infrastructure_tables
2026_10_16_000100_add_it_infrastructure_permissions
```

`ItInfrastructureChecklistSeeder` mengisi 12 template dan 269 item. Tidak menjalankan DatabaseSeeder, migrate:fresh, rollback, atau migration APCA. Kedua migration APCA tanggal 6 Oktober tetap Pending.

Aktivasi dilaksanakan melalui script lokal sekali jalan `tmp/activate-it-infrastructure-20261016.php`, yang memeriksa environment local, PostgreSQL, tidak adanya tabel IT sebelumnya, dan allowlist migration. Guard menghentikan eksekusi ulang setelah tabel IT tersedia. Sebelum/sesudah aktivasi dibandingkan metadata kolom schema existing, record migration existing, jumlah record sepuluh tabel bisnis utama, role, pivot permission/cakupan department dan permission non-IT. Fingerprint identik. Tidak ada perubahan Ticket Master, LTRO, ATK atau workflow ticketing. Bukti: `output/it-infrastructure-review/operations-activation.json`.

## File implementasi

Baru/pengganti preview:

```text
app/Services/ItInfrastructure/ItDeviceService.php
app/Services/ItInfrastructure/ItInspectionService.php
app/Filament/Support/ItInfrastructurePage.php
app/Filament/Resources/ItDevices/ItDeviceResource.php
app/Filament/Resources/ItDevices/Pages/ListItDevices.php
app/Filament/Resources/ItDevices/Pages/CreateItDevice.php
app/Filament/Resources/ItDevices/Pages/EditItDevice.php
app/Filament/Resources/ItDevices/Pages/ViewItDevice.php
app/Filament/Resources/ItDevices/Pages/InteractsWithItDeviceRecord.php
app/Filament/Pages/ItInspectionPage.php
app/Filament/Pages/ItInfrastructureReportPage.php
app/Exports/ItInspectionExport.php
resources/views/filament/pages/it-device-record.blade.php
tests/Feature/ItInfrastructure/ItInfrastructureOperationsTest.php
```

Disesuaikan: enum label status pemeriksaan, partial riwayat, view pengecekan/laporan dan komponen style/loading existing dari preview. File aplikasi existing yang diubah pada tahap ini hanya `app/Services/UserAdditionalAccessService.php`, untuk menampilkan pilihan permission IT tanpa mengubah pemetaan menu lain. Perubahan provider/seeder dari tahap fondasi tetap dipertahankan.

Dihapus/diganti: tiga Page Review, base Page Review, service sesi contoh, view master preview dan test preview sesi. Slug menu tetap sama tanpa route atau menu ganda. Model, policy serta schema tabel fondasi dipakai kembali.

## Verifikasi

Pengujian menggunakan konfigurasi khusus IT dengan allowlist migration; suite default yang dapat mengeksekusi APCA tidak digunakan. PostgreSQL diuji pada database sementara bernama acak, dengan pemeriksaan nama/OID/owner sebelum dibersihkan dan fingerprint database aplikasi sebelum/sesudah pengujian.

Cakupan tambahan: beberapa laptop dengan jenis sama, pencarian master, form create/edit/create-another, pendaftaran cepat tanpa disk kosong wajib, lisensi berdasar verifikasi, ruangan tanpa identitas aset, penolakan mutasi lintas cakupan, penyimpanan atomik, persistensi antarkelompok/komponen, finalisasi dan hasil final terkunci, penyelesaian tanpa mengubah hasil, kondisi terakhir versus temuan lama, filter periode/petugas/kategori, riwayat setelah mutasi department, workbook Excel nyata serta perlakuan teks formula, permission ekspor dan pemetaan akses IT tanpa kehilangan pilihan tiket existing.

Hasil: SQLite lengkap 66 tests / 957 assertions PASS; PostgreSQL 18.4 lengkap 66 tests / 1089 assertions PASS setelah penanganan filter ID tidak sah disesuaikan untuk PostgreSQL. Database sementara dibersihkan dan fingerprint database aplikasi tetap sama. Log: `operations-suite.log` dan `operations-postgres.log` pada folder output review. Pemeriksaan sintaks, Pint dan `git diff --check` PASS.

## Delivery gate anti-slop

Arah tetap formulir operasional Filament/Internal 9 dengan pola ATK yang diminta pengguna. ENERGY 1 / RHYTHM 1 / MOTION 1. Tidak ada redesign, font, warna tema atau aset ilustrasi baru. Screenshot merupakan bukti aplikasi, bukan data ilustrasi.

- Hard Gate PASS untuk modul yang diserahkan: label simulasi dihapus bersama service sesinya; tidak ada angka atau perangkat rekaan pada register aplikasi. Tiga menu menuju halaman nyata. Semua aksi tulis memanggil service transaksi dan otorisasi, bukan handler contoh. Empty state, loading dan error tetap tersedia. Mode terang panel existing dipertahankan.
- Purpose-Gate PASS: daftar berpaginasi dan pencarian digunakan untuk banyak perangkat; tab memisahkan identitas/inventaris/pemakaian/riwayat; Simpan & tambah lagi mempercepat pencatatan banyak unit; rincian checklist tetap langsung di tabel. Ikon serta aksen biru mempertahankan identitas navigasi panel dan tindakan utama.
- Liveliness PASS sesuai arah pengguna: formulir operasional dan tabel menjadi fokus layar. Struktur, tipografi dan spasi mengikuti Filament/ATK. Tidak menambahkan animasi atau dekorasi yang tidak melayani tugas.
- Craftsmanship PASS dengan batas bukti yang jelas: uji Livewire dan PostgreSQL terisolasi mencakup create, create-another, edit, cek/simpan/finalisasi, riwayat, filter, penyelesaian serta ekspor. Browser aplikasi asli memeriksa menu Master, tombol Tambah, form/tab, penambahan/penghapusan HDD pada form belum disimpan, validasi form kosong dan perpindahan jenis Ruang Server. Pengujian browser tidak mengisi register dengan perangkat uji.
- Responsive PASS pada ukuran yang diperiksa: form 390px memiliki lebar dokumen 375px tanpa overflow horizontal halaman. Tab/form memakai pola responsive native; kontrol media mengikuti repeater Filament. Viewport dikembalikan ke ukuran desktop setelah pengujian.
- Kontras/fokus memakai pasangan warna dan aturan scoped yang telah diperiksa pada dokumen UI review; tidak ada perubahan palet pada tahap ini. Tab aktif tetap teks putih/biru dengan rasio 5.27:1. Validasi browser form kosong memfokuskan Nama device / objek yang wajib diisi.

Bukti tampilan: `output/it-infrastructure-review/master-perangkat-operasional.jpg`. Pemeriksaan ini berlaku pada modul IT; bukan audit visual atau aksesibilitas seluruh aplikasi existing.

## Revisi 18 Oktober 2026: ringkasan pemeriksaan dan catatan opsional

Nomor pemeriksaan pada tab Kondisi terakhir, Riwayat, dan Temuan di Rekap & Laporan sekarang membuka `/panel/it-inspection-summary?inspection=ID`. Halaman ringkasan menampilkan identitas historis, waktu, petugas, lokasi, kondisi keseluruhan, jumlah setiap hasil dan seluruh kelompok checklist sebagai teks. Nilai nol, satuan, rincian multi-baris, alasan N/A, temuan serta status tindak lanjut tetap terbaca. Teks masukan di-escape. Pembaca laporan hanya dapat membuka hasil final dalam department yang diizinkan. Petugas berhak edit dapat memilih Lanjutkan draft; hasil final tetap terkunci.

Catatan pemeriksaan dan catatan setiap butir opsional saat menyimpan draft maupun finalisasi. Validasi service dan model telah disesuaikan. Penilaian setiap butir, alasan N/A, temuan Warning/Critical serta ketentuan tindak lanjut tetap berlaku.

Template Server Fisik versi 2 menghapus butir AC ruang; Suhu ruang, Suplai UPS dan Suplai listrik tetap ada. Template Storage, AC, dan Ruang Server tidak berubah. Seeder menerbitkan versi baru dan menonaktifkan versi server sebelumnya tanpa mengubah butir atau hasil historis. Pemeriksaan lama tetap menampilkan AC ruang sesuai data saat diperiksa. Script `tmp/revise-it-server-checklist-20261018.php` telah menerapkan revisi pada PostgreSQL lokal; fingerprint seluruh data perangkat, pemeriksaan, hasil, tindak lanjut dan event identik sebelum/sesudahnya.

Verifikasi SQLite: 73 tests / 1105 assertions PASS. Test mencakup finalisasi dengan catatan kosong/null/tanpa key, tautan rekap ke ringkasan, identitas historis setelah master berubah/nonaktif, nilai nol dan multi-baris, escaping HTML, penolakan akses draft bagi pembaca laporan, department lain, ID tidak sah dan pengguna tanpa permission, serta penerbitan template baru secara idempoten dengan hasil final lama tetap utuh. Pint, sintaks PHP dan `git diff --check` PASS.

Delivery gate revisi (anti-slop selama implementasi, arah Filament/ATK, ENERGY 1 / RHYTHM 1 / MOTION 1):

- **Hard Gate PASS pada perubahan ini**: ringkasan tidak memiliki input checklist; browser mengonfirmasi nol input/select/textarea pada konten ringkasan. Tautan Kondisi terakhir dan Riwayat membuka halaman ringkasan; tombol kembali membuka rekap. Konten berasal dari snapshot nyata. Loading/error memakai komponen existing, dan hasil kosong memiliki pesan. Warna teks slate pada latar netral 9.45:1, Normal 6.81:1 dan fokus biru 5.98:1 diperiksa dengan checker lokal. Browser tidak mencatat error konsol pada pemeriksaan akhir.
- **Purpose-Gate PASS**: metadata menjelaskan pemeriksaan yang sedang dibaca; hitungan kondisi merangkum hasil; tabel per kelompok menempatkan nilai dan temuan berdampingan. Tombol Lanjutkan draft hanya muncul sesuai policy agar petugas dapat kembali mengisi draft. Tidak menambahkan menu sidebar, ikon, font, warna atau aset ilustrasi.
- **Liveliness PASS**: pola tabel, aksen biru, tipografi dan spasi Filament/Internal 9 tetap menjadi identitas formulir operasional. Judul ringkasan dan identitas pemeriksaan memimpin hierarki, lalu jumlah hasil dan kelompok checklist. Tidak ada dekorasi atau animasi baru.
- **Craftsmanship PASS pada alur yang diperiksa**: browser memverifikasi pembukaan ringkasan dengan Enter, tombol kembali, tautan Riwayat, checklist server baru 37 butir tanpa AC ruang, serta placeholder catatan opsional. Fokus Tab antarregion tabel berupa outline solid `#005fcc`. Lebar dokumen 375px pada viewport 390px, 753px pada viewport 768px, dan 1521px pada viewport 1536px; tabel tetap bergulir dalam region lokal. Viewport dikembalikan ke ukuran semula. Pengujian finalisasi dilakukan di database uji, tanpa menambah pemeriksaan percobaan ke register aplikasi. Pemeriksaan ini bukan audit aksesibilitas seluruh aplikasi atau pengujian ponsel fisik.

Verifikasi PostgreSQL 18.4: 73 tests / 1251 assertions PASS. Database uji sementara dibersihkan; fingerprint schema public, daftar migration dan jumlah record tabel bisnis utama tetap sama.

Bukti browser: `output/it-infrastructure-review/ringkasan-pemeriksaan-20261018.png` dan `output/it-infrastructure-review/checklist-server-20261018.png`.

### Penyelarasan kolom ringkasan

Semua tabel kelompok ringkasan memakai layout tabel tetap dengan proporsi Pemeriksaan/Hasil/Nilai/Catatan sebesar 30/18/22/30 persen. Lebar kolom tidak lagi mengikuti panjang isi masing-masing kelompok. Perubahan hanya pada class tabel ringkasan dan CSS yang dibatasi untuk class tersebut.

- Hard Gate PASS pada perubahan ini: browser merender sembilan tabel dengan posisi dan lebar kolom identik. Lebar halaman 1521px pada viewport 1536px, 753px pada 768px, dan 375px pada 390px; tabel ponsel selebar 544px berada di area scroll lokal 343px. Warna, fokus, kontrol dan state existing tidak berubah.
- Purpose-Gate PASS: proporsi yang sama menjaga nilai, kondisi dan catatan tetap sejajar untuk perbandingan antarkelompok. Tidak menambah dekorasi atau kontrol.
- Liveliness PASS: arah Filament/ATK dan ENERGY 1 / RHYTHM 1 / MOTION 1 dipertahankan; tabel tetap menjadi fokus.
- Craftsmanship PASS pada cakupan ini: pengukuran DOM memastikan lebar kolom sama pada sembilan kelompok di desktop, tablet dan ponsel; screenshot browser diperiksa, viewport dikembalikan ke ukuran awal. Perubahan presentasi ini tidak menyentuh data, validasi atau hak akses. Bukti: `output/it-infrastructure-review/kolom-ringkasan-sejajar-20261018.png`.

### Form pengecekan satu lembar mengikuti LTRO Daily Input

Acuan pengguna `/ltro/daily-input` diperiksa melalui source dan browser. Form pengecekan kini memakai satu tabel bergaris dengan judul kelompok tebal dan input langsung dalam sel. Kolom Hasil, Nilai/rincian dan Catatan memiliki posisi yang sama untuk seluruh kelompok. Petunjuk tiap butir tetap terlihat; catatan opsional. Pemilih kelompok, tombol pergantian kelompok dan state navigasinya dihapus. Penanganan error tidak lagi memilih kelompok karena seluruh butir sudah tampil. Simpan draft, finalisasi, temuan, alasan N/A, hasil final terkunci dan pemeriksaan hak akses memakai alur yang sama.

Verifikasi SQLite: 74 tests / 1168 assertions PASS. Test yang sebelumnya berpindah kelompok diperbarui untuk mengisi beberapa kelompok langsung pada satu form. Tambahan test server memastikan seluruh 37 input, kelompok awal/akhir, nilai suhu dan rincian VM multi-baris tampil serta tetap tersimpan saat draft dibuka kembali. Test validasi memastikan error muncul pada baris terkait. Pint, sintaks PHP dan `git diff --check` PASS.

Delivery gate (anti-slop selama implementasi; lembar operasional IT dengan pola LTRO, ENERGY 1 / RHYTHM 1 / MOTION 1):

- **Hard Gate PASS pada cakupan ini**: browser memverifikasi satu form, satu header tabel, sembilan judul kelompok dan 37 pilihan hasil server tanpa pemilih kelompok. Tidak ada data atau aset baru. Input tetap berlabel, loading/error/empty state existing dipertahankan, dan final tetap terkunci dalam pengujian. Kontras teks slate/putih 10.35:1, batas sel slate/putih 4.76:1, fokus biru memakai pasangan 5.98:1 yang sudah diperiksa. Konsol browser tidak mencatat error pada pemeriksaan akhir.
- **Purpose-Gate PASS**: garis sel memisahkan field seperti lembar isian LTRO; judul tebal menandai kelompok tanpa kontrol tambahan. Layout kolom tetap memungkinkan pembacaan lintas kelompok. Bentuk form mengikuti acuan pengguna dengan ukuran input yang tetap dapat dioperasikan; tidak menyalin teks, logo atau data proses LTRO.
- **Liveliness PASS**: tabel isian menjadi fokus, judul kelompok memberi hierarki, dan aksen serta tipografi panel existing tetap berlaku. Tidak menambah dekorasi, animasi atau menu.
- **Craftsmanship PASS pada alur yang diperiksa**: browser menguji pilihan Warning memunculkan Temuan/Tindak lanjut/Penanggung jawab dan mengembalikannya ke Belum Diperiksa. Tab dari hasil suhu menuju nilai suhu memberi outline solid di dalam sel. Pada viewport 390px, halaman 375px dan tabel 960px berada dalam region scroll 343px; fokus input menggulir tabel secara horizontal. Pada viewport 768px halaman 753px, dan pada desktop 1536px halaman 1521px. Sembilan kelompok tetap ada pada setiap ukuran. Viewport dikembalikan ke ukuran awal. Pengujian simpan/finalisasi dilakukan pada database terisolasi; pemeriksaan browser tidak menyimpan data percobaan ke register aplikasi.

Verifikasi PostgreSQL 18.4: 74 tests / 1316 assertions PASS. Database uji sementara dibersihkan; fingerprint schema public, daftar migration dan jumlah record tabel bisnis utama tetap sama. Browser juga membuka hasil final lama: seluruh 114 field checklist pada sembilan kelompok berstatus disabled. Setelah pemeriksaan, tab dikembalikan ke form baru tanpa menyimpan data percobaan.

Bukti: `output/it-infrastructure-review/form-checklist-satu-lembar-20261018.png`.

### Penyamaan tabel master dan rekap dengan pengecekan

Pada 19 Oktober 2026, tabel Master Perangkat, Riwayat perangkat, serta tab Kondisi terakhir, Temuan dan Riwayat di Rekap & Laporan mengikuti tabel Pengecekan Infrastruktur: garis sel `#64748b`, header `#f8fafc`, isi putih, teks 14px dan padding `.4rem .5rem`. Header master memakai huruf biasa. Class `it-infrastructure-table` membatasi CSS pada tabel yang diminta; pencarian, pengurutan, filter, pagination, aksi, query dan permission tetap memakai implementasi existing. Tidak ada migration atau perubahan data.

Delivery gate, anti-slop selama implementasi dengan arah Filament/Internal 9, ENERGY 1 / RHYTHM 1 / MOTION 1:

- **Hard Gate PASS pada perubahan ini**: tidak menambah aset, menu, kontrol, data atau klaim. Kontras teks/header 9.90:1, teks/putih 10.35:1, batas sel/putih 4.76:1 dan fokus biru/putih 5.98:1 dihitung dengan checker project. Browser memeriksa pencarian Thinkpad, pencarian tanpa hasil, pengurutan nama, filter Laptop, reset filter, tab laporan dan filter Critical tanpa hasil. State loading existing tetap muncul selama perubahan filter.
- **Purpose-Gate PASS**: garis setiap sel dan padding yang sama mengikuti acuan pengecekan agar kolom mudah dibandingkan. Tidak menambahkan efek, ikon, animasi atau warna baru. Hover master tidak lagi menambahkan bayangan tepi di dalam tabel bergaris.
- **Liveliness PASS**: tabel tetap menjadi fokus layar, dengan tipografi panel, header netral, aksen biru untuk tindakan/fokus dan hierarki existing. Arah serta dials tidak berubah.
- **Craftsmanship PASS pada cakupan ini**: 30 test operasional / 465 assertions lulus, mencakup form, pemeriksaan, laporan, ekspor dan akses. Pint, sintaks PHP dan whitespace lulus. Browser membuka riwayat perangkat nyata tanpa menyimpan perubahan; padding sel terukur 6.4px/8px dan garis `#64748b`. Fokus Tab pada pengurutan master serta tab rekap terlihat. Lebar dokumen 375px pada viewport 390px dan 753px pada 768px, pada master maupun rekap; tabel lebar bergulir dalam region lokal. Viewport dipulihkan dan console tidak mencatat error pada pemeriksaan. Ini pemeriksaan perubahan tabel, bukan audit aksesibilitas seluruh aplikasi atau uji ponsel fisik.

Bukti: `output/it-infrastructure-review/tabel-master-selaras-20261019.png` dan `tabel-rekap-selaras-20261019.png`.

### Penyamaan tabel detail Ringkasan Pemeriksaan

Halaman `/panel/it-inspection-summary` juga memakai class tabel bersama pada seluruh kelompok hasil. Penyesuaian 19 Oktober 2026 ini melengkapi perubahan sebelumnya yang baru mencakup daftar master dan tab rekap. Proporsi kolom 30/18/22/30, snapshot historis, angka kondisi dan akses baca tetap sama. Header diberi `scope="col"`; tidak ada perubahan query, database atau alur pemeriksaan.

- **Hard Gate PASS**: tabel memakai garis, latar, teks dan kontras yang sudah diverifikasi pada perubahan sebelumnya. Tidak ada aset, kontrol, data atau klaim baru. Semua hasil final tetap berupa teks. Console browser tanpa error pada pemeriksaan.
- **Purpose-Gate PASS**: class bersama membuat garis, header, latar sel dan padding detail mengikuti acuan Pengecekan Infrastruktur serta tabel rekap. Tidak menambah dekorasi.
- **Liveliness PASS**: arah Filament/Internal 9, ENERGY 1 / RHYTHM 1 / MOTION 1 dipertahankan; fokus halaman tetap identitas pemeriksaan dan hasil setiap kelompok.
- **Craftsmanship PASS pada perubahan ini**: dua test ringkasan / 42 assertions lulus, termasuk nilai historis, escaping HTML, hasil final dan akses department. Browser memverifikasi sembilan tabel dengan lebar kolom identik, isi putih, garis `#64748b` dan padding 6.4px/8px. Fokus Tab antarregion terlihat. Halaman selebar 375px pada viewport 390px dan 753px pada 768px; tabel ponsel selebar 544px bergulir dalam region 343px. Viewport dipulihkan. Whitespace lulus; tidak ada data percobaan disimpan.

Bukti: `output/it-infrastructure-review/tabel-ringkasan-selaras-20261019.png`.

### Penyederhanaan Laptop/PC dan data pemakaian

Revisi 19 Oktober 2026 mengikuti permintaan pengguna:

- Pilihan jenis perangkat menggunakan nama langsung, termasuk Laptop dan PC, tanpa awalan kategori.
- Baris awal tab OS & lisensi menjadi OS, Versi, Lisensi. OS menerima Windows, Ubuntu, macOS atau isian lain melalui input dengan saran. Kolom penyimpanan existing tetap dipakai agar data OS/versi/lisensi lama tidak hilang. Isian Microsoft Office tetap terpisah; dasar verifikasi status lisensi pada master tetap berlaku.
- Tab Pemakaian menambahkan User terdaftar, Jabatan dan Divisi. Pilihan user berasal dari karyawan aktif yang terhubung ke akun user; user existing yang sudah nonaktif tetap dapat ditampilkan pada perangkat yang sebelumnya ditugaskan kepadanya. Nama pemakai diambil dari akun pada server. Jabatan berasal dari `employees.position`, Divisi dari department karyawan, dan keduanya mengikuti data karyawan saat master dibuka. Nama User manual tetap tersedia saat tidak memilih user terdaftar, sehingga nama lama tetap dapat digunakan. Ruang Server tidak memiliki pemakai.
- Pemeriksaan baru Laptop dan PC memakai checklist versi 2 dengan masing-masing 18 butir. Delapan butir dihapus: Boot dan shutdown, Performa penggunaan, Penggunaan RAM, Disk health HDD/SSD, Akses jaringan kerja, Keamanan akses, Verifikasi lisensi Windows, serta Verifikasi lisensi Microsoft Office. Kelompok menjadi Keamanan karena verifikasi lisensi tidak lagi dilakukan dalam checklist komputer. Hardware PC hanya ada pada PC; Laptop memiliki Baterai laptop. Checklist server dan jaringan tidak berubah.
- Filter Perangkat / ruang di area filter Rekap & Laporan dihapus. Tautan riwayat perangkat tetap dapat membuka laporan khusus perangkat melalui parameter existing; query dan izin ekspor tetap berlaku.

Seeder menerbitkan versi baru dan menonaktifkan versi Laptop/PC sebelumnya. Draft dan hasil final lama tetap merujuk template serta snapshot semula; delapan butir historis tidak dihapus dari pemeriksaan lama. Snapshot pemeriksaan baru juga mencatat jabatan dan divisi pemakai. Tidak ada migration, perubahan role, atau penambahan data perangkat contoh. Aktivasi PostgreSQL lokal memverifikasi fingerprint register, detail komputer, draft/final/hasil/tindak lanjut/event, user, karyawan, department, role, permission dan migration identik sebelum/sesudah. Menjalankan seeder kedua kali tidak mengubah katalog.

Verifikasi: 77 tests / 1224 assertions SQLite dan 77 tests / 1378 assertions PostgreSQL 18.4 lulus. Database PostgreSQL sementara dibersihkan; schema public, migration dan jumlah record tabel bisnis utama tetap sama. Test tambahan mencakup versi checklist dengan draft/final lama tetap utuh, perpindahan PC ke Laptop tanpa Hardware PC, simpan/buka ulang 18 hasil, pengisian user/jabatan/divisi, penolakan user nonaktif untuk penugasan baru, edit penugasan existing, serta snapshot jabatan setelah data karyawan berubah. Fixture kode pada test pencarian dibuat tetap agar kode acak tidak kebetulan cocok dengan kata pencarian. Pint, sintaks dan whitespace lulus. Log PostgreSQL: `output/it-infrastructure-review/computer-simplification-postgres-20261019.log`.

Delivery gate, anti-slop selama implementasi; arah formulir operasional Filament/Internal 9, ENERGY 1 / RHYTHM 1 / MOTION 1:

- **Hard Gate PASS pada perubahan ini**: tidak ada aset, menu, klaim atau data operasional buatan. Browser menampilkan Laptop dan PC tanpa kategori, input Ubuntu/24.04, perubahan Lisensi ke Tidak Berlaku menampilkan dasar verifikasi, dan pilihan user Khafafi menampilkan jabatan/divisi dari data existing. Loading terlihat selama pemilihan. Simpan dan validasi diuji pada database terisolasi. Warna, kontras, fokus serta mekanisme error/empty existing dipertahankan.
- **Purpose-Gate PASS**: label singkat mengikuti permintaan pengguna; tiga kolom pemakaian menempatkan user, jabatan dan divisi berdampingan. Data identitas bersumber dari user existing agar tidak diketik ulang. Tidak ada perubahan dekorasi, animasi atau tema.
- **Liveliness PASS**: tabel isian, tab, tipografi dan aksen panel existing tetap menjadi identitas layar. Header dan tabel mempertahankan arah serta dials sebelumnya.
- **Craftsmanship PASS pada cakupan yang diperiksa**: browser memverifikasi pergantian jenis master, tab OS/Pemakaian, pemilihan user dan fokus Tab ke Clear selection. Pemilihan Thinkpad pada pengecekan menghasilkan 18 pilihan hasil pada enam kelompok, tanpa delapan butir yang dihapus atau Hardware PC. Rekap hanya memiliki lima filter: Kategori, Kondisi terakhir, Dari tanggal, Sampai tanggal dan Petugas. Halaman ponsel selebar 375px pada viewport 390px, tabel checklist 960px berada dalam region 343px; pada tablet halaman 753px dalam viewport 768px. Viewport dipulihkan; tidak ada error console pada pemeriksaan akhir. Form browser tidak disimpan menjadi perangkat atau pemeriksaan percobaan.

Bukti: `output/it-infrastructure-review/pemakaian-user-jabatan-divisi-20261019.png`, `os-versi-lisensi-20261019.png` dan `checklist-laptop-18-mobile-20261019.png`.

### Koreksi jenis PC dan Laptop pada master existing

Pada 19 Oktober 2026, aturan yang mengunci jenis setelah detail komputer dibuat diperbaiki agar salah klasifikasi PC/Laptop dapat dikoreksi melalui Edit. Perangkat komputer dengan detail atau pemeriksaan existing hanya menawarkan Laptop dan PC. Perangkat terpakai tidak dapat dipindahkan ke kategori server, jaringan atau ruangan. Aturan pilihan dan validasi model memakai `editableDeviceTypes()` yang sama; policy edit dan batas department tetap berlaku.

Koreksi mempertahankan detail OS/lisensi, RAM, HDD/SSD, pemakai dan status pemakaian. Perubahan jenis tidak lagi otomatis mereset status menjadi Tidak Digunakan. Draft dan hasil final existing tetap menggunakan template serta snapshot jenis semula; pemeriksaan baru memakai jenis master yang sudah dikoreksi. Tidak ada migration atau konversi otomatis atas record perangkat.

Lokasi dijelaskan sebagai kantor/cabang tempat perangkat berada, dengan placeholder Pilih lokasi perangkat. Pilihannya berasal dari Work Locations. Detail ruang/rack menyimpan posisi lebih spesifik. Lokasi bersifat opsional untuk Laptop/PC dan tetap wajib untuk Ruang Server.

Validasi: 79 tests / 1258 assertions SQLite dan 79 tests / 1416 assertions PostgreSQL 18.4 lulus. Dua test baru menyimpan koreksi PC ke Laptop lalu kembali ke PC, memeriksa inventaris dan status tetap sama, draft/final lama tidak berubah, serta checklist baru sesuai jenis. Perubahan lintas kategori melalui service ditolak dan transaksi dibatalkan. Database uji PostgreSQL dibersihkan; fingerprint schema, migration dan jumlah record tabel bisnis utama tetap sama. Pint, sintaks dan whitespace lulus. Log: `output/it-infrastructure-review/computer-type-correction-postgres-20261019.log`.

Delivery gate, anti-slop selama implementasi; Filament/Internal 9, ENERGY 1 / RHYTHM 1 / MOTION 1:

- **Hard Gate PASS pada perubahan ini**: pilihan jenis pada komputer existing aktif dan hanya memuat Laptop/PC. Petunjuk lokasi dan koreksi berasal dari aturan aplikasi yang diuji; tidak menambah aset, menu atau klaim buatan. Warna, kontras, error/loading dan empty state existing dipertahankan.
- **Purpose-Gate PASS**: membuka pilihan yang dapat dikoreksi tanpa mengubah struktur inventaris. Petunjuk di bawah field menjelaskan arti lokasi serta akibat koreksi terhadap riwayat. Tidak ada dekorasi atau kontrol baru.
- **Liveliness PASS**: form bertab, tipografi, aksen dan hierarki panel dipertahankan dengan arah dan dials yang sama.
- **Craftsmanship PASS pada cakupan ini**: browser memilih PC lalu Laptop pada master existing tanpa menyimpan perubahan percobaan; status tetap Digunakan. Simpan dua arah dan penolakan lintas kategori diuji di database terisolasi. Tab menuju Department pengelola menunjukkan outline biru. Halaman selebar 375px pada viewport 390px dan 753px pada 768px; petunjuk membungkus tanpa keluar form. Viewport dipulihkan dan console tidak mencatat error.

### Label ringkas dan LT untuk Switch

Revisi 19 Oktober 2026 menyamakan nama field dan kolom menjadi Nama Perangkat serta Status Pemakaian. Tambahan `/ ruang` di label pemilih pemeriksaan, ringkasan dan judul ekspor dihapus. Ruang Server tetap didukung sebagai jenis perangkat; aturan validasi dan akses tidak berubah.

Master Switch memiliki pilihan opsional LT (Lantai): B2, Lobi, LT 2, LT 3, LT 5, LT 6, LT 7, LT 8, LT 9 dan LT 10. Jenis lain tidak menampilkan LT; mengganti jenis Switch yang belum digunakan ke jenis lain membersihkan nilainya. Model menolak lantai pada jenis selain Switch dan service membatasi nilainya ke daftar tersebut. Kolom LT tampil pada daftar master.

Migration `2026_10_19_000000_add_floor_to_it_devices` menambahkan kolom `floor` nullable tanpa mengisi ulang data existing. Snapshot pemeriksaan baru mencatat lantai master saat dibuat. Pengecekan dan Ringkasan Pemeriksaan menampilkan lantai dari snapshot, sehingga perubahan lantai pada master tidak mengubah hasil historis. Ekspor ringkasan menambahkan LT ke lokasi master saat ini; ekspor temuan dan rincian menggunakan LT historis pada lokasi pemeriksaan. Jumlah serta urutan kolom workbook tetap sama.

Verifikasi: 82 tests / 1367 assertions SQLite dan 82 tests / 1531 assertions PostgreSQL 18.4 lulus. Test tambahan memeriksa migration pada perangkat/hasil existing, seluruh pilihan lantai, penolakan LT 4/B1/11, simpan/buka/edit Switch, pembersihan LT ketika jenis berubah, riwayat LT 2 setelah master pindah LT 3, serta lokasi dalam ketiga ekspor. Database PostgreSQL sementara dibersihkan dan fingerprint database aplikasi tidak berubah selama suite. Aktivasi migration lokal juga membandingkan seluruh field lama perangkat, inventaris, checklist, pemeriksaan/hasil/tindak lanjut/event, user dan akses: identik sebelum/sesudah, selain kolom nullable serta catatan migration baru. Pint, sintaks dan whitespace lulus. Log: `output/it-infrastructure-review/switch-floor-postgres-20261019.log`.

Delivery gate, anti-slop selama implementasi; arah Filament/Internal 9 dengan ENERGY 1 / RHYTHM 1 / MOTION 1:

- **Hard Gate PASS pada cakupan ini**: LT merupakan select nyata dengan sepuluh pilihan yang diminta; browser memilih LT 2 dan Lobi, kemudian berpindah ke Laptop dan LT tersembunyi. Loading tampak selama pergantian jenis. Simpan, validasi pilihan tidak sah, snapshot dan ekspor diuji di database terisolasi. Tidak ada aset, menu atau data perangkat contoh baru. Warna dan kontras mengikuti kontrol serta teks existing.
- **Purpose-Gate PASS**: label ringkas mengikuti permintaan pengguna; LT berada di samping Lokasi agar kantor dan lantai dibaca bersama. LT di daftar serta lokasi ekspor membantu membedakan posisi Switch tanpa mengubah struktur tabel lain.
- **Liveliness PASS**: tipografi, aksen, tab, hierarki form dan tabel bergaris existing dipertahankan, dengan arah dan dials yang sama. Tidak ada dekorasi atau animasi tambahan.
- **Craftsmanship PASS pada cakupan ini**: browser mengonfirmasi label Nama Perangkat dan Status Pemakaian, seluruh opsi LT, pemilihan Lobi dan fokus Tab menuju Detail ruang/rack. Halaman ponsel selebar 375px pada viewport 390px dan tablet 753px pada 768px; form Switch menumpuk satu kolom pada ponsel. Viewport dipulihkan, console tanpa error, dan form browser tidak disimpan sebagai data percobaan.

Bukti: `output/it-infrastructure-review/switch-lantai-20261019.png`.
