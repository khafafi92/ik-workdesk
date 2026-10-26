# Audit Akses Menu, 20 Oktober 2026

Pemeriksaan mencocokkan seluruh menu resource dan halaman yang terdaftar di panel dengan checklist User Management, izin aksi, dan aturan akses khususnya. Form manpower HR tetap berada di Service Desk, sehingga menggunakan izin Service Desk dan tidak membutuhkan menu baru.

Pembaruan 26 Oktober: CBO dan COO menggunakan level Management; kewenangan Legal diberikan melalui checklist Approval Legal. Lihat [panduan Management](MANAGEMENT-ACCESS-2026-10-26.md).

## Pilihan yang dilengkapi

| Kelompok | Pilihan baru | Izin |
| --- | --- | --- |
| Master Data | Departments | `master.departments.manage` |
| Master Data | Employees | `master.employees.manage` |
| Attendance Report | Work Locations | `master.work-locations.manage` |
| Reports | Ekspor Reports | `report.export` |
| Service Desk | Kelola Service Desk | `tickets.manage` |
| Service Desk | Kirim komentar / balasan | `comments.create` |
| Daily Reports | Lihat Due Diligence Findings | `findings.view` |
| Daily Reports | Kelola Due Diligence Findings | `findings.manage` |

Ekspor hanya dapat digunakan pada laporan yang boleh dibuka. Findings berada dalam Work Logs untuk permintaan Due Diligence. Izin kelola Service Desk dan komentar tetap mengikuti akses tiket, department, serta kondisi proses yang sudah ada. Label Saldo Department disamakan dengan nama menu ATK.

Tiga izin master yang baru bersifat terpisah: memilih Work Locations tidak memberikan akses Employees atau Departments. Izin lama `master-data.manage` tetap diterima pada ketiga master tersebut. Saat pengguna dengan izin master lama dibuka dan disimpan dari checklist, pilihan yang mewakili akses master lama ikut terisi, termasuk kategori request dan kategori task.

Halaman daftar Service Desk sekarang memeriksa izin ketika URL dibuka langsung. Pengguna tanpa izin Service Desk mendapat 403; sebelumnya navigasi disembunyikan tetapi halaman daftar kosong masih dapat dibuka.

## Menu lain yang diperiksa

- Infrastruktur IT: delapan pilihan kemampuan perangkat, pemeriksaan, tindak lanjut, laporan, dan ekspor sudah tersedia.
- Master Data, Attendance Report, Reports, Surat, LTRO, ATK, Meeting Room, Vehicle Booking, Notifications, Service Desk, dan Daily Reports dicocokkan dengan navigasi panel melalui pengujian inventaris. Menu baru yang belum dipetakan akan membuat pengujian ini gagal.
- Dashboard, Tanya Aku, dan Chat Room Global memang mengikuti akun yang sudah login serta batas data masing-masing. Perilaku tersebut dipertahankan.
- User Management mengikuti level Administrator. Master Tanya Aku khusus administrator sistem. Menu Internal 9 khusus role administrator sistem. Kewenangan ini tidak ditambahkan sebagai checkbox operasional.
- Persetujuan Legal tetap melalui kewenangan CBO/`legal-tasks.approve` yang sudah ada.
- Role Management dan halaman lama yang dinonaktifkan tidak dimunculkan kembali.
- `reminders.manage` merupakan izin lama alternatif untuk membuka Reminders; tidak ada aksi tersendiri yang memerlukan checkbox kedua. Notifikasi tiket pada lonceng mengikuti izin Service Desk dan department tujuan, bukan checkbox Reminders.
- `findings.respond` tidak memiliki pemakaian pada kode fitur saat audit. Registrasi lama dipertahankan tanpa membuat pilihan yang tidak memiliki perilaku.

## Penerapan lokal dan batas perubahan

