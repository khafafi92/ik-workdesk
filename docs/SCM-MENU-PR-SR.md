# Pemisahan menu PR, SR, dan SCM

Pembaruan berikutnya mengubah penomoran, tampilan status sebelum penerimaan, dan menghapus pilihan kategori saat menerima. Perilaku terbaru ada pada [nomor dan penerimaan PR/SR](SCM-NOMOR-DAN-PENERIMAAN.md); catatan di bawah mempertahankan hasil rilis pemisahan menu sebelumnya.

22 Oktober 2026. Perubahan mengikuti arahan pengguna: PR dan SR adalah menu user; tim SCM menerima permintaan sebelum mencari vendor dan menyusun CBE.

| Menu | URL | Penggunaan |
| --- | --- | --- |
| PR | `/panel/permintaan/pr` | Membuat dan memantau PR sendiri |
| SR | `/panel/permintaan/sr` | Membuat dan memantau SR sendiri |
| SCM | `/panel/scm/pengadaan` | Antrean permintaan terkirim, vendor, CBE, legalitas, pengaturan |

Menu PR dan SR memakai izin `scm.request` yang sudah ada. Pemrosesan tetap memakai `scm.process` dan cakupan departemen; pengaturan memakai `scm.configure`. Checklist hak akses menyediakan satu pilihan PR dan SR karena izin pemohon tetap sama. Tidak ada pemberian izin baru atau bypass administrator.

Form user tidak meminta lokasi atau kategori. Jenis mengikuti menu PR atau SR dan divalidasi kembali di server. Kategori dipilih SCM saat menerima permintaan, harus aktif, dicatat dalam audit, dan tetap menentukan snapshot persyaratan legalitas. Data kategori/lokasi lama dipertahankan ketika draft diedit. Migrasi hanya mengizinkan `scm_requests.category_id` kosong sebelum penerimaan, tanpa menghapus foreign key atau record.

Form Excel pengguna dibaca sebagai referensi, tanpa diubah. Identitas/tanggal/departemen dan rincian item sesuai kelompok data form. Kolom Delivery Point pada referensi menjelaskan fungsi lokasi yang kemudian dihapus atas permintaan pengguna. Format nomor, rantai tanda tangan, atau kolom tambahan tidak diubah oleh penyesuaian menu ini.

## Verifikasi lokal

- 33 tes SCM, katalog menu, dan hak akses lulus dengan 407 assertion. Termasuk kedua form tanpa kategori/lokasi, pemisahan jenis/pemilik, penolakan pemalsuan jenis, penerimaan kategori aktif, audit, pelestarian data historis, dan seeder demo idempotent.
- Runner PostgreSQL terpisah lulus: satu tes, 11 assertion. Migrasi nullable dan penerimaan kategori digunakan bersama pengujian nomor unik/finalisasi CBE bersamaan; jumlah record database utama tidak berubah.
- PR dan SR fiktif masing-masing dibuat, disimpan, dikirim, dan diterima SCM melalui browser Chrome lokal. Setelah penerimaan muncul langkah tiga kandidat vendor. Data ini hanya di lokal.
- Form SR diperiksa pada 320 dan 768 px. Lebar scroll dokumen tidak melebihi viewport; kontrol yang diukur memiliki tinggi minimal 44 px. Override viewport dikembalikan setelah pengujian.
- Penyimpanan draft serta penerimaan SCM diuji dengan Enter. Konsol browser pada pemeriksaan akhir tidak memuat error/warning.

Bukti berada di `output/scm-browser-20261022/`: `sr-menu-terpisah-320.jpg`, `penerimaan-kategori-menu-terpisah.jpg`, dan `sr-menu-terpisah-pencarian-vendor.jpg`.

## Gate UI untuk perubahan ini

Arah tetap mengikuti IK WorkDesk: ENERGY 1 / RHYTHM 1 / MOTION 1. Tidak ada perubahan warna, tipografi, aset, animasi, atau tema. Bukti kontras dan pengujian komponen yang tidak berubah menggunakan verifikasi SCM sebelumnya.

