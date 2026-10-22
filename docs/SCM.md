# Modul SCM: PR/SR, CBE, dan legalitas vendor

Modul terintegrasi dengan pengguna, departemen, Filament, dan hak akses IK WorkDesk. Sidebar menyediakan **Permintaan > PR** dan **Permintaan > SR** untuk permintaan user, serta **SCM > SCM** untuk penerimaan dan pemrosesan oleh tim SCM. Modul tidak menambahkan PO, kontrak, penerimaan barang, invoice, pembayaran, portal vendor, atau pesan otomatis.

## Menjalankan lokal di Windows PowerShell

Gunakan database lokal yang sudah dikonfigurasi. Jangan menjalankan reset atau `migrate:fresh` pada database aplikasi.

```powershell
php artisan migrate --path=database/migrations/2026_10_22_100000_create_scm_procurement_tables.php
php artisan migrate --path=database/migrations/2026_10_22_150000_move_scm_category_to_receipt.php
php artisan migrate --path=database/migrations/2026_10_22_160000_create_scm_request_numbering.php
php artisan db:seed --class=ScmSeeder
npm run build
php artisan config:cache
php artisan route:cache
php artisan filament:optimize-clear
php artisan serve --host=127.0.0.1 --port=8000
```

Migrasi awal menambah tabel berawalan `scm_`. Migrasi berikutnya mengizinkan kategori permintaan kosong sampai diterima SCM, dengan foreign key tetap berlaku. Tidak ada record yang dihapus atau kategori lama yang dikosongkan. Seeder awal menambah permission dan jenis NPWP/NIB/SBU secara idempotent, tanpa menganggap semuanya wajib.

Untuk data demo fiktif, hanya pada lingkungan `local` atau `testing`:

```powershell
php artisan db:seed --class=ScmDemoSeeder
```

Akun pengembangan: `scm.pemohon@example.test` dan `scm.petugas@example.test`, kata sandi awal `SCM-Demo-2026!`. Seeder hanya membuat akun bila belum ada dan tidak mengganti kata sandi akun yang ada. Semua vendor, PR/SR, dan dokumen demo diberi penanda DEMO/fiktif. Jangan menjalankan seeder demo atau memakai akun ini pada produksi. Seeder demo menyiapkan satu PR dan satu SR sampai selesai, kategori demo dengan NIB wajib, tiga vendor, serta contoh pemilihan harga terendah dan vendor lain dengan alasan.

## Hak akses dan penggunaan

Administrator mengatur izin melalui checklist menu Users, grup Permintaan dan SCM:

- `scm.request`: membuat/edit draft sendiri, mengirim ke SCM, melihat permintaan sendiri serta lampiran permintaan. Tidak membuka penawaran, CBE, atau legalitas vendor.
- `scm.process`: menerima permintaan yang sudah dikirim dalam cakupan departemen yang diizinkan; mengelola vendor, penawaran, CBE dan legalitas. Draft pengguna lain tetap privat.
- `scm.configure`: kategori, jenis dokumen, dan default persyaratan. Tidak otomatis memperoleh hak transaksi SCM.

SCM menggunakan permission yang benar-benar diberikan melalui role aktif atau permission langsung aktif. Flag admin atau role system-admin saja tidak memberi akses transaksi SCM. Cakupan departemen tetap mengikuti mekanisme proyek. Vendor merupakan master bersama dalam instalasi proyek yang saat ini tidak memiliki tenant terpisah.

Pada 22 Oktober 2026, atas permintaan pengguna, akun utama Khafafi di lingkungan lokal diberi ketiga izin SCM secara eksplisit. Sidebar dashboard utama juga menyediakan tautan **SCM - Pengadaan PR/SR** sesuai hak akses. Aktivasi ini khusus lokal, bukan perubahan default izin administrator atau penerapan ke server. Bukti tersedia di [catatan aktivasi menu lokal](../output/scm-browser-20261022/AKTIVASI-MENU-LOKAL.md).

User membuat draft pada menu PR atau SR sesuai jenisnya, lalu mengirim ke SCM. Jenis mengikuti menu dan tidak dapat diubah dari form. Lokasi dan kategori tidak diminta pada form user. Lokasi historis tetap tersimpan dan dapat dilihat pada detail jika pernah diisi.

Nomor tampil otomatis saat Buat PR/SR diklik, mulai `001/APCA-HO/PR/X/2026` untuk PR dan `001/APCA-HO/SR/X/2026` untuk SR pada trial Oktober 2026. Urutan terpisah per jenis dan tahun, tetap naik saat bulan berubah. Nomor dipertahankan ketika draft disimpan, dikirim dan diterima. Nomor lama tidak diubah.

