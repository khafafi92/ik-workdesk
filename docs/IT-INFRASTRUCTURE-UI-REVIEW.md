# Preview review Infrastruktur IT

Catatan ini merupakan riwayat preview. Preview telah diganti register PostgreSQL operasional atas permintaan pengguna. Panduan penggunaan dan aktivasi terbaru: [IT-INFRASTRUCTURE-OPERASIONAL.md](IT-INFRASTRUCTURE-OPERASIONAL.md).

Preview dibuat pada 16 Oktober 2026 setelah pengguna meminta hasil yang dapat dilihat langsung. Preview berada di panel aplikasi existing, dengan akses akun Sys Administrator aktif. Tidak ada migrasi database aplikasi atau perubahan workflow Ticket Master, LTRO, maupun ATK.

Pembaruan terakhir: seluruh isian checklist tersedia langsung di tabel. Tombol/modal Detail dan keterangan jumlah pada nama kelompok dihapus sesuai review pengguna. Catatan revisi terdahulu di bawah disimpan sebagai riwayat keputusan.

## Cara membuka

Pada aplikasi lokal, buka grup sidebar Infrastruktur IT:

- Master Perangkat: `/panel/it-devices`
- Pengecekan Infrastruktur: `/panel/it-inspections`
- Rekap & Laporan: `/panel/it-infrastructure-reports`

Seluruh halaman diberi label `Mode review: data contoh`. Data contoh disimpan dalam sesi pengguna agar percobaan dapat dilanjutkan antarhalaman. Tidak ada pembacaan atau penulisan tabel operasional IT. Penggunaan sesi mengikuti mekanisme session aplikasi existing.

## Yang dapat dicoba

- Memilih 12 objek contoh, memfilter kategori, mengubah inventaris dan menyimpan contoh. Field Laptop/PC mencakup OS/Windows, kedua lisensi, dasar verifikasi, HDD/SSD berulang, RAM, nomor aset, serial, pemakai, lifecycle dan keterangan.
- Memilih ruang server: lokasi wajib, identitas aset/pemakai/lifecycle aset disembunyikan.
- Membuka seluruh 269 butir checklist sesuai 12 jenis objek. Pilih kelompok melalui dropdown atau tombol Sebelumnya/Berikutnya. Tabel menampilkan satu kelompok; pergantian kelompok mempertahankan hasil kelompok lain.
- Mengisi nilai/rincian dan catatan langsung pada tabel. Warning/Critical menampilkan temuan, tindakan dan penanggung jawab wajib di bawah baris terkait. N/A menampilkan alasan wajib pada baris tersebut. Petunjuk khusus setiap pemeriksaan selalu terlihat. Lisensi selain Belum Diverifikasi memerlukan dasar verifikasi.
- Menyimpan draft, mencoba penolakan finalisasi belum lengkap, atau menggunakan Isi skenario contoh untuk menyiapkan satu Warning dan butir lain Normal. Tombol tersebut hanya bagian dari simulasi review.
- Memfinalisasi contoh, melihat hasil terkunci, membuka riwayat dari master, dan membuat contoh pemeriksaan berikutnya.
- Melihat perbedaan jumlah aset, ruang, transaksi pemeriksaan, draft, kondisi final terbaru per objek, serta temuan lama yang masih terbuka. Contoh awal memiliki dua pemeriksaan pada laptop yang sama.
- Menyelesaikan tindak lanjut contoh tanpa mengubah record pemeriksaan atau hasil final; penyelesaian disimpan terpisah dari hasil.
- Mengatur ulang contoh melalui Rekap & Laporan. Data sesi lain dan data operasional tidak dihapus.

Preview belum menjadi modul operasional: nomor register/department/lokasi/petugas contoh tidak mengubah master existing; belum ada export atau integrasi ticket. Sebelum mengganti preview dengan Resource operasional, diperlukan review serta penerapan migration IT secara terpilih. Slug preview sengaja sama dengan tujuan menu yang disepakati; Page preview harus diganti ketika Resource operasional disiapkan agar route tidak ganda.

