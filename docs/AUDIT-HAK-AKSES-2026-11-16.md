# Pemeriksaan katalog Hak Akses lokal, 16 November 2026

Katalog Akses Menu pada User Management dibandingkan dengan resource/page yang ditemukan panel Filament, aturan akses halaman, serta record permission di PostgreSQL lokal `ik_workdesk`. Terdapat 18 kelompok dengan 80 pilihan. Sebanyak 72 resource/page mendaftarkan navigasi untuk administrator; halaman yang disembunyikan dari sidebar diperiksa sebagai halaman turunan atau menu lama.

## Hasil dan perbaikan

Pilihan Rekap Material PR sudah ada pada kode katalog, tetapi record `scm.material-register` belum tersedia di database lokal. Migration khusus `2026_11_11_000001_add_material_register_permission` diterapkan untuk menambahkan record tersebut. Setelah perbaikan, seluruh pilihan katalog memiliki permission aktif dan pemetaan yang valid. Jumlah permission menjadi 110.

Fingerprint baris existing pada seluruh 127 tabel public dibandingkan sebelum/sesudah dalam transaksi yang sama, dengan pengecualian hanya record permission dan catatan migration yang ditambahkan. Seluruh baris existing identik, termasuk PO, user, role, dan penetapan akses. Permission baru tidak diberikan otomatis kepada user maupun role. Tidak menjalankan seeder keseluruhan, tidak mengubah password, dan tidak menjalankan migration modul lainnya.

| Kelompok Akses Menu | Jumlah pilihan |
| --- | ---: |
| Inventori | 3 |
| Permintaan | 2 |
| SCM | 5 |
| Purchasing Officer | 1 |
| Finance | 3 |
| Kewenangan Persetujuan | 1 |
| Infrastruktur IT | 8 |
| Master Data | 6 |
| Attendance Report | 4 |
| Reports | 8 |
| Surat | 8 |
| LTRO Management | 3 |
| ATK | 12 |
| Meeting Room | 3 |
| Vehicle Booking | 3 |
| Notifications | 1 |
| Service Desk | 4 |
| Daily Reports | 5 |

Finance mencakup Pembayaran PO, Master COA, dan Master anggaran. Inventori mencakup pengelolaan, tampilan seluruh entitas, serta distribusi gudang departemen. Rekap Material PR merupakan izin tersendiri dari proses pengadaan.

## Menu yang mengikuti aturan akses lain

Tidak semua entri sidebar menggunakan satu checkbox tersendiri. Pola existing berikut dipertahankan:

| Menu | Aturan existing |
| --- | --- |
| Monitoring Harian IT | Mengikuti izin Pengecekan Infrastruktur. |
| Anggaran departemen | Memakai izin `scm.request` yang sama dengan PR/SR. Dua pilihan tampil bersama ketika state dimuat ulang. |
| Dashboard | Halaman awal pengguna yang sudah login. |
| Tanya Aku dan Chat Room Global | Pengguna yang sudah login; percakapan/data mengikuti cakupan masing-masing. |
| Master Tanya Aku | Khusus administrator sistem. |
| User Management | Mengikuti kewenangan administrasi pengguna, bukan checkbox operasional. |
| Persetujuan PR/SR/CBE | Mengikuti penugasan persetujuan, kewenangan pemroses, atau administrator sistem. Checkbox Approval Legal mengatur persetujuan Service Desk ke Legal. |
| Nomor PR/SR dan tanda tangan profil | Mengikuti kewenangan requester, pemroses, konfigurator, atau penandatangan terkait. |
| Data Vendor dan pengaturan SCM | Mengikuti kewenangan proses/konfigurasi pengadaan. |

Permission lama `master-data.manage`, `findings.respond`, dan `reminders.manage` tidak menandakan modul sidebar baru yang hilang. Akses seluruh Master Data lama diubah menjadi pilihan submenu saat formulir dimuat. Respons finding mengikuti akses Work Logs/Service Desk dan kepemilikan/department. Reminders memiliki pilihan akses pribadi; permission lama dan batas data existing tetap dipertahankan. Role Management disembunyikan dari sidebar; pengaturan pengguna tetap melalui User Management.

## Verifikasi

Pengujian katalog mencakup navigasi, pemisahan master, grant/revoke Rekap Material PR, pemeliharaan akses master lama, simpan melalui form User Management tanpa mengubah role, batas department, ekspor, serta seeding tanpa pemberian akses otomatis. Pengujian tambahan memeriksa seluruh 80 pilihan: record izin aktif tersedia, izin yang disimpan sesuai pemetaan, pilihan tetap terpilih setelah dimuat ulang, dan pencabutan menghapus izin tersebut. Semua pengujian menggunakan SQLite in-memory terpisah dari database aplikasi.

Tidak ada perubahan tata letak, label katalog, policy, atau aturan akses bisnis. Perbaikan diterapkan pada database lokal; pemeriksaan ini tidak mengubah server.

Hasil akhir: 8 tests / 562 assertions lulus. Pint, sintaks PHP, dan pemeriksaan whitespace lulus. PO existing tetap terbaca melalui model aplikasi dengan relasi PR/CBE dan snapshot yang tersedia.