Alur yang ditampilkan: Draft, Diterima SCM, Pengumpulan penawaran, Penyusunan CBE, Vendor terpilih, Pengumpulan legalitas, Selesai. User dapat langsung Kirim ke SCM dari form atau Simpan draft terlebih dahulu untuk menambah lampiran. PR/SR yang sudah dikirim tetap tampil sebagai Draft dengan keterangan menunggu penerimaan; isinya terkunci dan masuk antrean SCM. Draft yang belum dikirim tetap privat. SCM cukup klik Terima oleh SCM, tanpa memilih kategori, lalu melanjutkan tiga vendor, quotation, CBE, dan legalitas.

Semua peralihan divalidasi server dengan transaksi, row lock, dan versi permintaan. Jika ada perubahan bersamaan, muat ulang data sebelum mengulang tindakan. Nomor PR/SR memakai counter terkunci dan unique constraint; nomor CBE tetap menggunakan ULID. CBE final tidak dapat dibuka ulang. Revisi setelah final merupakan kebutuhan lanjutan, bukan fitur versi ini.

## Angka, penawaran, dan snapshot

Input angka tanpa pemisah ribuan, desimal memakai koma atau titik. Jumlah mendukung tiga desimal. Uang disimpan sebagai integer minor units, dua desimal, dengan pembulatan setengah ke atas pada bruto baris. Input ekstrem yang melampaui batas integer ditolak. Komponen dihitung ulang server: jumlah × harga satuan, dikurangi diskon nominal, ditambah pajak nominal baris; biaya tambahan header ditambahkan satu kali. Tidak ada tarif pajak/kurs otomatis. Harga kosong bukan nol.

Finalisasi memerlukan tepat tiga vendor berbeda, mata uang yang sama, item lengkap, serta jumlah dan satuan setara dengan permintaan. SCM menentukan pemenang dan alasan; harga akhir berasal dari quotation yang dipilih. Revisi penawaran selalu membuat versi baru. CBE menyimpan snapshot identitas vendor, item, syarat, dan harga sehingga perubahan master berikutnya tidak mengubah CBE historis. Tampilan cetak menggunakan snapshot final dan dapat disimpan PDF melalui browser.

## Dokumen privat dan legalitas

Disk `scm` menyimpan file di `storage/app/scm-private`, tanpa URL publik. Unduhan melalui route terautentikasi dengan otorisasi di setiap permintaan. PDF/JPG/PNG divalidasi ukuran, ekstensi, MIME, dan signature, memakai nama UUID. Batas default 10 MB; dapat diatur dengan `SCM_UPLOAD_MAX_KB=10240` pada environment. Batas unggahan PHP/web server juga harus mencukupi.

Versi dokumen tidak ditimpa. Nomor dokumen berupa string agar nol awal tidak hilang. Versi existing dapat dipakai ulang pada checklist yang cocok vendor dan jenis dokumennya. Kategori dan aturan historis tetap tersimpan. Checklist permintaan lama tetap menyalin persyaratan kategorinya saat pengumpulan dimulai; pada permintaan baru tanpa kategori, SCM menambahkan persyaratan per proses di tab Legalitas dengan alasan dan audit. Transaksi selesai terkunci. Dokumen wajib tanpa file, berstatus belum diterima/perlu perbaikan, atau kedaluwarsa menghalangi penyelesaian. Kedaluwarsa setelah selesai tidak membuka ulang sejarah. Kelengkapan ini administratif internal, bukan verifikasi keaslian ke instansi.

## Pengujian

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/Scm
php tests/run-scm-postgres.php --no-progress
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test
npm run build
```

PHPUnit utama menggunakan SQLite `:memory:` dan cache pengujian terpisah. Runner PostgreSQL hanya menerima target lokal, membuat database acak `ik_scm_test_*` dengan kredensial admin lokal yang sudah tersedia, menguji dua proses bersamaan, memverifikasi jumlah record database aplikasi, lalu membersihkan database uji. Jangan menjalankan file pengujian PostgreSQL langsung terhadap database aplikasi.

Pada 22 Oktober 2026, modul diterapkan atas persetujuan pengguna ke project IK WorkDesk yang sama di `http://10.1.20.97:8093`, direktori `/var/www/ik-workdesk`. Akun Khafafi memiliki tiga izin eksplisit. Data demo lokal tidak dimasukkan. Batas unggah SCM server diatur `SCM_UPLOAD_MAX_KB=2048` mengikuti batas PHP 2 MB. Pembuatan dan penerimaan PR/SR tidak memerlukan kategori. Backup, verifikasi data lama, dan hasil penerapan tercatat pada [rilis SCM](SCM-PENERAPAN-2026-10-22.md).

Untuk penerapan berikutnya, cadangkan database dan dokumen terlebih dahulu, jalankan migrasi/seeder awal secara terkontrol, bangun aset, dan bangun ulang cache konfigurasi/route. Hasil pengujian aktual dan batas QA browser tercatat pada [SCM-VERIFIKASI.md](SCM-VERIFIKASI.md).