## File

File baru:

```text
app/Services/ItInfrastructure/ItInfrastructureReviewStore.php
app/Filament/Support/ItInfrastructureReviewPage.php
app/Filament/Pages/ItDeviceReview.php
app/Filament/Pages/ItInspectionReview.php
app/Filament/Pages/ItInfrastructureReportReview.php
resources/views/filament/components/it-review-notice.blade.php
resources/views/filament/components/it-device-history.blade.php
resources/views/filament/pages/it-device-review.blade.php
resources/views/filament/pages/it-inspection-review.blade.php
resources/views/filament/pages/it-infrastructure-report-review.blade.php
tests/Feature/ItInfrastructure/ItInfrastructureReviewTest.php
docs/IT-INFRASTRUCTURE-UI-REVIEW.md
output/it-infrastructure-review/master-perangkat.jpg
```

Perubahan provider panel hanya menambahkan grup navigasi Infrastruktur IT. Halaman memakai discovery Filament existing; tidak ada penambahan route manual, perubahan permission pengguna, atau perubahan tema global.

## Pengujian

- SQLite terisolasi: PASS, 53 tests, 803 assertions.
- PostgreSQL 18.4 terisolasi: PASS, 53 tests, 909 assertions. Database sementara dibersihkan; fingerprint schema public, daftar migration dan jumlah record pada sepuluh tabel utama sama sebelum/sesudah pengujian.
- Sepuluh pengujian preview mencakup akses administrator aktif, penolakan pengguna biasa, penyimpanan hanya pada sesi, inventaris ruang, checklist network berbeda, validasi draft/final/temuan/lisensi, hasil final terkunci, perhitungan rekap dan hasil final tetap sama setelah penyelesaian tindak lanjut.
- Pint, sintaks tujuh file PHP terkait preview/provider/test dan git diff --check: PASS.
- Tidak ada migration APCA yang dimuat. Suite existing lengkap tetap tidak dijalankan karena menggunakan RefreshDatabase dengan seluruh migration.

## Delivery gate anti-slop

Arah desain: halaman operasional internal untuk petugas IT, mengikuti bahasa visual Filament/Internal 9 existing. ENERGY 1 / RHYTHM 1 / MOTION 1. Font Inter dan mode terang diwarisi dari panel. Biru existing menandai tindakan utama; status memakai teks dan warna semantik. Section memisahkan identitas, inventaris, dan kelompok checklist; tabel digunakan untuk daftar dan riwayat yang perlu dibandingkan. Ikon server, clipboard dan laporan menjelaskan tiga tujuan navigasi. Tidak ada aset gambar baru selain screenshot bukti aplikasi.