| Pemeriksaan | Status dan bukti |
| --- | --- |
| R-02, R-16, R-36 | PASS: label PR, SR, SCM dan petunjuk kategori menyatakan fungsi nyata; tidak menambah klaim atau tanda pisah dekoratif |
| R-03 | PASS: form yang berubah diuji pada 320/768 px; tidak ada overflow dokumen, kontrol minimal 44 px |
| R-17, R-18, R-38 | PASS: ringkasan berasal dari query sesuai akses; transaksi browser diberi penanda DEMO dan hanya disimpan di lokal |
| R-23, R-24 | PASS: pemisahan navigasi diminta pengguna; ketiga tautan dibuka dan seluruh tujuan terdaftar dalam katalog hak akses |
| R-25, R-29 | PASS: tidak mengubah token warna; delapan pasangan warna komponen sebelumnya memiliki rasio 4,83:1 sampai 17,74:1 |
| R-26, R-27, R-35 | PASS: kedua form menyimpan, mengirim dan diterima SCM; kategori kosong/inaktif ditolak tes, status loading/error/empty dipakai ulang |
| R-32 | PASS: simpan draft dan penerimaan dijalankan dengan Enter; fokus terlihat pada form sempit; memakai kontrol native dengan label |
| R-33, R-34 | PASS: perubahan ditulis di sumber Blade/PHP; panel tetap memakai tema terang yang sudah ditetapkan |
| Purpose gate | PASS: tidak menambah efek/dekorasi; kelompok Permintaan memisahkan pekerjaan user dari pengolahan SCM |
| R-37 dan liveliness | PASS: dials 1/1/1 dipertahankan; judul PR/SR dan tombol buat menjadi fokus, struktur form mengikuti identitas dan item |
| C-1 sampai C-5, quality locks | PASS: memakai shell/komponen yang sama; otorisasi, data historis, format angka, dan proses CBE/legalitas tercakup regresi |

Gate ini mencakup perubahan menu/form. Batas QA modul yang sudah dicatat, seperti unggah file melalui ekstensi, simpan PDF dan zoom 200%, tetap tercantum pada [status verifikasi SCM](SCM-VERIFIKASI.md).

## Penerapan server

Commit aplikasi `283f9f256a6748efab31a106b8564a60cfa6a206` diterapkan ke `/var/www/ik-workdesk` di `10.1.20.97:8093`. [CI untuk commit aplikasi](https://github.com/khafafi92/ik-workdesk/actions/runs/39744104449) lulus, termasuk build, migrasi, Pint dan seluruh tes aplikasi.

Backup kode, environment dan dump PostgreSQL privat: `/home/r92/ik-workdesk-releases/20261022-145957-283f9f256a67-scm-menus/backup`. Daftar isi dump diperiksa dengan `pg_restore --list`. Jumlah record dan fingerprint seluruh isi 91 tabel identik sebelum/sesudah penerapan, termasuk tabel SCM, user, role dan permission. Pengecualian hanya migration, cache, session dan antrean. Kategori menjadi nullable; foreign key kategori tetap tersedia.

Pemeriksaan halaman pertama dijalankan saat maintenance masih aktif sehingga menghasilkan 503. Setelah aplikasi aktif, keenam pemeriksaan diulang dalam transaksi read-only: dashboard, PR, SR, antrean SCM, vendor dan pengaturan semuanya HTTP 200. Script pemeriksaan diperbaiki agar exception mengembalikan exit code gagal secara eksplisit, dan pengecekan halaman dilakukan setelah maintenance berakhir. Worker antrean aktif setelah penerapan.

Browser Chrome dengan akun server khafafi memperlihatkan PR/SR di kelompok Permintaan dan SCM secara terpisah. Form PR serta SR dibuka tanpa kategori/lokasi, dan antrean SCM dibuka melalui sidebar. Tidak ada transaksi uji yang disimpan di produksi. Bukti: `output/scm-server-20261022/pr-menu-terpisah-server.jpg`. Form PR server disediakan untuk trial pengguna.
