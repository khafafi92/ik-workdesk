# Monitoring Harian IT

Menu: **Infrastruktur IT > Monitoring Harian IT**, URL `/panel/it-daily-monitoring`.

Modul mencatat hasil pemeriksaan manual petugas. Format awal memiliki tiga jenis pencatatan dengan struktur baris berbeda. Modul ini tidak menjalankan backup, menarik telemetri, atau mengubah konfigurasi firewall, Synology, maupun vSphere.

Mulai perubahan 21 Oktober 2026, form baru menyediakan lima pemeriksaan: internet, firewall, ruang server, File Station backup, dan patch/update VM. Lihat [format terbaru dan hasil pengujian](MONITORING-HARIAN-IT-2026-10-21.md). Bagian berikut mendokumentasikan format sebelumnya dan riwayat pengembangannya; data lama tetap didukung.

## Pengisian

1. Klik **Cek internet**, **Cek backup**, atau **Cek VM** pada daftar. Jenis pemeriksaan langsung dipilih. Untuk pemeriksaan berikutnya pada perangkat yang sama, klik **Periksa lagi** dari daftar atau halaman rincian. Department pengelola ditetapkan **IT** oleh server dan tidak menjadi pilihan pada form. Akun tetap harus memiliki izin pencatatan dan cakupan akses IT.
2. Isi nama sistem/perangkat, lokasi bila diperlukan, dan waktu pengecekan aktual. Tanggal/waktu mendatang ditolak.
3. Isi setiap baris pengecekan. Tambah baris sesuai jumlah link, job backup, atau VM.
4. Klik **Simpan Draft** selama masih ada baris **Belum diperiksa**. Klik **Finalisasi** setelah seluruh baris sudah diperiksa. Catatan final dikunci; hasil bermasalah boleh difinalisasi dengan penjelasan masalah/tindakan.

Gaya lembar pemeriksaan tetap mengikuti Pengecekan Infrastruktur: heading, garis tegas, label isian, dan tombol Draft/Finalisasi. Form pengisian kini memakai blok per link, job backup, atau VM agar isian tidak terjepit dalam sel tabel. Blok dapat ditutup dengan nama dan hasil tetap terbaca. Pada desktop, isian utama memakai tiga kolom, tablet dua kolom, dan HP satu kolom. Angka pengukuran opsional serta lokasi/catatan umum dapat dibuka sesuai kebutuhan; nilai tersimpan membuat bagian tersebut terbuka. Halaman rincian tetap memakai tabel di desktop dan susunan vertikal berlabel di layar sempit.

**Periksa lagi** hanya menyalin nama sistem/perangkat, lokasi, dan nama pemeriksaan. Target uji koneksi serta rencana daya VM dipertahankan sebagai konfigurasi. Status aktual kembali **Belum diperiksa**; hasil verifikasi data, waktu backup, folder yang diperiksa, catatan hasil dan angka pengukuran dikosongkan. Waktu pengecekan memakai waktu baru dan tanggal backup memakai hari ini. Membuka form tidak menyimpan record. Record sumber tetap utuh, dan izin membuat/membaca tetap diperiksa pada server.

| Jenis | Isi setiap baris |
| --- | --- |
| Firewall & koneksi internet | Nama ISP/link, kondisi terhubung/putus nyambung/terputus, target uji, latency ms dan packet loss persen bila diukur, serta masalah/tindak lanjut. |
| Backup Synology | Nama job, hasil job, keberadaan data yang diperiksa, waktu mulai/selesai, folder atau restore point yang diperiksa, ukuran beserta satuan bila tersedia, serta masalah/tindak lanjut. |
| VM vSphere | Nama VM, status daya aktual, status daya yang diharapkan, kondisi/alarm, penggunaan CPU/RAM dan ruang disk tersisa bila diukur, serta masalah/tindak lanjut. |

### Synology

