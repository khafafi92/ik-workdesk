# Delivery: Nomor PR/SR, 2 November 2026

Mode **During**, dipilih pengguna. Cakupan: satu menu riwayat dan master nomor Project, pemilih master sebelum nomor terbit, serta penyesuaian tampilan tabel menu baru pada ponsel. Tampilan formulir dan persetujuan mengikuti aplikasi yang sudah ada.

Design Read: gunakan identitas SCM IK WorkDesk yang sudah ada: tabel operasional, label Indonesia, warna dan fokus dari `scm.css`, serta komponen Filament/SCM. ENERGY 1 / RHYTHM 1 / MOTION 1. Fokus halaman adalah nomor dan keterangan; jarak memisahkan riwayat dari konfigurasi. Label per baris pada ponsel menjaga keterbacaan nomor lengkap. Tidak ada arah visual baru atau aset baru.

## Bukti eksekusi

- PASS: `php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/Scm tests/Feature/MenuAccessCatalogTest.php`: 242 tes, 2.813 assertion.
- PASS: dua tes PostgreSQL untuk penerbitan bersamaan pada Project dan HO/CBE: 2 tes, 41 assertion. Database sementara dibersihkan; jumlah record database utama tetap sama.
- PASS: `npm run build`, Pint pada seluruh berkas PHP yang berubah, dan `git diff --check`.
- PASS: browser Chrome pada database SQLite terpisah; bukti `output/request-numbering-20261102/browser-checks.json` dan screenshot `history-*.png`.
- PASS: teks 200% pada lebar 390 px tanpa overflow halaman maupun tabel; bukti `zoom-check.json` dan `history-mobile-200percent.png`.
- PASS: aktivasi PostgreSQL lokal; fingerprint atribut data lama dan nomor terbit tetap sama, tercatat dalam `local-activation.json`.

## Klik dan interaksi

- PASS: menu Nomor PR/SR membuka halaman riwayat dan master sesuai izin.
- PASS: halaman kedua riwayat menampilkan nomor lama; Hapus semua filter mengembalikan halaman pertama.
- PASS: pencarian judul menghasilkan baris yang sesuai; HO/Project, PR/SR, perusahaan, dan pemakaian nomor mengubah hasil serta menampilkan pesan kosong saat tidak ada hasil.
- PASS: Simpan master membuat kode baru; Edit master membuka data yang benar; checkbox aktif menonaktifkan penerbitan nomor baru.
- PASS: mengganti kode master yang sudah dipakai menampilkan validasi; Kosongkan form menghapus isian dan kesalahan.
- PASS: filter, Edit master, dan Kosongkan form juga dijalankan pada lebar 768 dan 390 px.
- PASS: Buat PR/SR, pilihan Project, pilihan master, dan perusahaan menerbitkan nomor sesuai jenis; tombol perusahaan belum aktif sebelum master dipilih.
- PASS: Simpan draft dan Edit draft mempertahankan nomor Project yang sama pada PR dan SR; SR diuji pada lebar 390 px.
- PASS: Kembali ke daftar membuka daftar permintaan. Pemohon lain tidak melihat riwayat pengguna pertama atau form konfigurasi.
- PASS: Tab dari kolom pencarian memindahkan fokus ke pemilih kebutuhan. Kontrol baru berupa input, select, checkbox, dan button native, dengan fokus terlihat dari CSS SCM.
- PASS: tidak ada kesalahan JavaScript selama pengujian browser.

## Hard Gate

- R-02 PASS: label dan petunjuk menggunakan bahasa operasional Indonesia; teks baru tidak memakai em dash.
- R-03 PASS: lebar 1440, 768, dan 390 px tidak menyebabkan overflow halaman; tabel beralih ke daftar berlabel pada ponsel.
- R-17 PASS: tidak ada statistik produk yang ditambahkan; angka verifikasi berasal dari hasil tes.
- R-18 PASS: tidak ada testimonial atau profil rekaan dalam produk.
- R-23 PASS: menu baru diminta pengguna; tidak membuat logo, avatar, atau ilustrasi.
- R-24 PASS: route menu ada dan berhasil dibuka di browser.
- R-25 PASS: pemeriksa kontras menghasilkan teks utama 17,74:1, teks bantuan 7,56:1, garis input 4,83:1, serta fokus 7,31:1 terhadap latar putih.
- R-26 PASS: pencarian, semua filter, pagination, aksi master, serta pembuatan dan penyimpanan permintaan dijalankan di browser.
- R-27 PASS: pesan kosong, teks loading berstatus, serta ringkasan error dengan role alert tersedia; validasi kode dipicu di browser.
- R-28 PASS: tidak menambahkan FAQ.
- R-32 PASS: kontrol native mengikuti urutan DOM, fokus 3 px terlihat, dan tidak ada interaksi khusus mouse.
- R-33 PASS: Blade dan CSS ditulis di sumber aplikasi; tidak ada script runtime yang menambal desain.
- R-34 PASS: panel tetap memakai mode terang yang ditetapkan provider. Warna turunan `.dark` pada workspace juga diperiksa tanpa menambah theme switcher.
- R-35 PASS: build dan daftar klik di atas selesai; console browser tanpa error.
- R-36 PASS: tidak menambahkan klaim keamanan, performa, atau kepatuhan.
- R-37 PASS: arah mengikuti UI SCM yang sudah ada dan batas perubahan presentasi dalam instruksi proyek; dials dicatat di atas.
- R-38 PASS: kode LTRO berasal dari pengguna; data pengujian ditandai DEMO dan hanya berada pada database uji.