- R-24/R-26 PASS: ketiga menu membuka halaman nyata; simpan contoh, draft, finalisasi, pengisian skenario, riwayat, penyelesaian dan reset memiliki handler yang diuji.
- R-27 PASS: filter kondisi tanpa hasil menampilkan petunjuk mengubah filter; loading memiliki teks status; finalisasi kosong menampilkan error yang terlihat.
- R-25 PASS untuk pasangan warna baru: teks slate/putih 10.35:1; tombol primer existing biru/putih 4.53:1; batas input slate/putih 4.76:1; Normal 6.81:1; Warning 6.62:1; Critical 7.60:1; label netral 9.45:1; tautan 7.04:1; fokus biru/putih 5.98:1. Dihitung dengan contrast-check.py project.
- R-32 PASS pada interaksi preview yang diperiksa: Tab dari pemilih objek menuju area tabel menghasilkan outline solid yang terlihat; input/select/button memiliki focus-visible, dan section dapat dibuka/tutup melalui tombol native Filament.
- R-03 PASS pada ukuran yang diperiksa: 390px, 768px dan viewport desktop existing tidak menambah overflow horizontal halaman. Form menumpuk pada ponsel. Tabel mempertahankan lebar baca di dalam area scroll lokal yang dapat difokuskan, bukan memecah kata menjadi kolom sangat sempit.
- R-35 PASS untuk alur review yang diuji di Chrome: menu Master -> form; simpan ruang -> notifikasi; laptop -> tambah media SSD/kapasitas/dasar lisensi -> simpan; Pengecekan -> finalisasi kosong ditolak; isi skenario -> simpan draft -> finalisasi -> hasil terkunci; Rekap -> selesaikan temuan lama -> count terbuka berkurang; filter network/Critical -> empty state; menu ponsel -> Master -> ruang tanpa serial; FortiGate -> buka kelompok -> VPN/policy tampil; reset contoh -> kembali ke data awal. Tidak ada error konsol pada tab preview saat pemeriksaan terakhir.
- R-17/R-36/R-38 PASS: seluruh nama/perhitungan contoh diberi label simulasi, tanpa klaim data operasional. Hasil final contoh dipertahankan terpisah dari penyelesaian tindak lanjut.
- R-34/R-37 PASS sesuai scope: tampilan mempertahankan mode terang dan pola Filament yang diminta pengguna. Tidak mengubah tema aplikasi atau menambahkan mode lain.
- C-1/C-3/R-31 PASS berdasarkan susunan source: kontrol dan section mengikuti identitas/inventaris/checklist/riwayat yang diminta; tidak ada dekorasi atau kontrol tanpa fungsi. Reflow, empty/error/loading serta pemeriksaan keyboard di atas menjadi bukti C-4 untuk preview yang diserahkan.

Pemeriksaan ini berlaku pada preview baru. Tidak merupakan audit aksesibilitas seluruh aplikasi, uji seluruh pasangan warna tema existing, atau pengujian perangkat mobile fisik.

## Revisi form ringkas sesuai Permintaan ATK

Riwayat revisi pertama; penggunaan modal Detail pada rancangan ini digantikan oleh revisi isian langsung di bawah.

Pengguna meminta form yang lebih sederhana dan lebih sedikit scroll. Arah desain tetap Filament/Internal 9, dengan referensi `AtkRequestResource::form()`: header beberapa kolom dan tabel isian ringkas. Anti-slop diterapkan selama pekerjaan sesuai pilihan pengguna. ENERGY 1 / RHYTHM 1 / MOTION 1 tetap dipakai; tidak ada aset visual atau tema baru.

- Master menampilkan pemilih kategori/perangkat dan tab Identitas, OS & lisensi (Laptop/PC), Pemakaian, serta Riwayat. Tabel daftar di atas form dihapus karena menduplikasi pemilih perangkat. HDD/SSD memakai repeater tabel compact seperti daftar barang ATK.
- Pengecekan menampilkan header empat kolom pada desktop, kemudian satu kelompok checklist dalam tabel Pemeriksaan/Hasil/Nilai/Detail. Jumlah butir diperiksa dan kondisi ditampilkan singkat. Petunjuk, catatan, temuan, tindak lanjut, penanggung jawab dan alasan N/A berada di modal Detail dua kolom pada desktop, satu kolom pada ponsel.
- Semua hasil tetap berada dalam state draft saat pindah kelompok. Simpan memvalidasi seluruh kelompok; bila ada kesalahan, kelompok terkait otomatis ditampilkan. Nilai numerik divalidasi di penyimpanan sesi karena inputnya sekarang berada di tabel.
- Hasil final dan Detail final tetap terkunci. Nama objek yang ditampilkan saat membuka pemeriksaan memakai snapshot pemeriksaan, termasuk bila nama pada master telah berubah.
- Rekap memisahkan Kondisi terakhir, Temuan dan Riwayat ke tab, sehingga hanya satu tabel tampil. Perhitungan aset unik, pemeriksaan dan temuan tidak berubah.
- Pemilih kategori master mengikuti kategori jenis perangkat setelah disimpan. Pengujian browser menggunakan contoh PC tambahan; draft laptop milik pengguna tidak diubah atau direset.