Seeder `MenuAccessPermissionSeeder` menambahkan tiga definisi izin master tanpa menautkannya ke pengguna atau role. Seeder ini juga dipanggil oleh AccessControlSeeder untuk instalasi baru. Untuk aplikasi yang sudah berjalan, jalankan hanya:

```shell
php artisan db:seed --class=MenuAccessPermissionSeeder --force
```

Seeder khusus sudah dijalankan di aplikasi lokal. Hash tabel `permission_user`, `permission_role`, dan `role_user` sebelum dan sesudah sama. Tidak ada akses pengguna nyata yang dicentang, tidak ada akun percobaan dibuat, dan tidak ada email percobaan dikirim.

Level dan role tetap mengikuti aturan lama. Checkbox menata izin langsung per pengguna; izin yang diwarisi dari role tidak dicabut oleh checkbox. Pengujian role manager/supervisor diperbarui agar sesuai dengan perilaku seeder yang sudah berlaku: akses menu diberikan lewat checklist, bukan otomatis dari level.

## Validasi

- PASS: 55 pengujian dengan 447 assertion untuk katalog menu, pengaturan pengguna, hierarki akses, Service Desk, manpower, dan security hardening.
- PASS: 49 pengujian tambahan dengan 326 assertion untuk administrator, beberapa role, Work Logs, kolaborasi tiket, persetujuan Legal, laporan, sumber reminder, batas akses Infrastruktur IT, dan dashboard.
- PASS: izin master terpisah, akses warisan tersimpan, perubahan izin melalui form Edit User, pembatasan department, komentar berdasarkan tiket terkait, ekspor terpisah dari akses laporan, pencabutan izin, dan penolakan URL Service Desk tanpa izin.
- PASS: Pint untuk berkas PHP yang diubah, pemeriksaan sintaks, `git diff --check`, dan pemeriksaan keamanan lokal `security:check`.

## Delivery Gate tampilan

Anti-slop diterapkan selama pekerjaan menggunakan gaya Internal9/Filament yang sudah ada, dengan ENERGY/RHYTHM/MOTION 1/1/1. Tidak dibuat aset atau pola navigasi baru. Ruang sentuh checkbox diperbesar untuk mencegah salah memilih izin; fokus keyboard diperjelas.

- PASS Hard Gate: aplikasi dijalankan; pilihan baru terlihat pada form Create User; centang lewat klik dan Space dapat dibatalkan tanpa menyimpan akun. Tidak ada data contoh atau klaim keamanan menyeluruh pada UI.
- PASS aksesibilitas: label checkbox setinggi 44 piksel; teks `#111827`, border `#334155`, dan fokus solid `#0068d9` memenuhi kontras terhadap putih. Pilihan diberi label dan bisa digunakan dengan keyboard.
- PASS responsif: pada lebar CSS 391 piksel, clientWidth dan scrollWidth sama-sama 391; pada 768 piksel keduanya 768. Label panjang Findings tetap muat. Ukuran browser dikembalikan setelah pengujian.
- PASS Purpose Gate: kelompok dan pilihan mengikuti modul yang sudah ada; penjelasan membedakan akses melihat, kelola, balasan, dan ekspor agar pemberian akses dapat diputuskan dengan tepat.
- PASS Liveliness dan Quality Locks: komponen checkbox, section, font, dan warna mengikuti aplikasi; tidak ada dekorasi, ikon, atau statistik baru.
- PASS Craftsmanship fungsional: inventaris navigasi dan siklus simpan/muat/pencabutan diuji; console browser tidak berisi error. Bukti ukuran dan status browser disimpan di `output/menu-access-review-20261020/browser-checks.json`.

Tangkapan layar tidak tersedia: perintah capture browser berulang kali melewati batas waktu. Verifikasi tampilan menggunakan keadaan UI dan ukuran DOM yang benar-benar dirender; tidak dibuat gambar pengganti atau klaim pemeriksaan visual dari screenshot.
