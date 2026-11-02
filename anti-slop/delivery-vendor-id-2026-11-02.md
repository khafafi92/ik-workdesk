# Delivery: Vendor ID pada Data Vendor

Mode During mengikuti pilihan pengguna. Design Read: daftar operasional vendor untuk superuser dan SCM, memakai bahasa visual SCM yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Fokus perubahan adalah identitas yang dipakai membuka formulir. Nomor pendaftaran dan kode master diberi label terpisah; Vendor ID ditempatkan sebelum tindakan membuka formulir. Textarea readonly membuat seluruh ID terbaca pada ponsel dan dapat dipilih sekaligus. Palet, tipografi, whitespace, dan komponen mengikuti aplikasi, tanpa aset atau navigasi baru.

## Bukti

- PASS: 48 tes fitur direktori/portal vendor, 535 assertion. Meliputi penyimpanan terenkripsi, pengungkapan khusus superuser, pembukaan formulir dengan ID, penggantian ID lama, pelestarian jawaban/lampiran, audit pelaksana, versi usang, riwayat lama, akses dicabut, dan penolakan SCM biasa/nonaktif.
- PASS: browser Chrome dengan SQLite terpisah; `output/vendor-access-20261102/browser-checks.json` merekam alur, lebar, dan console tanpa error.
- PASS: membatalkan konfirmasi tidak menerbitkan ID; menyetujui konfirmasi menampilkan ID pengganti; klik memilih seluruh ID dan Tab berpindah ke Buka formulir.
- PASS: Buka formulir menuju pendaftaran yang sama; perubahan bagian Manajemen Mutu tersimpan melalui alur portal yang sudah ada.
- PASS: SCM biasa tidak melihat ID atau tombol penggantian; pencarian kosong dan Hapus semua filter bekerja.
- PASS: screenshot pada lebar 1440/768/390 px diperiksa; teks 200% tanpa overflow halaman. ID panjang tetap terbaca.
- PASS: Vite build, Pint, pemeriksaan whitespace, dan kontras teks bantuan/batas kontrol.

## Delivery Gate

- R-02 PASS: label dan keterangan menyebut Vendor ID, nomor pendaftaran, kode master, serta akibat penggantian; tanpa em dash baru.
- R-03 PASS: textarea dan tindakan memakai reflow SCM; tiga lebar dan pembesaran teks diperiksa.
- R-17 PASS: jumlah pendaftaran berasal dari query aplikasi, tanpa statistik tambahan.
- R-18 PASS: tidak menambah testimonial atau identitas orang rekaan.
- R-23 PASS: pengguna meminta Vendor ID untuk membuka/edit formulir; tidak membuat aset baru.
- R-24 PASS: Data Vendor, Buka formulir, dan navigasi bagian portal bekerja.
- R-25 PASS: warna lama dipertahankan; teks bantuan `#4b5563` di putih 7,56:1 dan batas `#6b7280` di putih 4,83:1.
- R-26 PASS: penggantian dengan batal/konfirmasi, pilih teks, buka, simpan, pencarian, dan hapus filter dijalankan melalui browser.
- R-27 PASS: pesan validasi penggantian, konfirmasi, loading/disabled, offline, daftar kosong, dan pemberitahuan sukses tersedia.
- R-28 PASS: tidak menambah FAQ.
- R-32 PASS: label textarea terhubung, readonly tetap bisa difokuskan/dipilih, Tab menuju aksi berikutnya, fokus terlihat.
- R-33 PASS: fitur ditulis pada PHP/Blade sumber; tidak ada patch UI runtime.
- R-34 PASS: tema terang panel dan portal dipertahankan.
- R-35 PASS: aplikasi dibangun, alur klik diuji, dan screenshot diperiksa.
- R-36 PASS: keterangan hash lama/enkripsi/penggantian sesuai perilaku yang diuji.
- R-37 PASS: arah visual mengikuti daftar SCM; dials 1/1/1.
- R-38 PASS: produk menampilkan data tersimpan; fixture browser berlabel DEMO di database terpisah.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambah gradient, glow, ikon, font, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambah glass, shadow, kartu dekoratif, animasi, atau ilustrasi.
- Liveliness PASS: Vendor ID dan Buka formulir menjadi fokus; motif daftar, tipografi, whitespace, serta aksen SCM dipertahankan.
- C-1 PASS: pemisahan label mencegah tertukarnya identitas pendaftaran, master, dan akses portal.
- C-2 PASS: seluruh kontrol tambahan mempunyai perilaku teruji.
- C-3 PASS: informasi tambahan mendukung pencarian dan pembukaan formulir.
- C-4 PASS: state, keyboard, responsive, dan teks 200% diperiksa pada jalur perubahan.
- C-5 PASS: hanya menampilkan ID tersimpan; ID lama yang tidak dapat dibaca dijelaskan secara tepat.
- R-05/R-11/R-15/R-16 PASS: daftar, radius, dan tindakan konkret mengikuti SCM, tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas/palet/tema aplikasi dipertahankan; alasan kontrol tertulis pada Design Read.
- Human PASS: label, fokus, kontras, readonly, dan target kontrol mengikuti komponen teruji.
- Layoutmobile PASS: ID panjang membungkus dalam textarea; tidak menambah hover atau navigasi fixed.
- Copywriting PASS: keterangan membedakan ID portal dari kode master dan menyatakan akibat penggantian dengan jelas.
