# Perbaikan temuan audit — 20 September 2026

Enam temuan fungsional dan tiga temuan tampilan pada REPORT.md telah diperbaiki di kode lokal. Perubahan lokal yang sudah ada sebelum perbaikan dipertahankan.

| Temuan | Perbaikan |
| --- | --- |
| A01: Viewer attendance mendapat 403 pada detail | Halaman hasil menerima izin attendance.view, dengan pemeriksaan ulang pada request Livewire. Hak upload/manage yang sebelumnya bisa membaca hasil tetap berlaku. Breadcrumb mengarah ke Report Center bagi pembaca, dan kartu upload disembunyikan jika tidak diizinkan. Izin create/edit/delete tidak diperluas. |
| A02: ID karyawan dan durasi kosong / pencarian gagal | Kolom tabel dan pencarian memakai employee_code dan duration_text sesuai output processor. Pencarian detail memakai whereLike Laravel, yang menghasilkan ILIKE pada PostgreSQL dan juga dapat diuji pada SQLite. |
| A03: Department duplikat memicu exception | Validasi unique untuk code pada create/edit, dengan pengecualian record yang sedang diedit. Panjang code/name dibatasi sesuai kolom database. |
| A04: Akun user terhubung ke dua employee | Pilihan akun hanya menampilkan akun yang tersedia serta akun employee yang sedang diedit. Validasi unique tetap memeriksa input saat penyimpanan, termasuk input yang dimanipulasi. |
| A05: Durasi negatif diterima | work_minutes wajib bilangan bulat 0–2147483647. distance_meters dibatasi 0–99999999.99. Berlaku pada create dan edit. |
| A06: ID periode tidak valid memicu exception | Input angka bebas diganti pilihan periode yang dapat dicari, disertai validasi keberadaan periode. Relasi tetap boleh kosong sesuai skema sebelumnya. |
| U01: Department hanya tampil sebagai angka | Tabel Employees menampilkan nama department, mendukung pencarian dan pengurutan. |
| U02: Tiga tautan sidebar tidak berfungsi | Tautan dan label diarahkan ke Service Desk, Laporan Aktivitas, dan Profile melalui named routes yang tersedia. |
| U03: Karakter dashboard rusak | Karakter sapaan diganti dengan HTML entity Unicode yang benar. |

## Verifikasi

- **213/213 tes lulus, 1.171 assertion**: 145 tes bawaan, 56 probe audit, dan 12 tes regresi baru.
- Tes regresi tersimpan di `tests/Feature/AuditRegressionTest.php` sehingga ikut suite aplikasi. Meliputi batas izin viewer dan pencabutan izin pada request lanjutan, kompatibilitas uploader, validasi create/edit, akun employee yang sedang dipakai, input attendance valid/tidak valid, serta pencarian ID/durasi/department.
- `php vendor/bin/pint --test`: lulus. Dua probe audit dirapikan formatnya tanpa mengubah skenario.
- `npm run build`: berhasil; ada peringatan waktu eksekusi plugin Vite, tanpa kegagalan build.
- `git diff --check`: lulus.

Perintah verifikasi gabungan:

```powershell
php vendor/phpunit/phpunit/phpunit tests output/audit-2026-09-18 --log-junit output/audit-2026-09-18/final-fixed.xml
php vendor/bin/pint --test
npm run build
```

Bukti akhir: `final-fixed.txt`, `final-fixed.xml`, `fixed-style.txt`, dan `fixed-build.txt`. Bukti kegagalan audit awal tetap tersimpan pada berkas laporan sebelumnya.

Tes transaksi menggunakan SQLite `:memory:`, bukan database bisnis PostgreSQL. Tidak ada migration atau perubahan data bisnis yang dijalankan. Pemeriksaan tampilan pada tahap perbaikan dilakukan melalui render aplikasi dan inspeksi Blade, belum melalui pengujian visual ulang di browser. Catatan duplikasi data KBLI (O01) tetap menjadi observasi data dan tidak digabung/dihapus otomatis.
