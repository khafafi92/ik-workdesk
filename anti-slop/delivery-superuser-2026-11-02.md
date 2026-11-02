# Delivery: kewenangan superuser

Mode During melanjutkan pilihan pengguna. Design Read: gunakan lembar PR/SR, menu approval, dan formulir pengguna yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Pemilih Requestor dan departemen membantu superuser membuat permintaan lintas pengguna. Nama pelaksana pada tombol dan keterangan penugasan menjelaskan tanda tangan yang akan digunakan. Tidak ada arah visual, aset, atau navigasi baru.

Laporan ini menggantikan batas kewenangan pada delivery PR atas nama sebelumnya. Superuser dapat menjalankan penugasan pengguna lain dengan identitasnya sendiri; akun operasional tetap mengikuti izin dan penugasannya.

## Bukti

- PASS: seluruh suite aplikasi, 812 tes dan 8.418 assertion. Mencakup superuser melalui flag/role, pengelolaan user, PR/SR/approval/CBE, penolakan akun biasa/nonaktif, versi/urutan proses, dan aturan dokumen final/aset.
- PASS: uji browser Chrome pada database SQLite terpisah, tercatat di `output/superuser-20261102/browser-checks.json`.
- PASS: superuser memilih pemohon dan departemen lain, menyimpan, menandatangani, serta mengajukan PR Project menggunakan profilnya sendiri dan rute pemohon terpilih.
- PASS: superuser membuka approval pengguna lain, melihat penugasan asli, dan menandatangani sebagai dirinya sendiri. Gambar tanda tangan berhasil dimuat.
- PASS: pemilih Requestor SR Project bekerja; administrator biasa tetap tidak mendapat pemilih atau PR pemohon lain.
- PASS: superuser menandatangani tahap CBE milik pengguna lain dan proses berlanjut ke penugasan berikutnya, dibuktikan oleh `output/superuser-20261102/cbe-browser-checks.json` dan `cbe-superuser.png`.
- PASS: pemeriksaan lebar 1440/768/390 px dan teks 200% tanpa overflow halaman. Screenshot `requestor-*.png` dan `approval-superuser.png` diperiksa.
- PASS: 2 tes konkurensi PostgreSQL, 41 assertion. Database uji dibersihkan; jumlah record database aplikasi tetap sama.
- PASS: build Vite, Pint, dan pemeriksaan whitespace Git.

## Delivery Gate

- R-02 PASS: label tindakan menyebut akun penandatangan dan memakai bahasa operasional; tanpa em dash baru.
- R-03 PASS: kontrol memakai reflow form lama; tiga lebar browser dan pembesaran teks diperiksa.
- R-17 PASS: tidak menambah statistik produk.
- R-18 PASS: tidak menambah testimonial atau profil rekaan.
- R-23 PASS: kontrol mengikuti permintaan pengguna; tidak membuat aset baru.
- R-24 PASS: halaman PR, SR, dan Persetujuan dibuka dalam pengujian browser.
- R-25 PASS: warna SCM dipertahankan; teks bantuan `#4b5563` di `#f3f4f6` 6,87:1, teks hitam di putih 21:1, fokus `#b33800` di putih 6,03:1, batas `#6b7280` di putih 4,83:1.
- R-26 PASS: pemilih, simpan, Requested by, ajukan, dan approval dijalankan melalui browser.
- R-27 PASS: status memuat, ringkasan validasi, pratinjau master yang belum lengkap, serta keadaan daftar kosong memakai komponen yang sudah ada.
- R-28 PASS: tidak menambah FAQ.
- R-32 PASS: kontrol native dengan label terhubung; Tab dari Requestor menuju Department/Div; fokus terlihat pada screenshot.
- R-33 PASS: fitur ditulis pada sumber PHP/Blade, tanpa patch runtime.
- R-34 PASS: mode terang yang ditetapkan panel dipertahankan.
- R-35 PASS: aplikasi dibangun dan alur perubahan dijalankan melalui browser; screenshot diperiksa.
- R-36 PASS: kewenangan dan identitas tanda tangan sesuai service yang diuji, tanpa klaim pemasaran.
- R-37 PASS: arah visual dan dials mengikuti form operasional yang diminta pengguna.
- R-38 PASS: pengguna produk berasal dari akun tersimpan; data browser berlabel DEMO dalam database terpisah.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambah gradient, glow, ikon, font, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambah glassmorphism, shadow, kartu, animasi, atau ilustrasi.
- Liveliness PASS: form dan langkah approval menjadi fokus; grid dokumen, whitespace, tipografi, serta aksen SCM dipertahankan sesuai dials 1/1/1.
- C-1 PASS: tambahan menjelaskan kewenangan, pemohon, dan pelaksana tindakan.
- C-2 PASS: kontrol baru dan kontrol yang diperluas mempunyai perilaku teruji.
- C-3 PASS: tidak ada bagian pengisi di luar kebutuhan proses.
- C-4 PASS: state, keyboard, responsive, dan pembesaran teks diperiksa pada jalur perubahan.
- C-5 PASS: identitas pelaksana dan penugasan asli cocok dengan hasil simpan dan audit.
- R-05/R-11/R-15/R-16 PASS: susunan, radius, dan label aksi konkret mengikuti aplikasi, tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas lembar KPMOG/APCA dan panel dipertahankan; tidak menambah tema atau palet.
- Human PASS: label native, fokus, kontras, target minimum dari komponen SCM, serta identitas penandatangan dalam teks dipertahankan.
- Layoutmobile PASS: reflow form dan target kontrol lama dipakai; tidak menambah navigasi fixed atau interaksi khusus hover.
- Copywriting PASS: keterangan menyebut pemohon, penugasan asli, dan pelaksana berdasarkan perilaku yang diuji.
