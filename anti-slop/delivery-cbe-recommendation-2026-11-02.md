# Delivery: rekomendasi harga CBE

Mode During melanjutkan pilihan pengguna. Design Read: panel bantuan keputusan pada editor CBE operasional, mengikuti lembar Excel dan komponen SCM yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Rekomendasi ditempatkan di luar lembar dokumen agar pengguna membedakannya dari vendor yang benar-benar dipilih. Tombol opsional membantu mengisi pilihan; kolom vendor dan alasan tetap menjadi keputusan pengguna. Palet, tipografi, jarak, dan tema mengikuti aplikasi.

## Bukti

- PASS: 75 tes perhitungan rekomendasi dan fitur pengadaan, 923 assertion. Meliputi PPN, Non PKP, diskon, biaya tambahan, seri harga, mata uang, kelengkapan cakupan/jumlah/satuan, vendor aktif, duplikasi perusahaan, hak akses, pilihan manual, finalisasi, dan snapshot.
- PASS: 2 tes PostgreSQL, 41 assertion. Database sementara dibersihkan dan jumlah record database aplikasi tetap sama.
- PASS: Chrome pada SQLite terpisah; `output/cbe-recommendation-20261102/browser-checks.json` merekam alur dan console tanpa error.
- PASS: rekomendasi vendor termurah muncul tanpa mengisi pilihan; tombol dapat diaktifkan dengan keyboard untuk memakai rekomendasi.
- PASS: diskon dan Non PKP memperbarui rekomendasi; pilihan manual tetap tersimpan. Harga seri menampilkan semua vendor terendah tanpa memilih salah satunya.
- PASS: kurang dari tiga vendor menampilkan petunjuk; rekomendasi dan tombol tidak ditampilkan sampai perbandingan memenuhi syarat.
- PASS: pengguna menyimpan dan memfinalisasi vendor yang lebih mahal; snapshot menyimpan rekomendasi harga dan pilihan pengguna secara terpisah.
- PASS: panel diperiksa pada 1440/768/390 px dan teks 200% tanpa overflow halaman. Screenshot `recommendation-*.png` dan `final-recommendation.png` diperiksa.
- PASS: Pint, build Vite, dan pemeriksaan whitespace. Tidak ada migrasi atau perubahan data pengadaan lama.

## Delivery Gate

- R-02 PASS: label menyebut rekomendasi harga, Grand Total, dan tindakan konkret; tanpa em dash baru.
- R-03 PASS: panel/tombol membungkus pada ponsel; tiga lebar dan pembesaran teks diperiksa.
- R-17 PASS: angka berasal dari perhitungan CBE, dengan mata uang dan basis biaya tertulis.
- R-18 PASS: tidak menambah testimonial atau profil orang rekaan.
- R-23 PASS: pengguna meminta rekomendasi dengan pilihan manual; tidak membuat aset atau navigasi baru.
- R-24 PASS: tab CBE, pemilih penawaran, tombol rekomendasi, simpan, dan finalisasi digunakan dalam browser.
- R-25 PASS: warna SCM dipertahankan; teks bantuan `#4b5563` di `#f9fafb` 7,23:1 dan teks utama `#111827` di `#f9fafb` 16,98:1.
- R-26 PASS: rekomendasi dapat dipakai, diubah, disimpan, dan difinalisasi; tidak ada kontrol tanpa perilaku.
- R-27 PASS: panel mencakup belum dipilih/belum sebanding, perhitungan tidak valid, hasil tunggal, hasil seri, dan snapshot final. Loading/disabled mengikuti editor yang sudah ada.
- R-28 PASS: tidak menambah FAQ.
- R-32 PASS: tombol dipakai melalui keyboard; fokus berpindah ke pilihan vendor dan kontrol native dipertahankan.
- R-33 PASS: perubahan ditulis pada PHP/Blade sumber, tanpa patch runtime.
- R-34 PASS: tema terang panel dan lembar cetak dipertahankan.
- R-35 PASS: aplikasi dibangun, alur klik dijalankan, dan screenshot diperiksa.
- R-36 PASS: panel menyatakan basis harga serta pemeriksaan yang masih dilakukan pengguna; tidak mengklaim penilaian kualitas otomatis.
- R-37 PASS: dials 1/1/1 mengikuti editor CBE dan referensi Excel pengguna.
- R-38 PASS: produk menghitung penawaran tersimpan; fixture browser berlabel DEMO dalam database terpisah.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambah gradient, glow, ikon, font, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: panel memakai komponen SCM lama tanpa glass, shadow, kartu dekoratif, animasi, atau ilustrasi.
- Liveliness PASS: nama vendor, Grand Total, dan tombol opsional menjadi fokus; struktur lembar dan aksen SCM dipertahankan.
- C-1 PASS: tambahan membantu membandingkan harga dan membedakan saran dari keputusan pengguna.
- C-2 PASS: tindakan rekomendasi dan keputusan manual mempunyai perilaku teruji.
- C-3 PASS: panel hanya memuat hasil perbandingan, basis biaya, dan langkah pemilihan.
- C-4 PASS: state, keyboard, responsive, dan teks 200% diperiksa pada jalur perubahan.
- C-5 PASS: hasil sesuai perhitungan yang disimpan; seri dan kondisi belum sebanding ditampilkan secara jujur.
- R-05/R-11/R-15/R-16 PASS: komposisi/radius mengikuti editor; label spesifik tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas/palet/tema lama dipertahankan; alasan perubahan tertulis pada Design Read.
- Human PASS: heading panel terhubung, pembaruan diumumkan secara polite, kontrol native dan fokus dipertahankan.
- Layoutmobile PASS: panel menggunakan wrap SCM; tidak menambah fixed navigation atau interaksi khusus hover.
- Copywriting PASS: teks membedakan rekomendasi harga, pilihan pengguna, alasan, dan snapshot final.