## Purpose-Gate

- R-01 PASS: tidak menambahkan gradient atau glow; warna mengikuti token SCM untuk teks, batas, dan fokus.
- R-04 PASS: tidak menambahkan ikon dekoratif.
- R-06 PASS: font mengikuti aplikasi; nomor ditampilkan sebagai data biasa.
- R-07 PASS: garis tabel memisahkan atribut dan nomor, tanpa pola latar dekoratif.
- R-08 PASS: tidak menambahkan panah dekoratif.
- R-09 PASS: status menggunakan teks, tanpa badge promosi.
- R-10 PASS: tidak menambahkan glassmorphism.
- R-12 PASS: tidak menambahkan bayangan baru.
- R-13 PASS: tidak menambahkan glow.
- R-14 PASS: struktur mengikuti riwayat dan master; tidak ada feature cards.
- R-19 PASS: tidak menambahkan animasi; loading memakai perilaku Livewire yang sudah ada.
- R-22 PASS: tidak menambahkan ilustrasi.

## Liveliness

- PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 dinyatakan dan sesuai halaman administrasi SCM.
- PASS: nomor dan keterangan menjadi fokus utama di atas konfigurasi.
- PASS: panel dan jarak memisahkan pencarian, daftar nomor, dan master yang membutuhkan izin khusus.
- PASS: identitas produk berasal dari kode perusahaan/Project, istilah PR/SR, dan struktur operasional yang nyata.
- PASS: tipografi dan satu aksen fokus mewarisi sistem aplikasi; tidak ada variasi dekoratif baru.

## Craftsmanship dan checklist tambahan

- C-1 PASS: setiap bagian melayani pencarian nomor atau konfigurasi kode Project.
- C-2 PASS: seluruh kontrol baru berperilaku sesuai label pada daftar klik.
- C-3 PASS: tidak menambahkan bagian pemasaran atau konten pengisi.
- C-4 PASS: state kosong/error/loading, keyboard, teks 200%, dan perubahan lebar telah diperiksa.
- C-5 PASS: tidak menambahkan bukti sosial atau klaim rekaan.
- R-05 PASS: urutan informasi mengikuti pemeriksaan nomor, kemudian konfigurasi; grid filter memiliki satu, dua, dan tiga kolom pada rentang lebar yang sesuai.
- R-11 PASS: radius mengikuti komponen SCM; tidak ada perubahan bentuk komponen lain.
- R-15 PASS: label aksi spesifik: Simpan master, Edit master, Kosongkan form, Hapus semua filter.
- R-16 PASS: petunjuk menjelaskan aturan nomor tanpa buzzword pemasaran.
- R-20 PASS: halaman memakai struktur dan data pengadaan IK WorkDesk yang nyata.
- R-21 PASS: pilihan mode terang aplikasi dipertahankan.
- R-29 PASS: warna mewarisi token teks, latar, garis, dan aksen SCM.
- R-30 PASS: tidak mengadopsi tampilan produk lain.
- R-31 PASS: alasan tabel, label mobile, warna, tipografi, dan pemisahan panel tertulis dalam Design Read.
- Human PASS: label terhubung ke input, tabel memiliki header, status tidak bergantung warna, target tombol minimum 44 px, serta fokus dan garis memenuhi 3:1.
- Human PASS: warna turunan dark menghasilkan teks 16,98:1, bantuan 12,04:1, garis 6,99:1, serta fokus 10,52:1 pada latar `#111827`.
- Layoutmobile PASS: filter reflow satu/dua/tiga kolom, tabel menjadi daftar berlabel, tidak memotong isi, dan tidak menambah navigasi fixed atau kontrol hover.
- Copywriting PASS: teks baru bersumber dari kebutuhan pengguna dan perilaku yang diuji; label menyebut tindakan, tanpa klaim rekaan atau penutup promosi.

Penerapan ini aktif di lokal. Penerapan ke server belum dilakukan.