File revisi: ketiga Page preview, ketiga view halaman, komponen notice/style, service preview untuk validasi angka, pengujian preview dan dokumen ini. Komponen riwayat di atas merupakan file baru. Tidak ada perubahan tambahan pada provider, migration, model operasional, enum, seeder, Ticket Master, LTRO atau ATK untuk revisi tampilan ini.

### Delivery gate revisi

- Hard Gate PASS: tidak ada aset, navigasi baru, statistik operasional atau klaim buatan. Label mode review tetap terlihat. Menu tetap tiga submenu yang disetujui. Tidak ada perubahan tema global atau migration database aplikasi.
- Purpose-Gate PASS: tab memisahkan isian/riwayat agar tidak bertumpuk; tabel memudahkan perbandingan status per butir; modal menempatkan rincian dekat butir terkait; satu aksen biru mengikuti panel dan menandai tab/tindakan aktif. Tidak ada efek atau animasi tambahan.
- Liveliness PASS sesuai arah produk: fokus halaman adalah tabel checklist atau tab data yang dipilih. Tipografi, panel section dan aksen biru mempertahankan identitas formulir operasional Internal 9/ATK. Spasi memisahkan pemilih, header, tabel dan tindakan; dials tidak berubah.
- Craftsmanship PASS pada alur yang diuji: seluruh butir dipertahankan; pengisian Detail Warning, pergantian kelompok, simpan draft dan buka kembali draft dicoba di Chrome. Finalisasi kosong menampilkan error pada baris serta ringkasan. Tab inventaris/riwayat master dan Temuan/Riwayat rekap berpindah ke konten terkait.
- R-03/R-32 PASS pada ukuran 390px dan desktop 1536px yang diperiksa: tidak ada overflow horizontal halaman. Tabel dapat digeser dalam region lokal. Tab master disusun dua kolom pada ponsel. Tab keyboard dari Identitas menuju OS & lisensi memperlihatkan outline solid. Kontrol baru memakai elemen native dan label per butir; modal memakai mekanisme fokus Filament.
- R-25 PASS untuk tambahan revisi: teks putih/tab biru `#0068d9` 5.27:1; teks error `#991b1b`/putih 8.31:1, dihitung menggunakan checker project. Pasangan warna lain tetap memakai hasil pemeriksaan di atas. Label tab aktif diperbaiki agar tidak biru di atas biru.
- R-35 PASS untuk perbandingan tinggi: draft laptop yang sama, kelompok pertama, viewport 1536×770, tinggi dokumen sebelum revisi 2204px dan setelah revisi 1017px. Ini pengukuran satu kondisi form; kelompok dengan lebih banyak baris atau error dapat lebih panjang. Form ponsel tetap memerlukan scroll vertikal.

Bukti gambar revisi: `output/it-infrastructure-review/pengecekan-ringkas.jpg`. Log pengujian revisi berada pada folder yang sama: `compact-ui-tests.log`, `compact-ui-suite.log`, `compact-ui-postgres.log`.

Hasil akhir revisi: SQLite 59 tests / 962 assertions PASS; PostgreSQL 18.4 59 tests / 1080 assertions PASS; subset preview 16 tests / 272 assertions PASS. Database PostgreSQL sementara dibersihkan; fingerprint schema public, daftar migration dan jumlah record tabel utama sama sebelum/sesudah. Pengujian tambahan mencakup modal temuan, alasan N/A, nilai multi-baris, validasi angka, hasil antar kelompok tetap tersimpan, pemilihan kelompok error, penolakan perubahan Detail final, tab rekap dan konsistensi pemilih kategori master. Pint dan pemeriksaan sintaks PASS. Konsol browser tab hasil akhir tidak mencatat error pada pemeriksaan terakhir.

