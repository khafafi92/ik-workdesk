# Tema workspace seluruh halaman

Mode: During, melanjutkan arah desain yang sudah disetujui pengguna. Permintaan terakhir memperluas gaya dashboard ke seluruh halaman master dan modul. Dials tetap ENERGY 2 / RHYTHM 2 / MOTION 2.

## Hasil

Tema bersama sekarang mencakup daftar, create/edit, detail, tab, modal, profil, autentikasi, portal vendor, dan LTRO. Adapter modul menyamakan SCM, inventori, ATK, attendance, laporan, kalender, IT, serta percakapan dengan komponen Filament. Layout di luar panel memuat stylesheet yang sama melalui `x-workspace-styles`.

Teks isi memakai skala 13 px; judul halaman 22 sampai 28 px dengan berat 500. Nilai ringkasan modul memakai 24 sampai 28 px, sedangkan total dashboard tetap mengikuti revisi pengguna sebelumnya. Status bisnis tetap mempunyai warna dan label masing-masing.

Inter memakai berkas font yang sudah tersedia di aplikasi. Logo profil menggunakan aset Internal9 yang sudah ada. Tidak ada aset, metrik, atau data produksi baru. Fixture bertanda QA hanya berada dalam SQLite terpisah.

Perubahan tidak menyentuh policy, route, controller, model, perhitungan, maupun proses persetujuan. Format dokumen resmi dan worksheet tetap dipertahankan; adapter modul dibatasi untuk media screen. Import Tailwind yang belum dikompilasi di CSS LTRO dihapus karena sebelumnya memicu permintaan `/ltro/tailwindcss` yang gagal.

## Alasan visual

- Kanvas abu muda dan permukaan putih menyatukan perpindahan modul tanpa mengubah struktur kerja masing-masing.
- Biru menandai tindakan utama dan tab aktif. Hijau, kuning, dan merah tetap menyampaikan status operasional.
- Teks yang lebih kecil dan berat 400/500 mengikuti koreksi pengguna serta mengurangi persaingan antara judul, label, dan data.
- Radius 8 px untuk kontrol dan 12 px untuk panel membedakan tindakan dari kelompok informasi; bayangan hanya diperlukan untuk lapisan mengambang.
- Transisi 160 ms memberi umpan balik pada kontrol; kemunculan judul 240 ms menandai perpindahan halaman. Reduced motion menonaktifkannya. Tidak ada animasi berulang atau transform pada pembungkus modal.
- Tabel, formulir, kalender, dan dokumen mempertahankan susunan sesuai pekerjaannya. Referensi monday dipakai untuk ketenangan dan konsistensi workspace; identitas serta alur Internal9 tetap menjadi dasar.

## Verifikasi

- PASS: 122 URL QA yang diizinkan merespons HTTP 200 dan memuat tema bersama. Cakupan mencakup halaman panel terdaftar, index/create resource yang dapat diakses, detail/edit fixture yang tersedia, admin CRUD, profil, tiga halaman LTRO, serta lima langkah vendor dan review. [Daftar hasil](../output/workspace-all-pages-20261109/route-coverage.json).
- PASS: 64 tes, 693 assertion. Suite mencakup coverage tema, profil, dashboard accessibility, vendor publik/internal, dan perangkat IT. Resource lama yang sengaja ditolak tetap HTTP 403; halaman hilang tetap HTTP 404. [Hasil tes](../output/workspace-all-pages-20261109/tests.json).
- PASS: `npm run build`, Pint untuk test baru, parsing dua stylesheet dengan PostCSS, dan `git diff --check`.
- PASS: skenario REA selesai untuk tab IT dengan Enter, filter dan tiga bagian SCM, kalender minggu berikut/sebelumnya/Today, dialog profil dibuka lalu ditutup dengan Escape, perpindahan langkah vendor dan ekspansi unggahan. Tidak ada galat JavaScript pada skenario selesai.
- PASS: pemeriksaan visual pada lebar 320, 390, 768, dan 1440 px. Tabel lebar tetap menggulir dalam pembungkusnya. [Rekaman langkah dan accessibility tree](../output/workspace-all-pages-20261109/browser-checks.json).
- PASS: checker kontras untuk putih pada biru `#005fcc` menghasilkan 5.98:1 dan teks sekunder `#5b6070` pada kanvas `#f6f7fb` menghasilkan 5.85:1. Teks utama pada putih 13.64:1; batas kontrol `#858d9c` pada putih 3.34:1.

Pemeriksaan visual ini memakai browser desktop headless dengan viewport ponsel, bukan perangkat fisik. Keyboard layar dan zoom browser 200% belum diverifikasi. Sweep 122 URL memeriksa render dan pemuatan tema; tidak menyatakan bahwa setiap tindakan bisnis di setiap URL telah dijalankan. Beberapa percobaan browser tambahan berhenti di alat otomatisasi; hanya skenario lengkap dipaketkan sebagai bukti. Console HTTP 404 pada skenario halaman hilang merupakan status yang memang diuji, bukan galat JavaScript.

