# Spesifikasi Infrastruktur IT

Status: spesifikasi Tahap 1 dan fondasi Tahap 2. Preview visual berbasis data contoh kemudian ditambahkan atas permintaan pengguna; lihat [IT-INFRASTRUCTURE-UI-REVIEW.md](IT-INFRASTRUCTURE-UI-REVIEW.md). Halaman operasional, export dan integrasi ticket belum diterapkan. Migration dan seeder tidak dijalankan pada database aplikasi.

## Objek dan jenis

Grup menu tahap berikutnya tetap `Infrastruktur IT`: Master Perangkat, Pengecekan Infrastruktur, Rekap & Laporan.

| Kategori | Jenis | Bentuk objek |
| --- | --- | --- |
| Server & Data Center | server, storage, ups, air_conditioner | physical |
| Server & Data Center | hypervisor, virtual_machine | virtual |
| Server & Data Center | server_room | room |
| Network Infrastructure | fortigate, access_point, switch | physical |
| Laptop/PC | laptop, pc | physical |

`it_devices` adalah register objek pemeriksaan. `server_room` merujuk `work_locations` existing dan wajib memiliki lokasi. Ruang tidak memiliki nomor aset, serial number, pemakai, atau status siklus hidup aset. Kode register tetap unik. Ruang tidak dihitung sebagai aset pada dashboard. Hypervisor adalah objek logis; host fisiknya dapat dirujuk melalui `parent_device_id`. Relasi parent tidak boleh membentuk siklus.

Nomor aset dan serial number perangkat boleh belum tersedia. Nomor aset unik bila diisi; serial number tidak dipaksa unik. Department pengelola wajib diisi untuk semua objek, termasuk ruangan. Department pemakai tidak menentukan cakupan akses.

## Status

Nilai database memakai string snake_case, melalui backed enum PHP, mengikuti pola status aplikasi. Nilai enum disimpan sebagai string lintas PostgreSQL/SQLite.

| Dimensi | Nilai database | Label |
| --- | --- | --- |
| Siklus hidup | in_use, spare, under_repair, unused, damaged, disposed | Digunakan, Cadangan, Dalam Perbaikan, Tidak Digunakan, Rusak, Dihapuskan |
| Lisensi Windows/Office | valid, unofficial, unverified, not_applicable | Original/Valid, Tidak Resmi/Crack, Belum Diverifikasi, Tidak Berlaku |
| Kondisi item | normal, warning, critical, not_applicable, unchecked | Normal, Warning, Critical, N/A, Belum Diperiksa |
| Dokumen | draft, finalized | Draft, Final |
| Tindak lanjut | open, in_progress, resolved | Terbuka, Dalam Proses, Selesai |

`is_active` hanya berarti objek boleh dipilih untuk pemeriksaan baru, bukan status kondisi atau pemakaian. Objek perangkat baru default `unused`; objek room tidak mempunyai lifecycle. Objek disposed harus nonaktif dan memiliki tanggal serta alasan penghapusan administratif. Tidak ada penghapusan data historis. Lisensi default `unverified`. Status selain unverified wajib disertai petugas verifikasi, waktu, dan catatan dasar verifikasi, termasuk alasan not_applicable. Aplikasi tidak menyimpulkan lisensi dari versi OS atau status aktivasi.

## Database dan relasi

| Tabel | Isi dan relasi |
| --- | --- |
| it_devices | Kode, kategori/jenis/bentuk objek, identitas, spesifikasi, work_location_id, detail lokasi, managing_department_id, assigned_employee_id/nama alternatif, parent_device_id, lifecycle, disposal, is_active, catatan dan pembuat/pengubah |
| it_device_computer_details | Satu-ke-satu perangkat laptop/pc: OS, versi Windows, Office, kedua lisensi dan bukti verifikasi masing-masing, RAM, daftar HDD/SSD |
| it_checklist_templates | Kode, versi, kategori/jenis dan aktif; unique code+version dan device_type+version |
| it_checklist_items | Template, kode stabil, kelompok, label, petunjuk, urutan, jenis nilai, satuan; unique template+code |
| it_inspections | Nomor, perangkat, template, checked_at, inspector_id, lokasi aktual, department pengelola saat pemeriksaan, salinan identitas/perangkat/petugas/lokasi, status dokumen, kondisi dan counts, finalized_at/by |
| it_inspection_results | Satu hasil per inspection+item; salinan definisi item, kondisi, nilai pengukuran terstruktur, catatan, temuan dan alasan N/A |
| it_inspection_follow_ups | Satu temuan per hasil Warning/Critical, tindakan, assignee, target selesai, status, resolusi dan penyelesai; department diwarisi dari pemeriksaan |
| it_inspection_events | Log append-only: inspection, actor, action, meta dan created_at |

