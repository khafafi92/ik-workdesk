# Pemeriksaan CBE dan Rekap Material PR

Mode During, dipilih pengguna. Arah: lembar Excel untuk tim pengadaan dalam gaya aplikasi yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Tabel adalah fokus; garis sel, header lengket, dan nomor baris mendukung pembacaan item PR. Tipografi, warna, jarak, dan fokus menggunakan komponen SCM yang sudah ada. Tidak ada aset visual baru.

Status implementasi: tes dan build lulus. Gate visual belum lengkap karena computer-use menghentikan akses browser Windows saat URL tidak dapat dipastikan. PENDING berarti belum diverifikasi secara visual, bukan PASS. Pengujian Livewire di bawah memanggil metode komponen; bukan click-through browser.

## Hard Gate

- R-02 PASS: teks baru tidak memakai em dash.
- R-03 PENDING: tabel memakai container overflow dan grid responsif yang sudah ada; viewport dan zoom belum diperiksa di browser.
- R-17 PASS: nomor baris berasal dari paginasi item, tanpa statistik tambahan.
- R-18 PASS: tidak menambahkan testimonial atau identitas rekaan ke aplikasi.
- R-23 PASS: menu dan kolom diminta pengguna; tidak membuat logo, avatar, atau gambar.
- R-24 PASS: route `panel/scm/rekap-material-pr` terdaftar dan render HTTP mengembalikan 200.
- R-25 PASS: checker lokal menghasilkan 17.74:1 untuk #111827 pada putih, 16.98:1 pada #f9fafb, 7.56:1 untuk #4b5563 pada putih, 7.31:1 untuk #9a3412 pada putih, dan 4.83:1 untuk #6b7280 pada putih.
- R-26 PASS: pencarian, Hapus filter, paginasi, dan Muat ulang data terhubung ke Livewire; metode tersebut diuji.
- R-27 PASS: empty state diuji, loading berteks memakai wire:loading.delay, error query dan pemulihan diuji melalui Livewire.
- R-28 PASS: tidak menambahkan FAQ.
- R-32 PENDING: label input, native button, tabindex region, dan focus-visible tersedia; urutan keyboard belum diperiksa di browser.
- R-33 PASS: perubahan ditulis di source PHP, Blade, dan CSS melalui patch langsung.
- R-34 PASS: panel aplikasi menetapkan darkMode(false); tidak menambahkan tema atau toggle.
- R-35 PENDING: Vite berhasil dibangun dan HTTP berhasil; click-through dan pemeriksaan console browser terhalang computer-use.
- R-36 PASS: tidak menambahkan klaim keamanan, compliance, atau performa.
- R-37 PASS: arah Excel diberikan pengguna dan pola SCM dipertahankan; dials dinyatakan sebelum implementasi.
- R-38 PASS: aplikasi menggunakan data tersimpan; seluruh fixture QA terpisah dan berlabel DEMO.

## Purpose Gate

- R-01 PASS: memakai variabel warna SCM yang sudah ada tanpa gradient atau glow baru.
- R-04 PASS: tidak menambahkan ikon.
- R-06 PASS: tipografi SCM dan formulir CBE dipertahankan untuk konsistensi dokumen.
- R-07 PASS: garis hanya pada sel tabel sesuai format Excel yang diminta, bukan pola latar dekoratif.
- R-08 PASS: tidak menambahkan panah pada tombol.
- R-09 PASS: tidak menambahkan badge.
- R-10 PASS: tidak menambahkan glassmorphism.
- R-12 PASS: tidak menambahkan shadow.
- R-13 PASS: tidak menambahkan glow.
- R-14 PASS: tidak menambahkan kartu fitur.
- R-19 PASS: tidak menambahkan animasi; loading menggunakan indikator bawaan Livewire.
- R-22 PASS: tidak menambahkan ilustrasi.

## Liveliness

- Dials PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 dinyatakan dalam pembacaan desain.
- Kesesuaian dials PASS: satu tabel operasional, tanpa animasi atau bagian dekoratif baru.
- Focal point PASS: tabel material PR merupakan satu-satunya area data baru pada halaman.
- Whitespace PASS: pola panel, filter, aksi, tabel, dan keterangan memakai spacing SCM.
- Accent PASS: fokus dan tautan memakai aksen SCM yang sudah ada, tanpa warna aksen tambahan.
- Identity motif PASS: susunan lembar Excel dan dokumen CBE memakai pola operasional SCM yang ada.
- Design Read PASS: arah dan dials dicatat sebelum menulis tampilan.

