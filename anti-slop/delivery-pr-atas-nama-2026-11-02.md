# Delivery: pilihan pemohon PR khusus sysadmin

Mode During melanjutkan pilihan pengguna. Design Read: pertahankan lembar PR Excel yang sudah ada dan ubah hanya field Requestor menjadi pilihan bagi Sys Administrator/pemilik sistem. ENERGY 1 / RHYTHM 1 / MOTION 1. Kontrol baru memakai komponen SCM yang sudah ada; keterangan pemohon dan pembuat diletakkan di luar lembar dokumen.

## Bukti

- PASS: 274 tes, 2.994 assertion pada seluruh fitur SCM, formatter kode Project, dan katalog akses.
- PASS: 2 tes konkurensi PostgreSQL, 41 assertion; database sementara dibersihkan dan jumlah record database utama tetap sama.
- PASS: Pint, build Vite, dan `git diff --check`.
- PASS: browser Chrome dengan database SQLite terpisah; bukti `output/delegated-pr-20261102/browser-checks.json` dan screenshot `requestor-*.png`.
- PASS: inspeksi desktop/ponsel, lebar 1440/768/390 px serta teks 200% tanpa overflow halaman.
- PASS: tidak ada migrasi, perubahan database bisnis, role, atau permission saat implementasi dan pengujian lokal.

## Interaksi dan kewenangan

- PASS: sysadmin membuka form Project, memilih master/perusahaan, lalu memilih pemohon dari departemen lain.
- PASS: departemen utama terisi otomatis; daftar departemen mengikuti cakupan pemohon; pratinjau approval mengikuti pemohon yang dipilih.
- PASS: Tab dari pemilih Requestor menuju Department/Div dan fokus tetap terlihat.
- PASS: sysadmin menyimpan draft, melihat catatan pembuat/pemohon, mengunggah lampiran, dan membuka kembali draft.
- PASS: memilih dirinya sendiri menampilkan kontrol tanda tangan sendiri; memilih orang lain menghilangkan kontrol tanda tangan dan pengajuan bagi sysadmin tersebut.
- PASS: pemohon terpilih melihat PR pada daftar miliknya, tidak mendapat pemilih Requestor, menandatangani dengan profilnya sendiri, lalu mengajukan ke Approved by dari master Project.
- PASS: setelah pengajuan, tombol Edit draft tidak tersedia.
- PASS: administrator biasa yang memiliki izin konfigurasi SCM tidak mendapat pemilih Requestor dan tidak melihat PR delegasi tersebut.
- PASS: pengiriman pemohon lain melalui service/Livewire oleh akun biasa ditolak; SR tidak memperoleh delegasi.
- PASS: pemohon nonaktif/tanpa izin, departemen di luar cakupan pemohon, serta draft milik pembuat lain ditolak.
- PASS: perubahan pemohon dan lampiran menghapus tanda tangan lama; nomor serta akun penerbit nomor tetap sama.
- PASS: pencabutan sysadmin menghilangkan akses delegasi, termasuk melalui register nomor terpakai.
- PASS: console JavaScript browser tanpa error pada alur akhir.

## Delivery Gate

Gate lengkap [halaman Nomor PR/SR](delivery-nomor-pr-sr-2026-11-02.md) dan [pembuat kode Project](delivery-project-code-master-2026-11-02.md) ditinjau kembali untuk perubahan form ini.

- R-02 PASS: keterangan berbahasa Indonesia dan label Requestor mengikuti lembar sumber; tanpa em dash baru.
- R-03 PASS: pemilih memakai grid identitas dan mobile state form yang sudah ada; lebar 390/768/1440 px tidak meluber.
- R-17/R-18 PASS: tidak menambahkan statistik, testimonial, atau profil rekaan ke produk.
- R-23 PASS: pengguna meminta pemilih pemohon; tidak membuat aset, logo, atau navigasi baru.
- R-24 PASS: link dan navigasi PR yang sudah ada tetap bekerja pada alur browser.
- R-25 PASS: warna teks, batas, dan fokus mewarisi pasangan SCM yang telah dihitung; tanpa warna baru.
- R-26 PASS: pilihan pemohon, departemen, simpan, edit, unggah, tanda tangan pemohon, serta pengajuan dijalankan melalui browser.
- R-27 PASS: validasi server, ringkasan error, loading, dan keterangan ketika pemohon lain perlu menandatangani tersedia.
- R-28 PASS: tanpa FAQ baru.
- R-32 PASS: select/input/button native dan urutan keyboard sesuai susunan form; tidak ada interaksi khusus mouse.
- R-33 PASS: perubahan ada pada PHP dan Blade sumber; tidak ada patch runtime UI.
- R-34 PASS: mode terang serta lembar cetak putih mengikuti panel yang sudah ditetapkan; tidak menambah atau mengubah tema.
- R-35 PASS: build, tes, interaksi end-to-end, dan inspeksi screenshot selesai.
- R-36/R-38 PASS: identitas produk berasal dari akun aplikasi; data browser berlabel DEMO pada database terpisah, tanpa tanda tangan pengguna nyata.
- R-37 PASS: batas perubahan mengikuti permintaan pemilih langsung di form, dengan dials 1/1/1.
- R-01/R-04/R-06/R-07/R-08/R-09 PASS: tidak menambahkan gradient, glow, ikon, font, pola dekoratif, panah, atau badge.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambah glassmorphism, shadow, feature cards, animasi, atau ilustrasi.
- Liveliness PASS: pilihan pemohon dan identitas departemen menjadi fokus; tipografi, whitespace, serta grid lembar tetap mengikuti PR operasional.
- C-1/C-2/C-3 PASS: setiap tambahan menjelaskan/memilih pemohon dan bekerja; tidak ada konten pengisi.
- C-4/C-5 PASS: kewenangan, state, responsive, keyboard, dan pembesaran teks diperiksa; tanpa klaim rekaan.
- R-05/R-11/R-15/R-16 PASS: susunan, radius, dan label aksi lama dipertahankan; tanpa buzzword atau perubahan gaya dokumen.
- R-20/R-21/R-29/R-30/R-31 PASS: identitas, warna, dan tema SCM tetap menjadi acuan; tujuan kontrol dan keterangan tertulis di atas.
- Human PASS: label select terhubung dengan kontrol, fokus terlihat, target mengikuti minimum 44 px, dan keterangan penandatangan berupa teks.
- Layoutmobile PASS: field menggunakan reflow form lama; tidak ada scroll halaman, kontrol hover, atau navigasi fixed baru.
- Copywriting PASS: keterangan membedakan pemohon, pembuat draft, dan tindakan penandatangan berdasarkan perilaku yang diuji.