Hasil dan tindak lanjut memakai foreign key gabungan untuk memastikan item benar-benar milik template pemeriksaan dan tindak lanjut milik hasil pemeriksaan tersebut. Riwayat pemeriksaan berasal langsung dari inspections. Identitas perangkat/pemakai, spesifikasi, lisensi berikut verifikasinya, lifecycle, lokasi, dan petugas disalin saat draft dibuat. Perubahan master tidak mengubah salinan ini. Definisi template/item yang sudah digunakan tidak dapat diubah atau dihapus; versi baru dibuat untuk perubahan checklist. Menonaktifkan template tetap diperbolehkan.

Nomor inspeksi wajib unik; pembentukan nomor dan submit idempotent dilaksanakan bersama alur form pada tahap berikutnya. Tidak ada tabel, foreign key, observer, atau perubahan model ticket pada tahap ini.

## Hak akses

Semua hak menggunakan `User::hasPermission`, termasuk permission langsung per user. Sys Administrator mengikuti bypass existing. Akun employee nonaktif tetap ditolak. Tidak ada pemberian otomatis ke manager, requester, administrator, atau seluruh anggota department IT.

| Permission | Hak |
| --- | --- |
| it.devices.view | Membaca master dalam department pengelola yang dapat diakses |
| it.devices.manage | Membuat/mengubah master dalam cakupan; termasuk membaca master |
| it.inspections.view | Membaca pemeriksaan dalam cakupan |
| it.inspections.create | Membuat draft dan mengubah/finalisasi draft sendiri dalam cakupan, termasuk persiapan tindak lanjut draft; termasuk membaca pemeriksaan |
| it.inspections.manage | Mengelola semua draft dalam cakupan; termasuk membuat dan membaca pemeriksaan |
| it.follow-ups.manage | Membaca pemeriksaan serta mengelola tindak lanjut dalam cakupan |
| it.reports.view | Membaca laporan dalam cakupan |
| it.reports.export | Membaca dan mengekspor laporan dalam cakupan |

Record master mengikuti managing_department_id saat ini. Record pemeriksaan mengikuti managing_department_id yang disalin saat pemeriksaan dibuat, sehingga mutasi perangkat tidak memindahkan akses riwayat lama. Department tambahan tetap memakai department_user_accesses existing. Petugas IT yang diberi izin dan cakupan department pengelola IT dapat memeriksa laptop dengan pemakai dari department lain. Data pemakai tidak memperluas akses. Pemberian izin laporan tidak otomatis membuka halaman master. Mapping pilihan izin pada Users dan menu baru ditunda sampai tahap tampilan agar tidak menampilkan menu menuju halaman yang belum tersedia.

## Draft, finalisasi, dan tindak lanjut

