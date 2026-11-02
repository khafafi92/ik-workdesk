# Delivery: status dan langkah berikutnya vendor

Mode During melanjutkan pilihan pengguna. Design Read: daftar vendor operasional dan Form 005, mengikuti bahasa visual SCM/portal yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Setiap pendaftaran menjelaskan tindakan berikutnya berdasarkan status dan masa akses. Tindakan utama menyebut prosesnya; tautan ke Bagian 5 membantu pengguna menemukan pemeriksaan kelengkapan dan pengiriman. Palet, tipografi, whitespace, dan susunan formulir dipertahankan.

## Bukti

- PASS: 49 tes direktori/portal vendor dengan 546 assertion; validasi, akses, ID vendor, kelengkapan, status, dan pengembalian ke direktori tetap teruji.
- PASS: Chrome pada SQLite terpisah; `output/vendor-next-step-20261102/browser-checks.json` merekam alur serta console tanpa error.
- PASS: draft tidak lengkap diarahkan ke Bagian 5; pengiriman menampilkan kekurangan dan status tetap draft.
- PASS: draft lengkap dikirim dari Bagian 5, menjadi Terkirim, dibuka melalui Periksa kiriman, lalu diterima SCM dan menampilkan panduan pembaruan.
- PASS: Minta perbaikan mengubah status dan menampilkan catatan SCM serta petunjuk pengiriman ulang.
- PASS: pemeriksaan mempertahankan tautan Kembali ke Data Vendor setelah keputusan; validasi versi usang juga mempertahankan tujuan kembali.
- PASS: lebar 1440/768/390 px dan teks 200% tanpa overflow halaman; screenshot `next-step-*.png` diperiksa.
- PASS: Pint, Vite build, dan pemeriksaan whitespace Git. Tidak ada migrasi atau perubahan service bisnis.

## Delivery Gate

- R-02 PASS: petunjuk dan label menyebut tindakan serta status konkret; tanpa em dash baru.
- R-03 PASS: petunjuk dan label panjang membungkus pada lebar ponsel; tiga lebar serta teks 200% diperiksa.
- R-17 PASS: jumlah bagian/unggahan berasal dari data; petunjuk menjelaskan batas maknanya.
- R-18 PASS: tidak menambah testimonial atau profil rekaan.
- R-23 PASS: pengguna meminta proses lanjutan diterapkan; tidak membuat aset atau navigasi baru.
- R-24 PASS: tautan Bagian 5, Periksa kiriman, dan kembali ke Data Vendor dijalankan melalui browser.
- R-25 PASS: warna lama dipertahankan; teks bantuan `#4b5563` di putih 7,56:1, aksen `#9a3412` di putih 7,31:1.
- R-26 PASS: buka formulir, tautan Bagian 5, kirim, terima, minta perbaikan, dan kembali ke daftar bekerja.
- R-27 PASS: validasi kekurangan, versi usang, loading/disabled, pesan sukses, dan petunjuk akses dicabut/kedaluwarsa dipertahankan.
- R-28 PASS: tidak menambah FAQ.
- R-32 PASS: tautan Bagian 5 diaktifkan dengan keyboard; kontrol native dan fokus lama dipertahankan.
- R-33 PASS: perubahan ditulis pada PHP/Blade sumber tanpa patch runtime.
- R-34 PASS: tema terang panel dan portal dipertahankan.
- R-35 PASS: aplikasi dibangun, alur kirim/periksa/perbaiki dijalankan, dan screenshot diperiksa.
- R-36 PASS: status dan panduan sesuai hasil transaksi yang diuji.
- R-37 PASS: dials 1/1/1 mengikuti aplikasi operasional yang sudah ada.
- R-38 PASS: produk memakai status tersimpan; data browser berlabel DEMO dalam database terpisah.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambah gradient, glow, ikon, font, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambah glass, shadow, kartu dekoratif, animasi, atau ilustrasi.
- Liveliness PASS: Langkah berikutnya menjadi fokus tindakan; susunan daftar, whitespace, dan aksen SCM menghubungkan status dengan prosesnya.
- C-1 PASS: setiap petunjuk menjelaskan tindakan berdasarkan keadaan pendaftaran.
- C-2 PASS: kontrol baru dan label tindakan mempunyai tujuan yang teruji.
- C-3 PASS: penambahan langsung menjawab kebingungan proses pengguna.
- C-4 PASS: state, keyboard, responsive, dan pembesaran teks diperiksa pada jalur perubahan.
- C-5 PASS: jumlah tersimpan tidak diklaim sebagai jaminan kelengkapan.
- R-05/R-11/R-15/R-16 PASS: susunan/radius mengikuti aplikasi; label tindakan konkret tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas/palet/tema lama dipertahankan; alasan perubahan tertulis pada Design Read.
- Human PASS: heading Langkah berikutnya terhubung ke bagian, kontras terukur, dan tautan/label dapat digunakan melalui keyboard.
- Layoutmobile PASS: teks dan tombol panjang membungkus; tidak menambah fixed navigation atau interaksi khusus hover.
- Copywriting PASS: membedakan simpan, kirim, pemeriksaan, perbaikan, dan penerimaan sesuai perilaku aplikasi.