## Craftsmanship dan Quality Locks

- C-1 PASS: tabel dan harga melayani pemeriksaan item PR serta vendor terpilih.
- C-2 PASS: Livewire memverifikasi filter, reset, paginasi, serta pemulihan error; tidak menambahkan tombol kosong.
- C-3 PASS: satu menu rekap sesuai permintaan; tidak menambahkan metrik, grafik, atau bagian pemasaran.
- C-4 PENDING: empty/error/loading tercakup; pemeriksaan breakpoint dan keyboard di browser masih tertunda.
- C-5 PASS: isi berasal dari snapshot final dan record PR yang dapat diakses.
- R-05 PASS: komposisi berupa worksheet yang diminta pengguna, tanpa template landing page.
- R-11 PASS: radius panel dan kontrol mengikuti stylesheet SCM.
- R-15 PASS: aksi memakai teks Hapus filter dan Muat ulang data.
- R-16 PASS: teks baru menjelaskan sumber data dan arti harga tanpa buzzword.
- R-20 PASS: kolom, penomoran, dan rincian vendor sesuai proses PR/CBE aplikasi.
- R-21 PASS: pengaturan tema panel dipertahankan.
- R-29 PASS: tidak menambah warna ke palet SCM.
- R-30 PASS: perubahan mengikuti worksheet pengguna dan komponen SCM, tanpa meniru produk lain.
- R-31 PASS: garis dan sticky header untuk membaca baris; harga rata kanan untuk membandingkan angka; typography dan spacing mengikuti dokumen yang ada.

## Bukti teknis

- PHPUnit: 121 tes terkait lulus; perubahan terakhir pada fallback kode project dan pemulihan error juga diuji ulang.
- Pint: semua berkas PHP yang berubah lulus.
- Vite: `npm run build` lulus.
- HTTP: halaman rekap dan cetak CBE mengembalikan 200 dengan kolom, nama project, nomor, vendor, dan harga yang diharapkan.
- Fixture 28 item: halaman 1 berisi 25 item; halaman 2 berisi sisanya; pencarian mengembalikan halaman 1; Hapus filter memulihkan hasil; requester lain tidak melihat vendor.
- PostgreSQL lokal: tujuh nomor CBE diverifikasi setelah migrasi; atribut CBE selain nomor identik dengan cadangan sebelum migrasi.

## Pembaruan COMMENT dan Differences

- Arah dan mode PASS: During dipertahankan; formulir CBE yang sama, ENERGY 1 / RHYTHM 1 / MOTION 1.
- R-26 PASS: COMMENT memakai textarea komponen SCM dengan label dan binding cbeForm.comment; simpan, buka ulang, kosongkan, dan finalisasi diuji melalui Livewire.
- R-27 PASS: Differences tanpa anggaran menampilkan penjelasan penyebab di bawah lembar; mata uang asing tanpa konversi memakai pesan khusus kurs IDR.
- R-17/R-38 PASS: anggaran tidak diisi dengan nilai rekaan; anggaran kosong tidak dianggap nol. Tes anggaran nol tetap menghasilkan selisih numerik.
- C-2/C-5 PASS: komentar tersimpan terpisah dari catatan PR, ikut snapshot final, dan ditampilkan di persetujuan/cetak; pemohon tanpa izin SCM dan perubahan pada CBE final ditolak.
- R-25 PASS: memakai textarea, teks, latar, dan focus-visible dari komponen SCM tanpa perubahan warna.
- R-01/R-04/R-06/R-07/R-08/R-09/R-10/R-12/R-13/R-14/R-19/R-22 PASS: tidak menambahkan teknik visual, aset, ikon, atau animasi baru.
- R-02/R-15/R-16/R-23/R-24/R-28/R-33/R-34/R-36/R-37 PASS: label COMMENT mengikuti formulir yang diminta, aksi memakai tombol simpan yang ada, kode ditulis langsung di source, tema dan navigasi tetap.
- R-05/R-11/R-20/R-21/R-29/R-30/R-31 PASS: susunan lembar dan komponen SCM dipertahankan; teks tambahan menjelaskan sumber anggaran dan penyebab selisih belum dihitung.
- R-03/R-32/R-35/C-4 PENDING: browser belum dapat diperiksa; pengujian metode Livewire tidak dinyatakan sebagai click-through atau verifikasi viewport.
- Validasi teknis PASS: 104 tes terkait CBE/pengadaan, Pint, dan build Vite lulus setelah perubahan komentar dan pesan anggaran.
