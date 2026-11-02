# Delivery: pembuat kode Project KPMOG Rev.4

Mode During menggunakan pilihan pengguna pada sesi yang sama. Arah visual tetap mengikuti SCM IK WorkDesk. ENERGY 1 / RHYTHM 1 / MOTION 1. Fokus tambahan adalah pratinjau kode, dengan isian komponen mengikuti susunan kode pada PDF. Form memakai grid, label, kontrol, fokus, warna, dan state yang sudah ada.

Sumber domain: `KPMOG PROJECT CODE_Rev.4.pdf`, kedua halaman telah diekstrak dan diperiksa secara visual. Format standar memakai singkatan Project, dua digit tahun awarded, work code SP/SC/SPC, dan tiga digit nomor urut. Contoh dengan pembeda BMTH-LNG/BMTH-BBM serta kode lama COMP-19E001 dipertahankan. Nomor urut Project diisi pengelola; PDF tidak menetapkan aturan reset counter Project yang dapat diimplementasikan otomatis.

## Bukti

- PASS: 262 tes, 2.897 assertion pada unit formatter, seluruh tes fitur SCM, dan katalog akses menu.
- PASS: `npm run build`, Pint untuk PHP yang berubah, dan `git diff --check`.
- PASS: `output/project-code-master-20261102/browser-checks.json` mencatat interaksi, lebar viewport, dan console tanpa error pada database uji terpisah.
- PASS: hasil akhir diperiksa pada screenshot desktop dan ponsel. Tidak ada perubahan schema atau penulisan data bisnis lokal melalui pengujian ini.

## Interaksi

- PASS: form baru memilih Format KPMOG; komponen kosong menampilkan petunjuk melengkapi kode.
- PASS: input `ubad`, `2023`, `SPC`, `001` menampilkan `UBAD/23/SPC/001` secara otomatis.
- PASS: pilihan SP, SC, dan SPC masing-masing mengubah segmen pekerjaan pada pratinjau.
- PASS: singkatan kurang dari empat huruf menampilkan validasi ketika disimpan.
- PASS: Simpan master membuat kode; Edit master mengisi kembali singkatan, tahun, jenis pekerjaan, dan nomor urut.
- PASS: kode dengan suffix BMTH-LNG berhasil disimpan melalui form ponsel.
- PASS: kode duplikat ditolak dengan error; Kosongkan form mengembalikan isian standar.
- PASS: edit LTRO membuka mode Kode lama / khusus dan mempertahankan COMP-19E001.
- PASS: pemilih format dapat beralih dari format standar ke kode lama dan kembali.
- PASS: master UBAD yang baru dibuat tersedia pada pilihan master PR. Menyimpan PR menghasilkan nomor dengan kode UBAD/23/SPC/001 secara utuh.
- PASS: perubahan nomor urut master setelah dipakai PR ditolak; nama dan status tetap dapat dikelola sesuai izin yang sudah ada.
- PASS: select jenis pekerjaan dan pratinjau berjalan pada lebar 1440, 768, dan 390 px; workspace dark juga diperiksa.
- PASS: Tab dari singkatan menuju tahun awarded; kontrol baru native dan memakai fokus SCM.
- PASS: teks 200% pada ponsel tidak menyebabkan overflow halaman.

## Delivery Gate

Setiap rule di [gate halaman Nomor PR/SR](delivery-nomor-pr-sr-2026-11-02.md) ditinjau kembali untuk perubahan ini. Hasil berikut melengkapi bukti gate tersebut.

- R-02 PASS: petunjuk berbahasa Indonesia menjelaskan pengisian nyata; tanpa em dash.
- R-03 PASS: grid satu/dua/tiga kolom sesuai lebar, tanpa overflow halaman di 390/768/1440 px.
- R-17 PASS: tidak menambahkan statistik produk.
- R-18 PASS: tidak menambahkan testimonial atau profil.
- R-23 PASS: tidak menambahkan aset atau menu baru pada tahap ini.
- R-24 PASS: route tetap sama dan pemilih master PR berhasil digunakan.
- R-25 PASS: memakai pasangan warna SCM yang sudah dihitung pada gate sebelumnya; tidak menambah warna.
- R-26 PASS: semua input komponen, pemilih format, preview, simpan, edit, dan reset dijalankan di browser.
- R-27 PASS: preview kosong, status loading, serta error komponen/duplikat/kode terpakai tersedia dan diuji.
- R-28 PASS: tidak menambahkan FAQ.
- R-32 PASS: input/select/button native, urutan DOM logis, dan fokus SCM tetap terlihat.
- R-33 PASS: perubahan dilakukan pada sumber Blade/PHP; tidak ada script runtime untuk menambal UI.
- R-34 PASS: mode terang aplikasi dipertahankan; workspace dark juga diperiksa.
- R-35 PASS: build, tes, interaksi browser, dan inspeksi screenshot selesai.
- R-36 PASS: tidak menambahkan klaim keamanan, performa, atau kepatuhan.
- R-37 PASS: arah dan dials mengikuti halaman SCM yang sudah ada; fokus preview memiliki tujuan pemeriksaan sebelum simpan.
- R-38 PASS: format dan kode contoh berasal dari PDF pengguna; data browser ditandai DEMO.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambahkan gradient, glow, ikon, typeface, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambahkan glassmorphism, shadow, glow, feature cards, animasi, atau ilustrasi.
- Liveliness PASS: dials 1/1/1 konsisten; preview menjadi fokus, grid mengelompokkan komponen, dan format kode memberi konteks operasional KPMOG.
- C-1/C-2/C-3 PASS: komponen baru membentuk kode Project; semua aksi bekerja; tidak ada konten pengisi.
- C-4/C-5 PASS: validasi, state, keyboard, responsive, dan pembesaran teks diperiksa; tidak ada klaim rekaan.
- R-05/R-11/R-15/R-16 PASS: struktur sesuai susunan kode, radius mengikuti komponen lama, aksi spesifik, tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas, tema, palet, dan komponen SCM dipertahankan; tujuan setiap tambahan dicatat.
- Human PASS: label terhubung ke input, preview memakai output dan aria-live, pesan error berupa teks, target dan fokus mengikuti CSS SCM.
- Layoutmobile PASS: grid reflow sesuai lebar dan tabel mobile lama dipertahankan; tanpa navigasi fixed atau interaksi hover baru.
- Copywriting PASS: petunjuk memberi contoh dan batas input yang benar-benar divalidasi; kode lama dan standar dijelaskan sesuai sumber.

Pembaruan tersedia di lokal dan tidak memerlukan migrasi tambahan. Penerapan ke server belum dilakukan.
