# Memperbarui SCM melalui GitHub

Pembaruan ini mencakup Master Approval gabungan PR/SR dan CBE, tanda tangan CBE berurutan, serta Portal Vendor dengan Vendor ID. Komputer pengembangan lokal sudah diaktifkan; server harus diperbarui secara terpisah.

Cadangkan database, dokumen privat dan sumber aplikasi memakai prosedur backup server. Jalankan pada direktori IK WorkDesk yang sudah terhubung ke repository GitHub:

```bash
cd /var/www/ik-workdesk
git status --short
```

Jika ada perubahan pada file terpantau, periksa dan selesaikan perubahan server tersebut sebelum menarik pembaruan. Pertahankan `.env`, storage dokumen dan konfigurasi server yang digunakan instalasi tersebut.

Jalankan satu per satu. Periksa hasil setiap perintah sebelum melanjutkan:

```bash
php artisan down
git pull --ff-only origin main
composer dump-autoload --no-dev --optimize
php artisan migrate --path=database/migrations/2026_10_28_100000_create_scm_cbe_approvals.php --force
php artisan migrate --path=database/migrations/2026_10_29_100000_add_public_vendor_portal.php --force
php artisan filament:clear-cached-components
php artisan optimize:clear
php artisan optimize
php artisan up
```

Migrasi yang sudah tercatat akan dilewati. Migrasi memerlukan role database dengan hak CREATE/ALTER pada schema aplikasi. Jika role runtime dibatasi, gunakan prosedur akun migrasi/owner yang sudah tersedia. Setelah migrasi, role runtime memerlukan SELECT/INSERT/UPDATE/DELETE pada `scm_cbe_approval_policies`, `scm_cbe_approvals`, dan `scm_vendor_portal_accesses`, serta USAGE/SELECT pada sequence ID ketiganya. Kredensial migrasi tetap dikelola di server.

Pembaruan memakai aset SCM dan portal yang sudah tersedia; tidak mengubah dependency Composer atau CSS. Jangan menjalankan seeder demo pada database operasional. Pilih penandatangan CBE melalui **SCM > Pengaturan > Master Approval > Dokumen persetujuan: CBE**. Setiap akun menyimpan gambar TTD sendiri pada profilnya.

Sesudah layanan dibuka, periksa login, Master Approval dengan pilihan PR/SR/CBE, menu Persetujuan, dan Portal Vendor `/vendor`. Pada instalasi yang tercatat, portal server berada di `http://10.1.20.97:8093/vendor`. Vendor baru memperoleh Vendor ID; vendor memakai ID yang sama untuk membuka data dan mengajukan pembaruan. Profil dan berkas masuk master setelah SCM menerima kiriman.

Panduan pengguna lengkap tersedia di [SCM](SCM.md) dan [Buku IK WorkDesk](../output/pdf/Buku%20Pengembangan%20IK%20WorkDesk.pdf).
