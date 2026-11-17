# Pemeriksaan Permintaan, SCM, dan Purchasing Officer

Catatan fase berikutnya: master inventori bersama dan gudang per entitas telah ditambahkan serta dimigrasikan pada aplikasi lokal. Lihat [pemeriksaan Inventori bersama](delivery-shared-inventory-2026-11-06.md) untuk status terbaru. Catatan penerapan dan browser di bawah hanya berlaku pada fase pemisahan menu ini.

6 November 2026. Anti-slop diterapkan selama implementasi sesuai pilihan pengguna. Kode dan pengujian diperiksa; Delivery Gate visual belum lengkap karena Computer Use dihentikan otomatis saat URL Edge tidak dapat dipastikan. Laporan ini tidak menyatakan seluruh gate lulus.

## Arah dan alasan

Halaman operasional untuk peminta, SCM Project, dan Purchasing Officer HO mengikuti aplikasi yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1.

- Permintaan menampung semua pengajuan; SCM dan Purchasing Officer memiliki antrean terpisah agar petugas langsung menemukan pekerjaannya.
- Kedua menu proses berada di kelompok Pengadaan bersama rekap material dan data vendor untuk memisahkan pengajuan dari pekerjaan pengadaan.
- Nama Requestor tidak mengulang departemen karena Department/Div sudah tersedia pada baris berikutnya.
- Formulir, font, jarak, warna, fokus, dan target kontrol mengikuti komponen yang sudah ada demi konsistensi dokumen perusahaan.
- Filter dan tabel dipertahankan untuk mencari dokumen; scroll lokal menjaga nomor serta tahap dapat dibaca pada layar sempit.
- Tidak ada ilustrasi, kartu dekoratif, warna, atau animasi baru.

## Perilaku dan bukti

Pengajuan PR/SR hanya dibuat di Permintaan. Peminta melihat pengajuan sendiri dan hasil final; superuser tetap dapat melihat semua serta memilih pemohon. Tombol penerimaan, vendor, penawaran, CBE, dan legalitas berada di menu pemrosesan. Setelah seluruh persetujuan lengkap, HO masuk Purchasing Officer dan Project masuk SCM sesuai entitas serta departemen.

Kode izin `ga.process` tetap digunakan untuk kompatibilitas; nama antarmukanya Purchasing Officer. Izin lama `scm.process` tetap membuka kedua menu, tetapi setiap antrean dan aksi dibatasi jenis kebutuhan. Draft dan proses persetujuan tidak masuk antrean pemrosesan, termasuk untuk superuser. URL inventori menuju halaman pemrosesan yang sesuai untuk petugas, dan menuju PR/SR untuk pemohon atau draft.

- Regresi SCM, ATK, dan akses tambahan: 325 tes lulus, 3.456 assertion, pada `output/inventory-20261106/tests-purchasing-officer-regression.json`.
- Sebelum regresi, 21 tes pembagian tim dan PR delegasi lulus, 219 assertion, pada `tests-separate-teams.json`.
- Pemeriksaan akhir pembagian tim, PR delegasi, dan navigasi per role: 24 tes lulus, 231 assertion, pada `tests-purchasing-officer-final.json`. Tiga kasus navigasi memeriksa HTML sidebar untuk Purchasing Officer, SCM, dan superuser dengan aplikasi uji terpisah per kasus. Sebagian besar tes ini sudah tercakup pada regresi; jumlah kedua suite tidak dijumlahkan.
- Build lulus pada `build-purchasing-officer.txt`; Pint dan `git diff --check` lulus.
- Browser QA memakai `127.0.0.1:8011`. Pemeriksaan berhenti di proses login; halaman revisi tidak diklaim telah diperiksa secara visual.
- Tidak ada deployment, migrasi database operasional, atau perubahan role akun nyata pada revisi ini.

## Hard Gate