1. Draft hanya untuk objek aktif, belum disposed, dan template aktif dengan jenis/kategori cocok. Petugas harus aktif. Seluruh butir template harus disimpan sebagai result; default unchecked.
2. Draft boleh belum lengkap. Warning/Critical wajib memiliki temuan sejak disimpan; N/A wajib memiliki alasan. Nilai ukur tidak otomatis menghasilkan status sampai batas SOP disepakati.
3. Finalisasi memerlukan tepat seluruh butir template, tanpa unchecked, catatan finalisasi, salinan identitas, serta identitas/waktu finalisasi. Setiap hasil Warning/Critical wajib memiliki satu tindak lanjut dengan uraian tindakan dan penanggung jawab aktif. Petugas boleh menyiapkan dan mengubah tindak lanjut draft sendiri dengan it.inspections.create; pengelolaan tindak lanjut setelah final memerlukan it.follow-ups.manage.
4. Kondisi ringkasan: Critical jika ada Critical, lalu Warning jika ada Warning, lalu unchecked jika masih ada unchecked, lalu Normal jika ada Normal, selain itu N/A. Counts seluruh status tetap disimpan; keberadaan Critical tidak menyembunyikan item belum diperiksa. Draft tidak masuk rekap hasil final.
5. Dokumen final dan hasilnya tidak boleh diubah/dihapus. Tidak ada reopen atau koreksi inplace; koreksi kelak berupa pemeriksaan pengganti dengan alasan dan log, setelah desainnya direview.
6. Follow-up dapat berubah open -> in_progress -> resolved atau open -> resolved. Resolved wajib memiliki catatan penyelesaian, petugas aktif, dan waktu selesai. Reopen belum tersedia. Perubahan tidak mengubah snapshot, status, counts, atau nilai hasil pemeriksaan final.
7. Model guard merupakan fondasi validasi aplikasi. Alur form berikutnya wajib memakai transaksi dan lock record induk untuk seluruh perubahan hasil, finalisasi dan tindak lanjut; query builder bulk update/delete tidak boleh dipakai untuk melewati model guard. Pencatatan actor dan event wajib dilakukan oleh alur transaksi tahap tersebut, bukan diisi dari browser.

## Checklist

Tersedia satu template versi 1 untuk masing-masing 12 jenis. Item terpisah untuk setiap fungsi, tidak ada satu status gabungan per kelompok. Seeder tidak memasang ambang suhu, CPU, RAM, kapasitas, firmware, atau klaim lisensi tanpa SOP. Fitur yang tidak tersedia pada perangkat ditandai N/A dengan alasan, bukan Normal.

Server mencakup fisik/hardware, resource, RAID/storage, OS/service/database/aplikasi, file sharing/jaringan, backup/restore, patch/security/log dan lingkungan. Ruang server mencakup lingkungan, AC, UPS, listrik dan keamanan ruang. UPS, AC, storage, hypervisor dan VM memiliki pemeriksaan khusus. FortiGate, AP dan switch masing-masing memiliki checklist berbeda. Laptop/PC mencakup daya, performa, disk health/kapasitas, layar/input, jaringan, antivirus, patch dan verifikasi lisensi.

## Kontrak rekap tahap berikutnya

- Total aset: distinct register non-room, dilaporkan per physical/virtual dan lifecycle; disposed tetap terdaftar, aset aktif dihitung terpisah.
- Total ruang: distinct register room, terpisah dari aset.
- Total pemeriksaan: jumlah transaksi; draft dan final dipisah.
- Kondisi terakhir aset: satu final terbaru per device berdasarkan checked_at lalu id, bukan created_at atau updated_at. Perangkat tanpa final ditampilkan Belum Diperiksa. Pemeriksaan ulang tidak menambah total aset.
- Temuan belum selesai: jumlah result Warning/Critical pada final dengan follow-up open/in_progress, termasuk temuan historis yang belum diselesaikan. Pemeriksaan Normal berikutnya tidak otomatis menutup temuan lama.
- Seluruh query, detail dan export wajib memakai permission dan department scope yang sama; filter periode tidak mengubah arti kondisi terakhir tanpa label periode yang jelas.

## Batas delivery Tahap 2

Tidak menjalankan artisan migrate/db:seed pada database aplikasi, termasuk dua migration APCA tertunda. Pengujian menggunakan allowlist migration induk dan migration IT baru; kedua migration APCA tidak pernah dimuat. Harness mendukung SQLite :memory: dan database PostgreSQL sementara yang dibuat runner khusus dengan nama unik, guard koneksi, serta rollback per pengujian. Database PostgreSQL sementara dibersihkan setelah suite selesai. Tidak ada perubahan Ticket Master, LTRO, ATK, route, CSS, sidebar atau halaman existing. Hasil pengujian PostgreSQL atas permintaan pengguna tercatat di [IT-INFRASTRUCTURE-POSTGRES-TEST.md](IT-INFRASTRUCTURE-POSTGRES-TEST.md). UI tetap menunggu review tahap berikutnya.