**Tanggal backup yang diperiksa** berbeda dari **Waktu pengecekan**. Jadwal yang ditampilkan mengikuti kebutuhan pengguna: setiap hari **00.01 WIB**. Petugas dapat memeriksa hasilnya setelah job berjalan dan menentukan tanggal backup yang sedang diperiksa.

Status job **Berhasil** memerlukan waktu mulai dan selesai. Waktu selesai tidak boleh mendahului waktu mulai; waktu backup tidak boleh mendahului tanggal backup atau melewati waktu pengecekan. Keberadaan data dicatat terpisah sebagai **Ya, sudah diperiksa**, **Tidak ada**, atau **Belum dapat diverifikasi**. Pilihan Ya memerlukan folder/restore point yang diperiksa. Job berhasil dengan data tidak ditemukan tetap dihitung perlu perhatian.

Jadwal 00.01 merupakan informasi untuk pencatatan, bukan scheduler atau bukti bahwa backup sudah dijalankan. Petugas memverifikasi hasil pada Synology.

### vSphere

Satu baris untuk setiap VM yang diperiksa. **Isi nama VM dari master** menambahkan nama VM aktif pada department IT yang diizinkan bagi petugas. Tombol ini mempertahankan baris bernama yang sudah diisi dan tidak menggandakan nama yang sama. Status aktual dan kondisi/alarm tetap **Belum diperiksa** atau kosong sampai petugas mengisinya. Jika tidak ada VM aktif di master, pemberitahuan meminta petugas mengisi nama secara manual.

Daftar tersebut merupakan bantuan pengisian dari register aplikasi. Petugas tetap mencocokkan seluruh VM pada vSphere, menambahkan VM yang belum terdaftar dan memisahkan pencatatan antar-host/vCenter sesuai kebutuhan. Aplikasi tidak terhubung ke inventaris langsung vSphere.

VM yang direncanakan mati dapat dicatat dengan status daya yang diharapkan **Mati terencana**. VM mati saat seharusnya menyala dihitung perlu perhatian. Warning/Critical pada kondisi VM juga memerlukan catatan masalah/tindak lanjut.

## Riwayat dan akses

Daftar menampilkan waktu, jenis, sistem/perangkat, jumlah baris diperiksa dibanding total, jumlah hasil perlu perhatian, Draft/Final dan petugas. Department tidak diulang sebagai kolom karena pencatatan dikelola IT. Tersedia pencarian sistem/perangkat atau petugas, filter jenis/status/periode serta hasil perlu perhatian. Tanggal filter tidak sah menghasilkan daftar kosong tanpa meneruskan tanggal tidak valid ke PostgreSQL.

Kolom **Perlu perhatian** menghitung hasil per baris yang bermasalah, belum berhasil, atau belum terverifikasi. Baris **Belum diperiksa** dihitung pada penyebut kolom **Diperiksa**, sehingga nol temuan tidak menyatakan pemeriksaan sudah lengkap. Angka merupakan kondisi saat pencatatan; modul tidak melacak status penyelesaian tindak lanjut terpisah.

Hak akses menggunakan permission pemeriksaan Infrastruktur IT yang sudah ada dan cakupan department existing:

- `it.inspections.create`: membuat catatan dan mengubah draft milik sendiri dalam cakupan department.
- `it.inspections.manage`: mengelola draft dalam cakupan department.
- `it.inspections.view`: membaca catatan yang diizinkan.
- `it.reports.view` / `it.reports.export`: membaca catatan final yang diizinkan. Modul ini belum menambahkan ekspor monitoring tersendiri.

Pengguna nonaktif ditolak. Tidak ada permission atau role baru yang diberikan kepada pengguna. Petugas, nama petugas, waktu finalisasi dan petugas finalisasi berasal dari server. Jenis, department dan identitas pencatat tidak dapat diubah setelah catatan tersimpan. Catatan final dan riwayat tidak dapat dihapus lewat aplikasi. Revisi draft diperiksa saat menyimpan untuk menolak penimpaan oleh halaman lama.