## Delivery gate untuk perubahan presentasi

| Pemeriksaan | Status dan bukti |
| --- | --- |
| R-02, R-15, R-16: teks | PASS: teks error spesifik dan tindakan masuk/kembali nyata; tidak menambah slogan, emoji dekoratif, atau em dash. |
| R-03: reflow | PASS pada viewport yang direkam: SCM 320 px, vendor/profil/kalender/LTRO/error 390 px, laporan 768 px, formulir 1440 px. |
| R-17, R-18, R-23, R-36, R-38: kebenaran dan aset | PASS: tidak menambah klaim, testimoni, data produksi, atau aset hasil fabrikasi; fixture QA dipisahkan. |
| R-24, R-26: tujuan dan kontrol | PASS untuk perubahan ini: hook, action, method, disabled, serta href lama dipertahankan; skenario komponen bersama direkam. Link pada error menuju route login atau panel yang ada. |
| R-25: kontras | PASS untuk token tema baru: rasio teks dan batas kontrol tercantum di atas; konflik text-fill tombol LTRO diperbaiki dan ditangkap ulang. |
| R-27: keadaan | PASS: empty state kalender/SCM, loading state Livewire yang ada, dialog profil, serta error 403/404 tetap tersedia. |
| R-28: FAQ | PASS: tidak ada FAQ baru dalam scope ini. |
| R-32: fokus dan keyboard | PASS pada komponen yang direkam: Enter pada tab IT/SCM/vendor dan navigasi kalender, Escape pada dialog profil, outline fokus 3 px. |
| R-33: implementasi | PASS: perubahan sumber ditulis langsung melalui patch; skrip QA hanya menyiapkan fixture, memeriksa hasil, dan mengemas bukti. |
| R-34: tema | PASS: aplikasi menggunakan tema terang yang sama; tidak menambahkan toggle tema yang tidak bekerja. |
| R-35: eksekusi | PASS untuk build, render, dan smoke komponen yang dicatat; batas pemeriksaan browser dinyatakan di bagian verifikasi. |
| R-37: arah | PASS: mengikuti brief monday workspace serta koreksi tipografi pengguna, dengan dials 2/2/2 yang sudah dipilih. |
| R-01, R-07, R-10, R-12, R-13, R-22 | PASS: adapter tidak menambah gradient, pola latar, glass, glow, ilustrasi, atau bayangan panel berulang. |
| R-04, R-08, R-09, R-31 | PASS: ikon dan navigasi yang ada menyampaikan modul/tindakan; tidak menambah panah, badge, atau titik dekoratif. |
| R-06 | PASS: Inter lokal dan skala teks bersama melayani pembacaan tabel/form yang padat, dengan alasan di atas. |
| R-14, R-19 | PASS: susunan modul mengikuti isi; gerak terbatas pada judul dan umpan balik kontrol, termasuk reduced motion. |
| Liveliness | PASS: dials 2/2/2, fokus pada judul dan pekerjaan utama; jarak memisahkan filter/data, aksen biru menandai tindakan, serta motif permukaan putih dan tab aktif berulang. |
| C-1, C-3, R-05, R-11 | PASS: setiap kelompok style memiliki tujuan tertulis; tidak menambah bagian pengisi; radius kontrol dan panel dibedakan. |
| C-2, C-4 | PASS dalam pemeriksaan yang dicatat: perilaku komponen tetap berfungsi, layout responsif dan state relevan ditangkap. Batas perangkat fisik/zoom dijelaskan. |
| C-5, R-20, R-21, R-29, R-30 | PASS: informasi tetap berasal dari aplikasi, tema terang mengikuti brief, palet bermakna, dan alur serta dokumen Internal9 dipertahankan. |

## Contoh tampilan

- [SCM desktop](../output/workspace-all-pages-20261109/scm-desktop.png), [SCM 320 px](../output/workspace-all-pages-20261109/scm-mobile-320.png).
- [Form IT](../output/workspace-all-pages-20261109/it-form.png), [profil dan kontrol akun](../output/workspace-all-pages-20261109/profile-mobile.png).
- [Kalender ponsel](../output/workspace-all-pages-20261109/calendar-mobile.png), [laporan tablet](../output/workspace-all-pages-20261109/report-tablet.png).
- [Unggahan vendor](../output/workspace-all-pages-20261109/vendor-mobile.png), [LTRO ponsel](../output/workspace-all-pages-20261109/ltro-mobile.png), [error 404](../output/workspace-all-pages-20261109/error-mobile.png).

Bundle mentah yang memuat sesi browser dan token fixture tetap berada di direktori sementara. Bukti yang disimpan ke repositori hanya screenshot dan ringkasan yang telah menghapus token undangan.
