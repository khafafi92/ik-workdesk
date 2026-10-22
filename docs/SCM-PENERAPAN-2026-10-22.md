# Penerapan SCM ke IK WorkDesk

22 Oktober 2026. Pengguna mengizinkan penerapan ke aplikasi `http://10.1.20.97:8093` setelah trial lokal. SCM terintegrasi dalam repository dan aplikasi IK WorkDesk yang sama; tidak ada project atau aplikasi SCM terpisah.

## Versi dan target

- Repository: `khafafi92/ik-workdesk`, branch `main`.
- Kode sebelum penerapan: `6cfe6f7e2cbf38a6a85ac0c296da72514ef23416`.
- Kode modul yang diterapkan: `71c72ad9bbb50e79eb1a77bd91a2239e4672e86d`.
- [GitHub Actions untuk kode modul](https://github.com/khafafi92/ik-workdesk/actions/runs/39737306159): success.
- Lokasi aplikasi: `/var/www/ik-workdesk`, yang pada server menunjuk ke `/var/www/ik-workdesk-releases/update-0956961`.

## Penerapan

Aset dibangun di direktori staging sebelum maintenance. Backup aplikasi, environment dan database PostgreSQL berada di direktori privat:

`/home/r92/ik-workdesk-releases/20261022-140448-71c72ad9bbb5-scm/backup`

Arsip database diperiksa dengan `pg_restore --list`. Penerapan menggunakan fast-forward Git, autoload baru, dan aset hasil build. Migration `2026_10_22_100000_create_scm_procurement_tables` menambahkan 15 tabel SCM. Hanya `ScmSeeder` yang dijalankan untuk tiga izin dan jenis dokumen NPWP/NIB/SBU. Seeder demo tidak dijalankan.

Akun utama khafafi (ID 1) menerima `scm.request`, `scm.process`, dan `scm.configure` secara eksplisit. Permission lama, role dan cakupan departemen tetap dipertahankan. Tidak ada pemberian otomatis kepada semua administrator. Direktori dokumen `storage/app/scm-private` disiapkan untuk aplikasi, tanpa URL publik.

Batas unggahan SCM server diatur menjadi 2 MB mengikuti konfigurasi PHP server yang sudah ada. Cache Laravel dan Filament dibangun kembali. Worker yang sebelumnya aktif dihentikan saat maintenance lalu diaktifkan kembali. Aplikasi sudah keluar dari maintenance.

Percobaan awal berhenti pada pemeriksaan path sebelum kode atau database diubah, karena PHP melihat path fisik symlink. Aplikasi dan worker dikembalikan aktif. Pemeriksaan disesuaikan agar membandingkan `realpath('/var/www/ik-workdesk')`, kemudian penerapan berhasil.

## Hasil verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Data bisnis lama | Jumlah record dan fingerprint seluruh isi 74 tabel yang diperiksa identik sebelum/sesudah. Pemeriksaan mengecualikan tabel permission, pivot permission, migration, cache, session dan antrean yang memang dapat berubah selama penerapan |
| Tabel SCM | 15 tabel tersedia. Permintaan dan vendor masih kosong; tidak ada transaksi demo yang dimasukkan |
| Akun utama | Ketiga izin SCM aktif untuk khafafi, tanpa mengubah metode otorisasi modul |
| Halaman terautentikasi | Dashboard utama, permintaan, antrean, vendor/dokumen, dan pengaturan menghasilkan HTTP 200 melalui kernel aplikasi dalam transaksi read-only |
| Browser pengguna | Dashboard server akun khafafi dimuat ulang; grup SCM muncul di antara Infrastruktur IT dan Meeting Room. Tautan Pengadaan PR/SR diklik dari sidebar dan halaman terbuka |
| Bagian modul | Permintaan saya, Vendor & dokumen dan Pengaturan dibuka di browser server. Daftar awal kosong dan jenis dokumen tersedia |
| Stylesheet | CSS SCM hasil build menghasilkan HTTP 200; computed style workspace adalah grid |
| Batas unggah | Form vendor menampilkan `Batas 2 MB per file.` |
| Konsol browser | Tidak ada error/warning yang tertangkap pada pemeriksaan akhir |
| Operasional | Login HTTP 200, worker aktif, dan working tree aplikasi bersih setelah penerapan modul |

Tes sebelum penerapan meliputi 532 tes lokal dengan 5.481 assertion; 27 tes SCM/katalog/izin tambahan dengan 339 assertion; runner PostgreSQL terpisah dengan 9 assertion; build, Pint, whitespace dan CI Linux berhasil. Tidak ada pengujian reset database pada server operasional.

## Mulai trial

Buka **SCM > Pengadaan PR/SR** pada aplikasi server. Akun khafafi dapat memakai tab **Pengaturan** untuk mengisi kategori pengadaan dan persyaratan dokumen sesuai kebutuhan organisasi, lalu membuat PR/SR. Kategori, vendor dan transaksi produksi tidak diisi otomatis.

Pemeriksaan ini memastikan pemasangan dan akses menu. Batas pengujian UI lanjutan, termasuk unggah melalui alat browser, simpan PDF dan zoom 200%, tetap dicatat di [status verifikasi](SCM-VERIFIKASI.md); tidak dinyatakan lulus tanpa bukti.

Bukti lokal: `output/scm-server-20261022/`, termasuk screenshot akun server, snapshot halaman, konsol, dan hasil verifikasi database. File bukti dan backup rahasia tidak dimasukkan ke Git.

## Pembaruan menu PR dan SR

Setelah arahan lanjutan pengguna, menu user dipisah menjadi PR dan SR. Menu SCM khusus menerima permintaan, mencari vendor, CBE dan legalitas. Lokasi dihapus dari form; kategori dipilih SCM saat menerima. Pembaruan sudah diterapkan ke server dengan backup dan pemeriksaan bahwa isi 91 tabel, termasuk hak akses serta data SCM, tetap identik. Form PR/SR dan antrean SCM diperiksa pada akun khafafi tanpa menyimpan transaksi produksi. Commit, backup, CI dan rincian verifikasi ada di [catatan pemisahan menu](SCM-MENU-PR-SR.md).