Catatan ini terpisah dari checklist inspeksi perangkat yang sudah ada. Pencatatan monitoring tidak otomatis membuat tiket Service Desk atau temuan pemeriksaan perangkat. Masalah/tindakan dicatat pada baris monitoring; permintaan layanan tetap mengikuti alur Service Desk existing.

## Aktivasi lokal

Migration khusus `2026_10_20_020000_create_it_daily_monitorings_table` sudah diterapkan pada PostgreSQL lokal. Akun runtime tetap terbatas; migration memakai kredensial administrator lokal yang sudah disimpan terpisah, tanpa mengganti konfigurasi runtime. Pemeriksaan jumlah record bisnis dan permission sebelum/sesudah identik. Tidak menambahkan data monitoring contoh ke database aplikasi.

Cache komponen Filament dan route sudah dibangun ulang. Implementasi menambahkan enum, model, policy, service, resource beserta empat halaman, migration, stylesheet yang dibatasi pada resource monitoring dan test fitur. Provider panel memuat stylesheet baru; selector stylesheet hanya memengaruhi halaman monitoring.

## Verifikasi

- SQLite fitur monitoring: **11 tests / 178 assertions**, lulus.
- PostgreSQL 18.4 fitur monitoring: **11 tests / 200 assertions**, lulus. Database sementara dibersihkan dan fingerprint database aplikasi tidak berubah selama tes.
- Cakupan: ketiga form create/view/edit/list; filter; input tidak sah; backup berhasil tanpa data; urutan waktu backup; VM mati terencana; finalisasi terkunci; akses lintas department; draft petugas lain; pembaca laporan hanya melihat final; metadata palsu dibuang; revisi draft lama ditolak; penambahan nama VM tanpa hasil rekaan; draft sebagian tidak bisa difinalisasi.
- Pemeriksaan browser lokal: navigasi menu, pergantian ketiga form, perubahan isian wajib, tambah/hapus baris, dialog dan Escape, toggle melalui Space, validasi form kosong, Batal kembali ke daftar, filter, state kosong, serta layout desktop/tablet/ponsel. Uji simpan yang menulis record dilakukan pada database uji lewat Livewire.
- Pint, sintaks provider panel dan `git diff --check` lulus.

Log: `output/it-daily-monitoring-review/monitoring-tests.log`, `postgres-tests.log`, `sqlite-tests.log`, dan `activation.log`.

Suite IT keseluruhan yang dijalankan menghasilkan **145/146 tes lulus**. Satu kegagalan berada pada `ItAssetNumberTest::test_generated_number_is_editable_and_corrections_keep_the_counter_ahead_without_duplicates`: setelah koreksi nomor aset, ekspektasi `00036/APCA/IT/2025` tetapi hasil `00002/APCA/IT/2025`. File penomoran aset sudah memiliki perubahan sebelum pekerjaan monitoring dimulai dan tidak diubah dalam pekerjaan ini. Masalah dengan hasil yang sama telah dicatat pada `docs/PENGECEKAN-ULANG-2026-10-20.md`. Kegagalan ini dilaporkan terpisah dan tidak dinyatakan lulus.

Audit UI setelah implementasi: `anti-slop/audit-002-2026-10-20.md`.

## Penyesuaian format dan department IT

Atas arahan pengguna berikutnya pada 20 Oktober 2026, tampilan form disamakan dengan tabel Pengecekan Infrastruktur. Komponen style dan notifikasi/error pemeriksaan digunakan kembali, berikut warna header, garis tabel, isian langsung dan tombol terpisah Simpan Draft/Finalisasi. Field tetap memakai komponen Filament dengan validasi dan penyimpanan service yang sama.

Department kanonis berasal dari master berkode `IT`, tanpa bergantung pada department yang dikirim form. Payload department lain diabaikan dan hasil tetap tersimpan pada IT. Pengguna dengan permission pencatatan tetapi tanpa cakupan IT tidak dapat membuat catatan. Pengubahan draft juga mensyaratkan catatan dikelola IT. Identitas, isi pemeriksaan, status dan penguncian final tetap mengikuti aturan semula. Catatan operasional yang sudah ada diperiksa dan memang berada pada department IT; tidak ada pemindahan catatan.

