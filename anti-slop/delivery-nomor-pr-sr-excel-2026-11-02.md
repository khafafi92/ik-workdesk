# Delivery: tampilan register PR/SR seperti Excel

Mode During mengikuti pilihan pengguna sebelumnya. Design Read: pengguna meminta view nomor PR/SR seperti Excel. ENERGY 1 / RHYTHM 1 / MOTION 1. Grid sel memisahkan data, huruf kolom dan nomor baris membantu penelusuran, header tetap terlihat saat digulir. Komponen dan warna SCM tetap menjadi acuan.

Perubahan terbatas pada Blade daftar nomor dan CSS register. Tidak mengubah query, penerbitan nomor, master kode Project, workflow approval, hak akses, atau database. Daftar master di bawah register mempertahankan tampilannya.

## Verifikasi

- PASS: build Vite dan `git diff --check`.
- PASS: 11 tes RequestNumberingTest dan ProjectCodeMasterTest, 106 assertion.
- PASS: browser Chrome pada database SQLite uji terpisah. Bukti: `output/request-number-excel-20261102/browser-checks.json` dan screenshot `sheet-*.png`.
- PASS: 10 kolom data terpisah ditambah nomor baris; sel mempunyai garis kanan dan bawah 1 px, serta batas luar tabel.
- PASS: posisi header diukur setelah scroll vertikal, nomor baris diukur setelah scroll horizontal; keduanya tetap pada posisi yang ditentukan.
- PASS: lebar 1847, 1440, 768, dan 390 px tanpa overflow halaman. Tabel tetap berupa lembar yang dapat digulir dalam containernya pada ponsel, sesuai permintaan gaya Excel.
- PASS: teks 200% pada lebar 390 px tanpa overflow halaman.

## Interaksi

- PASS: pencarian judul menampilkan satu nomor yang sesuai; Hapus semua filter mengembalikan daftar.
- PASS: filter HO/Project, PR/SR, perusahaan, dan pemakaian nomor tetap bekerja; hasil kosong menampilkan pesan.
- PASS: tombol halaman kedua membuka data selanjutnya dengan nomor baris mulai 16; reset filter kembali ke halaman pertama.
- PASS: fokus keyboard pada tabel dan ArrowRight menggeser lembar secara horizontal.
- PASS: pengguna lain tidak dapat membaca nomor pemohon pertama atau mengakses konfigurasi master.
- PASS: console browser tanpa kesalahan JavaScript.

## Delivery Gate

Gate lengkap [halaman Nomor PR/SR](delivery-nomor-pr-sr-2026-11-02.md) ditinjau kembali untuk cakupan perubahan presentasi ini.

- R-02 PASS: petunjuk baru berbahasa Indonesia, tanpa em dash.
- R-03 PASS: filter menjadi satu/dua/lima kolom sesuai lebar; scrolling lembar dibatasi container, tanpa overflow halaman.
- R-17/R-18 PASS: tidak ada statistik produk, testimonial, atau profil baru.
- R-23 PASS: pengguna meminta gaya Excel; tidak membuat aset visual atau navigasi baru.
- R-24 PASS: route dan kontrol navigasi yang sudah ada dipertahankan.
- R-25 PASS: teks header `#111827` pada `#f9fafb` 16,98:1; grid `#6b7280` pada header 4,63:1. Teks, bantuan, dan fokus lainnya memakai token SCM yang sudah diperiksa.
- R-26 PASS: semua filter, pencarian, reset, pagination, serta scrolling pointer/keyboard bekerja dalam browser.
- R-27 PASS: loading dan alert lama dipertahankan; empty state disesuaikan menjadi colspan 11 dan diuji.
- R-28 PASS: tidak menambah FAQ.
- R-32 PASS: tabel native memakai scope pada header kolom/baris; region scroll dapat difokuskan dan memakai outline SCM. Huruf kolom dekoratif disembunyikan dari pembaca layar.
- R-33 PASS: perubahan ditulis pada sumber Blade/CSS, tanpa patch runtime.
- R-34 PASS: mode terang aplikasi dipertahankan; token workspace dark juga diuji pada 390 px.
- R-35 PASS: build, tes, interaksi, pengukuran sticky, dan inspeksi screenshot selesai.
- R-36/R-38 PASS: isi tabel tetap berasal dari query berizin; fixture browser ditandai DEMO; tanpa klaim baru.
- R-37 PASS: arahan Excel berasal dari pengguna; dials 1/1/1 konsisten dengan register operasional.
- R-01/R-04/R-06/R-08/R-09 PASS: tanpa gradient, glow, ikon, typeface, panah, atau badge baru.
- R-07 PASS: grid sel memiliki fungsi membaca data seperti Excel, sesuai arahan pengguna.
- R-10/R-12/R-13/R-14/R-19/R-22 PASS: tanpa glassmorphism, bayangan, glow, feature cards, animasi, atau ilustrasi baru.
- Liveliness PASS: nomor dokumen menjadi fokus kolom A, whitespace mengelompokkan filter dan lembar; konten serta format pengadaan memberi identitas halaman.
- C-1/C-2/C-3 PASS: semua kolom menunjukkan data yang sudah ada, semua kontrol bekerja, tidak ada toolbar atau sel edit palsu.
- C-4/C-5 PASS: state, keyboard, responsive, teks 200%, serta akses diuji; tidak ada data produk rekaan.
- R-05/R-11/R-15/R-16 PASS: struktur mengikuti lembar data, radius register dirapatkan sesuai brief, label aksi lama dipertahankan, tanpa buzzword.
- R-20/R-21/R-29/R-30/R-31 PASS: warna dan tema mengikuti SCM; tampilan grid mengikuti permintaan Excel dan tujuan komponennya dicatat di atas.
- Human PASS: header semantik, teks status, fokus, batas sel, dan target kontrol lama dipertahankan.
- Layoutmobile PASS: filter menumpuk dan tabel digeser dalam area sendiri; header tetap tersedia saat membaca kolom di luar layar.
- Copywriting PASS: petunjuk baru menjelaskan cara menggeser lembar yang telah diuji.

Perubahan tersedia pada kode dan build lokal. Penerapan ke server belum dilakukan.