## Revisi terbaru: isian langsung di halaman

Sesuai review pengguna, tombol dan modal Detail dihapus. Nilai/rincian, termasuk input multi-baris, serta catatan langsung dapat diisi pada tabel kelompok aktif. Petunjuk pemeriksaan ditampilkan di bawah nama pemeriksaan. Warning/Critical menampilkan temuan, tindak lanjut dan penanggung jawab pada baris lanjutan; N/A menampilkan alasan di kolom catatan. Hasil final tetap menampilkan seluruh informasi terkait dalam keadaan terkunci. Tidak ada pengurangan checklist atau perubahan alur draft/finalisasi/tindak lanjut.

Revisi menyentuh `ItInspectionReview.php`, view pengecekan, komponen notice/style, service penyimpanan sesi, test preview dan dokumen ini. Handler modal beserta import yang tidak digunakan dihapus. Batas panjang isian yang semula divalidasi modal dipindahkan ke penyimpanan sesi agar tetap berlaku pada input langsung. Tidak ada migration atau perubahan Ticket Master, ATK, LTRO, permission, navigasi maupun tabel operasional.

Validasi: subset preview SQLite 17 tests / 278 assertions PASS; keseluruhan modul pada PostgreSQL 18.4 60 tests / 1088 assertions PASS. Database uji sementara dibersihkan dan fingerprint database utama tetap sama. Log: `inline-ui-tests.log` dan `inline-ui-postgres.log` pada folder output review. Test mencakup pengisian langsung, persistensi lintas kelompok/simpan/buka kembali, N/A wajib, rincian multi-baris, batas panjang, serta penolakan perubahan hasil final. Sintaks, Pint dan `git diff --check` PASS.

Delivery gate revisi ini:

- Hard Gate PASS: tidak ada aset, menu, klaim atau fitur baru; mode review tetap ditandai. Input menggunakan kontrol native berlabel dan mekanisme loading/error yang sama. Semua kondisi checklist dipertahankan.
- Purpose-Gate dan Liveliness PASS: ENERGY 1 / RHYTHM 1 / MOTION 1, Filament/Internal 9 dan pola tabel ATK tetap menjadi arah desain. Kolom isian memudahkan pengisian langsung; baris tambahan hanya muncul sesuai hasil yang memerlukan keterangan. Tidak ada dekorasi/animasi tambahan. Warna teks, border, fokus dan status tidak berubah dari pasangan yang telah diperiksa di atas.
- Craftsmanship PASS pada alur yang diperiksa: Chrome menampilkan petunjuk serta catatan tanpa tombol Detail; pemilihan Warning membuka ketiga isian wajib langsung di tabel; pengisian dan pindah kelompok mempertahankan nilai. Nilai multi-baris dan alasan N/A dapat diisi langsung. Pengujian penyimpanan/finalisasi tetap lulus.
- Responsive PASS pada 390px dan desktop 1536px: halaman tidak melebar horizontal. Tabel memakai area scroll lokal, isian tindak lanjut menumpuk satu kolom pada ponsel. Lebar minimum pemilih hasil menjaga label status terbaca. Ponsel tetap memerlukan scroll tabel serta scroll vertikal sesuai jumlah isian.
- Keyboard PASS pada alur yang diperiksa: Tab dari Catatan menuju Temuan mengaktifkan input yang sesuai dengan outline solid. Hasil pengamatan DOM pada desktop menunjukkan lebar halaman 1521px di viewport 1536px, dan pada ponsel 375px di viewport 390px.

Bukti tampilan terbaru: `output/it-infrastructure-review/pengecekan-langsung.jpg`. Contoh isian browser untuk pemeriksaan ini tidak disimpan ke register atau menimpa draft pengguna.