- R-02 PASS: copy baru memakai nama tindakan dan penangan tanpa em dash.
- R-03 TERTUNDA: grid responsif, kontrol minimum 44 piksel, dan scroll tabel lokal tersedia pada source; tampilan mobile revisi belum diperiksa langsung.
- R-17 PASS: angka dan daftar berasal dari query dokumen sesuai akses.
- R-18 PASS: tidak ada testimonial baru.
- R-23 PASS: nama serta pemisahan menu berasal dari permintaan eksplisit pengguna; tidak ada aset baru.
- R-24 PASS: route PR/SR dan menu proses terdaftar; halaman dirender dalam pengujian aplikasi.
- R-25 PASS: token warna tidak diubah; kontras mengikuti pemeriksaan stylesheet pada laporan inventori.
- R-26 PASS: kontrol terhubung pada aksi Livewire; filter, penerimaan, pembatasan jenis kebutuhan, pemohon, dan role diperiksa melalui tes aplikasi.
- R-27 PASS: view menyediakan kondisi tanpa hasil, loading, dan pesan error; komponen tersebut dipakai ulang.
- R-28 PASS: tidak ada FAQ baru.
- R-32 TERTUNDA: label kontrol, kontrol native, dan outline fokus tersedia; keyboard pada halaman revisi belum diperiksa langsung.
- R-33 PASS: PHP dan Blade diubah langsung melalui patch source.
- R-34 PASS: panel mempertahankan konfigurasi tema terang `darkMode(false)`.
- R-35 TERTUNDA: build dan regresi lulus; click-through visual belum lengkap karena penghentian Computer Use.
- R-36 PASS: tidak ada klaim keamanan, kepatuhan, pelanggan, atau kinerja baru.
- R-37 PASS: arah mengikuti aplikasi dan pembagian menu yang diminta pengguna; dials 1/1/1 dinyatakan sebelum implementasi.
- R-38 PASS: entitas, kebutuhan, tahap, dan peran berasal dari aplikasi; fixture QA ditandai terpisah.

## Purpose Gate

- R-01 PASS: tidak ada gradien atau glow baru.
- R-04 PASS: tidak ada ikon baru.
- R-06 PASS: font perusahaan dipertahankan untuk konsistensi formulir.
- R-07 PASS: tidak ada pola latar baru.
- R-08 PASS: tidak ada panah dekoratif baru.
- R-09 PASS: label tahap dan penangan memiliki fungsi operasional serta berasal dari dokumen.
- R-10 PASS: tidak ada glassmorphism baru.
- R-12 PASS: tidak ada shadow baru.
- R-13 PASS: tidak ada glow baru.
- R-14 PASS: tabel melayani daftar kerja, tanpa kartu fitur tambahan.
- R-19 PASS: tidak ada animasi baru.
- R-22 PASS: tidak ada ilustrasi baru.

## Liveliness

- Dials PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 sesuai halaman operasional.
- Konsistensi PASS: komponen formulir dan tabel lama dipakai ulang.
- Fokus PASS: daftar tugas atau dokumen yang dibuka tetap menjadi isi utama view.
- Whitespace PASS: jarak lama memisahkan navigasi, filter, dan tabel.
- Aksen PASS: tab aktif, tautan, dan outline memakai aksen aplikasi.
- Identitas PASS: formulir perusahaan, nomor PR/SR, KPMOG/APCA, dan HO/Project dipertahankan.
- Design Read PASS: tujuan, bahasa visual, dan dials dinyatakan sebelum revisi.

## Craftsmanship dan Quality Locks

- C-1 PASS: alasan pemisahan menu, nama pemohon, tabel, dan gaya ditulis pada bagian arah.
- C-2 PASS: kontrol mempunyai aksi nyata dan otorisasi backend diuji.
- C-3 PASS: menu pengajuan dan menu proses mengikuti tugas masing-masing, tanpa bagian pengisi.
- C-4 TERTUNDA: batas role, jenis kebutuhan, dan tahap diuji; pemeriksaan visual mobile, keyboard, dan zoom belum lengkap.
- C-5 PASS: tidak ada bukti sosial atau angka promosi buatan.
- R-05 PASS: komposisi mengikuti daftar kerja dan dokumen perusahaan.
- R-11 PASS: bentuk serta radius kontrol tidak diubah.
- R-15 PASS: tindakan menyebut penerimaan, penawaran, CBE, legalitas, dan pengaturan secara konkret.
- R-16 PASS: tidak ada istilah promosi AI.
- R-20 PASS: isi tetap spesifik untuk proses IK WorkDesk dan entitasnya.
- R-21 PASS: konfigurasi tema tidak diubah.
- R-29 PASS: token palet aplikasi dipertahankan.
- R-30 PASS: tidak ada peniruan desain produk lain.
- R-31 PASS: alasan keputusan utama tercatat di bagian arah.

Verifikasi visual belum dinyatakan selesai. Butir tertunda memerlukan pemeriksaan browser yang dapat dikendalikan.