Pengujian setelah perubahan:

- SQLite: **13 tests / 210 assertions lulus** (`style-it-only-tests.log`).
- PostgreSQL 18.4: **13 tests / 236 assertions lulus** (`style-it-only-postgres.log`). Database uji dibersihkan; fingerprint schema, migration dan jumlah record aplikasi tidak berubah.
- Dua skenario tambahan menguji penetapan IT di server, penghapusan pilihan department, penolakan penulis tanpa cakupan IT, struktur lembar tabel, serta tombol Finalisasi yang benar-benar menyimpan dan mengunci record melalui alur yang sama.
- Browser memeriksa header tabel, label rincian, pilihan department yang tidak ada, tombol Draft/Finalisasi, validasi isian kosong, scroll keyboard, dan lebar tablet/ponsel. Pada clientWidth 836 dan 339 px, scrollWidth halaman sama dengan clientWidth; tabel 960 px berada dalam pembungkus scroll sendiri. Browser tidak menambah atau mengubah record operasional saat pemeriksaan.
- Pint dan whitespace lulus. Perubahan tidak memerlukan migration baru.

Audit sesudah penyesuaian: `anti-slop/audit-003-2026-10-20.md`.

## Penerapan antislop lanjutan

Atas permintaan pengguna, antislop diterapkan kembali pada hasil implementasi. Form mempertahankan format Pengecekan Infrastruktur dan department IT. Petunjuk di bawah tabel kini mengikuti jenis pemeriksaan, pilihan kosong dan filter menggunakan teks yang menjelaskan tujuannya, serta jarak antarmetrik dipadatkan.

Halaman rincian menampilkan teks hasil tersimpan dengan label, bukan input nonaktif. Isian opsional yang kosong ditandai **Tidak dicatat** atau **Tidak ada catatan**. Angka nol tidak diganti dengan keterangan kosong; tanggal/waktu backup yang belum tersedia tidak dibuatkan nilai pengganti. Tanggal rincian menggunakan format `dd/mm/yyyy HH:mm`. Catatan beberapa baris dipertahankan dan HTML ditampilkan sebagai teks. Form catatan mendukung beberapa baris dan menyesuaikan tinggi.

Penyimpanan draft dari submit form juga diperiksa dengan flag finalisasi default yang dinormalisasi menjadi boolean. Hak akses, department IT, data operasional dan aturan penguncian final tidak diubah.

Verifikasi terbaru: SQLite **14 tests / 240 assertions** dan PostgreSQL **14 tests / 268 assertions**, seluruhnya lulus. Log `antislop-applied-tests.log` dan `antislop-applied-postgres.log` berada di `output/it-daily-monitoring-review/`. Audit empat blok PASS tersedia di `anti-slop/audit-004-2026-10-20.md`.

## Penyederhanaan alur pengisian

Atas masukan pengguna bahwa monitoring kurang ramah pengguna, alur diperbarui dengan pintasan per jenis, Periksa lagi, blok isian responsif per pemeriksaan, bagian opsional yang dapat ditutup, progres hasil dipilih dan label hasil pada header blok. Validasi form/server kini berbahasa Indonesia; kesalahan jenis sebelumnya dibersihkan saat jenis diubah. Pemanggilan daftar VM menjelaskan hasilnya melalui pemberitahuan, termasuk saat master kosong.

Verifikasi setelah perubahan: SQLite **18 tests / 362 assertions** dan PostgreSQL 18.4 **18 tests / 398 assertions**, lulus. Log `friendly-workflow-tests.log` dan `friendly-workflow-postgres.log` berada di `output/it-daily-monitoring-review/`. Tes tambahan memeriksa pintasan, pengulangan tanpa menyalin hasil, perlindungan sumber dan akses, feedback master kosong serta pesan koreksi Indonesia. Audit dan alasan desain tersedia di `anti-slop/audit-005-2026-10-20.md`. Tidak membutuhkan migration baru.
