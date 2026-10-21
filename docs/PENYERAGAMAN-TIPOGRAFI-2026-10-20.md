# Penyeragaman tipografi, 20 Oktober 2026

Brief pengguna: mengecilkan font yang terlalu besar pada Report Overview dan Rekap Kebutuhan ATK, serta menyamakan tampilannya dengan halaman lain. Anti-slop digunakan selama implementasi, mengikuti pilihan proyek yang sudah tercatat. Arah desain: halaman operasional Internal9 untuk pengguna internal, ringkas dan mengikuti komponen yang ada; ENERGY 1 / RHYTHM 1 / MOTION 1.

## Perubahan

| Elemen | Sebelumnya | Sekarang |
| --- | --- | --- |
| Judul halaman Filament | 30 px | 20 px / 700, line-height 28 px |
| Subjudul halaman | 18 px | 13 px / 400 |
| Judul bagian Report Overview | 16 px / 700 | 14 px / 600 |
| Angka ringkasan Report Overview | 21,6 px / 750 | 18 px / 600 |
| Status teks, misalnya Not available | 21,6 px / 750 | 13 px / 500 |
| Judul bagian utama ATK pada desktop lebar | Hingga 27,2 px / 800 | 14 px / 600 |
| Angka ringkasan ATK | Hingga 24 px / 800 | 18 px / 600 |
| Teks pendukung ATK | 14 px | 13 px |

Skala judul dan subjudul diterapkan pada selector bersama agar halaman lain mengikuti tema yang sama. Task Categories diperiksa sebagai pembanding: judulnya juga menjadi 20 px dengan keluarga font Inter Variable yang sama. Angka memakai tabular numerals agar lebar digit konsisten. Padding ringkasan ATK disesuaikan menjadi 16 px untuk mendukung skala teks yang lebih ringkas. Label pemantauan tidak lagi memakai huruf kapital dan tracking lebar.

Warna, data, filter, kalkulasi, status, query, dan authorization dipertahankan. View laporan hanya menambahkan kelas presentasi untuk membedakan nilai angka dari status teks.

## Delivery Gate untuk perubahan tipografi

- **PASS, Hard Gate:** kedua halaman dijalankan dan hasil render diperiksa. Pada lebar CSS aktual 390, 768, dan 1440 px, scrollWidth sama dengan clientWidth dan tidak ada heading/label/nilai ringkasan yang terpotong. Data dan angka berasal dari aplikasi. Tidak dibuat aset UI, tautan, kontrol, atau klaim baru.
- **PASS, Purpose Gate:** font Inter mengikuti tema yang sudah digunakan. Skala 20/14/13 px membedakan judul, bagian, dan teks pendukung; angka 18 px tetap dapat dipindai. Status teks 13 px mencegah keterangan kosong mendominasi metrik. Warna biru aksi dan aksen oranye kebutuhan belum diterima tetap memiliki fungsi yang sama.
- **PASS, Liveliness dan konsistensi:** ENERGY/RHYTHM/MOTION 1/1/1 mengikuti halaman operasional yang ada. Hierarki teks diperjelas tanpa menambah animasi, dekorasi, atau komposisi baru. Selector judul bersama dan pemeriksaan Task Categories membuktikan penyeragaman lintas halaman.
- **PASS, Craftsmanship dan aksesibilitas dalam lingkup perubahan:** seluruh sembilan kontrol filter tetap setinggi 44 px pada tiga ukuran layar. Navigasi Tab menempatkan fokus pada Status dengan outline biru yang terlihat. Status Open menghasilkan total 1, Reset mengembalikan pilihan kosong dan total 2; filter Resolved menampilkan keadaan kosong. Empty state ATK tetap terbaca. Tidak ada warning/error console yang tertangkap. Ukuran browser dikembalikan ke semula setelah pemeriksaan.
- **PASS, kontras teks:** checker lokal menghasilkan rasio 14,10:1 untuk ink utama, 6,12:1 untuk teks pendukung, 6,94:1 untuk teks pada latar oranye lembut, 6,12:1 untuk label oranye, serta 6,47/6,39/6,58:1 untuk status bahaya/peringatan/sukses pada putih. Fokus biru terhadap putih adalah 6,87:1. Tidak ada warna teks yang diubah menjadi lebih pucat.
- **PASS, validasi teknis:** PostCSS berhasil mem-parsing stylesheet (435 node), `git diff --check` lulus, dan pengujian ATK existing `test_atk_requirement_summary_shows_request_context_and_delivery_progress` lulus dengan 13 assertion. Tidak ditambahkan tes baru untuk perubahan presentasi ini.

Pemeriksaan ini terbatas pada tipografi dan interaksi yang berkaitan pada dua halaman, dengan satu halaman pembanding. Pengujian ini tidak menyatakan seluruh fungsi aplikasi atau semua state bisnis bebas masalah. Bug fungsional yang tercatat pada laporan sebelumnya tidak diubah oleh pekerjaan ini.

## Bukti

Pratinjau: [Report Overview](../output/typography-review-20261020/report-preview.png) dan [Rekap Kebutuhan ATK](../output/typography-review-20261020/atk-preview.png).

Ukuran aktual dan hasil browser disimpan pada `output/typography-review-20261020/responsive-checks.json`, `report-fonts.json`, `atk-fonts.json`, dan `atk-regression.xml`. Screenshot mobile yang tersimpan merupakan capture tambahan; acuan ukuran CSS tiga breakpoint adalah JSON hasil browser.
