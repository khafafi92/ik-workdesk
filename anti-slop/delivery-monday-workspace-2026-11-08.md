# Tampilan workspace IK WorkDesk

8 November 2026. Anti-slop mode During, mengikuti permintaan pengguna untuk menerapkan referensi monday.com yang sebelumnya diperiksa dengan REA.

## Arah desain

Workspace operasional untuk karyawan Internal 9, dengan panel putih, kanvas terang, tipografi yang mudah dipindai, status berwarna, dan transisi pendek. ENERGY 2 / RHYTHM 2 / MOTION 2.

- Warna biru melanjutkan aksen aplikasi untuk tindakan dan navigasi aktif; hijau, amber, dan merah menyampaikan status dengan label tertulis.
- Logo Internal 9 tetap memakai aset yang ada. Tipografi memakai Inter yang tersedia pada panel, dengan fallback font sistem untuk layout Blade.
- Total Service Desk dan Work Logs menjadi angka utama; rincian status tetap berasal dari query yang sama dan tidak diubah menjadi grafik dengan kategori tumpang tindih.
- Ringkasan modul mengikuti izin dan pilihan dashboard yang sudah ada. Nilai nol dan data yang belum tersedia memakai warna netral.
- Jarak 16 sampai 24 piksel memisahkan kelompok kerja. Ringkasan utama, modul, pengingat, dan tabel memakai komposisi yang sesuai isinya.
- Radius 8 piksel untuk kontrol, 12 piksel untuk panel, dan 16 piksel untuk modal membedakan jenis permukaan.
- Bayangan dipakai untuk modal dan dropdown yang menumpuk di atas konten; kartu data tetap rata.
- Transisi kontrol 160 ms memberi umpan balik; sidebar dan modal memakai 240 ms untuk menjelaskan perubahan ruang. Animasi masuk hanya pada judul, sehingga tidak mengubah containing block modal yang diposisikan fixed.
- Preferensi reduced motion menonaktifkan transisi dan animasi. Tidak ada video, font eksternal, atau dependensi animasi baru.

## Perubahan

- Stylesheet bersama: public/css/workdesk-workspace.css, dimuat setelah tema yang ada pada panel Filament dan layout admin Blade.
- Dashboard: definition list untuk metrik, label region, tanggal dengan datetime, hierarki angka, dan status nol netral.
- Sidebar: kontrol tetap memakai store Filament; tombol menyediakan aria-controls dan aria-expanded. Escape menutup drawer pada layar kecil dan mengembalikan fokus ke tombol pembuka.
- Layout admin pada ponsel menyediakan tautan Workspace menuju route panel yang sudah ada.
- Controller, query, angka statistik, service bisnis, route, dan izin pengguna tidak diubah oleh pekerjaan ini.
- Perubahan Inventori dan Pengadaan pada AdminPanelProvider sudah ada sebelum pekerjaan ini; penambahan stylesheet mempertahankannya.

## Pemeriksaan yang dijalankan

- Build frontend terakhir lulus. Bukti: ../output/workspace-style-20261108/build.txt.
- Seluruh 13 tes dashboard lulus, 132 assertion. Mencakup akses antar pengguna/departemen, URL rincian dan filter, kondisi kosong, pilihan bagian dashboard, dan metrik modul. Bukti: ../output/workspace-style-20261108/dashboard-tests.txt.
- Pint pada AdminPanelProvider, git diff --check, lint PHP, dan parse stylesheet melalui PostCSS lulus.
- Delapan skenario REA lengkap dipaketkan dengan tangkapan layar dan langkahnya di ../output/workspace-style-20261108/browser-checks.json.
- Browser QA memakai SQLite tersendiri di tmp/workspace-style-20261108/qa.sqlite, akun dan catatan berlabel QA, mail array, serta pencegahan request HTTP keluar dari server QA.
- Lebar yang diperiksa secara visual: 320, 390, 768, dan 1440 piksel. Modal pada 320 piksel menampilkan tombol Simpan tampilan dan Cancel tanpa clipping.
- Reduced motion diperiksa pada viewport tablet dan kondisi kosong di ponsel.
- Tidak ada console error atau page error pada delapan skenario lengkap yang dipaketkan.

Tes tambahan yang dijalankan bersama katalog navigasi menghasilkan 21 lulus dari 22 tes. Satu kegagalan berasal dari MenuAccessCatalogTest: menu Inventori belum ditemukan dalam pilihan checklist akses. Kelas Inventori dan service katalog akses termasuk perubahan yang sudah ada; perbaikannya berada di luar pekerjaan presentasi ini.

Pemeriksaan browser zoom 200% belum dapat dikonfirmasi: shortcut zoom dikirim pada Chromium headless, tetapi ukuran tampilan tidak berubah. Tangkapan desktop tersebut dicatat sebagai ukuran biasa. Keyboard layar ponsel fisik dan seluruh kontrol pada seluruh modul aplikasi tidak diuji dalam pekerjaan ini.

## Bukti interaksi

- Tombol Edit Dashboard dibuka dengan pointer dan Enter; form pengaturan tampil.
- Escape menutup form; form dapat dibuka kembali.
- Simpan tampilan menutup form dan menghasilkan notifikasi Tampilan dashboard disimpan pada database QA.
- Collapse dan Expand memakai store sidebar yang sama dan mengubah kelas sidebar.
- Drawer pada 320 piksel dibuka dengan tombol menu, ditutup dengan Escape, dan fokus kembali terlihat pada tombol menu.
- Tautan Workspace di dashboard admin dapat diaktifkan dengan Enter menuju panel.
- Tautan View all tickets juga dibuka dengan pointer dan menampilkan tabel Service Desk.
- Semua 21 tujuan unik pada dashboard diaktifkan dengan Enter, lalu kembali melalui logo topbar:
  - Daftar Service Desk dan Work Logs.
  - Ringkasan ATK, laporan LTRO, dan Attendance Report.
  - Daftar pengingat, rincian tiga pengingat QA, dan filter Today, Upcoming, Overdue.
  - Lima rincian tiket QA dan empat rincian Work Logs QA.
- Bukti navigasi lengkap: ev_108d4efd9de50c47787fe74c32886f4faa7697dbb79acf8d43e9ee6d473804e2.
- Bukti kontrol dashboard: ev_cf74eb86f0ba0c4e97df8c9863b91b455750b8e259dad80951da5d12c6cbe01d.
- Bukti Escape dan fokus menu ponsel: ev_19293fc8e689fb2437a24ca84b5a12794a4bd6c386e396b71b5d07e3b1aadd41.

Beberapa percobaan awal REA mengalami timeout navigasi, pemilihan modal yang ambigu, dan satu timeout cleanup. Skenario diperbaiki dengan selector yang spesifik serta menunggu hasil navigasi; catatan di atas berasal dari skenario lengkap setelah perbaikan. Tidak ada proses Chrome QA tertinggal pada pemeriksaan proses.

## Kontras

Checker lokal antislop-human menghasilkan:

| Pemakaian | Pasangan | Rasio | Hasil |
|---|---|---:|---|
| Teks utama | #292d3e pada putih | 13,64:1 | PASS |
| Teks pendukung | #5b6070 pada #f6f7fb | 5,85:1 | PASS |
| Tombol utama | Putih pada #005fcc | 5,98:1 | PASS |
| Pengingat aktif | #005fcc pada #e0ecff | 5,02:1 | PASS |
| Pengingat kosong | #5b6070 pada #e8ebf2 | 5,25:1 | PASS |
| Pengingat terlambat | #b4233b pada #ffe5eb | 5,44:1 | PASS |
| Status selesai | #006b46 pada putih | 6,58:1 | PASS |
| Status proses | #8a5200 pada putih | 6,39:1 | PASS |
| Batas kontrol | #858d9c bersebelahan putih | 3,34:1 | PASS untuk non-teks |

## Delivery Gate untuk perubahan ini

- R-02 PASS: tidak ada em dash dalam copy baru.
- R-03 PASS pada viewport yang diperiksa: grid reflow, teks panjang membungkus, kontrol utama 44 piksel, modal ponsel dan header tidak terpotong.
- R-17 PASS: statistik tetap berasal dari data aplikasi; screenshot memakai database QA terpisah.
- R-18 PASS: tidak ada testimonial.
- R-23 PASS: referensi dan penerapan disetujui pengguna; tidak ada aset visual baru.
- R-24 PASS: tautan baru Workspace memakai route panel yang ada; 21 tujuan dashboard dibuka dalam browser.
- R-25 PASS untuk pasangan baru: rasio teks dan batas kontrol tercatat di atas.
- R-26 PASS: kontrol pengaturan, simpan, sidebar, dan tautan berjalan pada QA.
- R-27 PASS untuk cakupan perubahan: pesan kosong dipertahankan; form tetap memakai validasi, loading, dan notifikasi Livewire/Filament. Tidak ada mekanisme pengambilan data baru.
- R-28 PASS: tidak ada FAQ.
- R-32 PASS pada interaksi yang diperiksa: Enter, Escape, dan fokus terlihat; definition list serta region berlabel mempertahankan makna data.
- R-33 PASS: implementasi ditulis langsung di CSS, Blade, dan hook provider; script QA hanya menyiapkan fixture dan mengekspor bukti.
- R-34 PASS: aplikasi tetap memakai tema terang yang sudah ditetapkan; tidak ada toggle tema baru.
- R-35 PASS untuk pemeriksaan implementasi: build, tes, langkah browser, dan console tercatat; batas zoom serta perangkat fisik dinyatakan di atas.
- R-36 PASS: tidak ada klaim produk atau performa baru.
- R-37 PASS: arahan monday.com dari pengguna dan dials ditetapkan sebelum implementasi.
- R-38 PASS: konten aplikasi dan navigasi dipertahankan; fixture jelas berlabel QA.
- R-01 PASS: warna melayani aksi dan status; tidak ada gradient atau glow baru.
- R-04 PASS: ikon navigasi dan pengingat yang ada tetap merepresentasikan tindakan atau modul.
- R-06 PASS: font lokal/sistem menjaga konsistensi dan keterbacaan tabel kerja.
- R-07 PASS: kanvas polos tanpa pola latar.
- R-08 PASS: tidak ada panah dekoratif baru.
- R-09 PASS: angka pengingat menyatakan jumlah nyata dan selalu berlabel.
- R-10 PASS: panel solid tanpa blur baru.
- R-12 PASS: elevasi hanya untuk modal/dropdown; kartu data tanpa bayangan.
- R-13 PASS: tidak ada glow.
- R-14 PASS: total, metrik modul, lane pengingat, dan tabel memakai struktur berbeda sesuai data.
- R-19 PASS: tujuan dan durasi gerak dinyatakan; reduced motion didukung.
- R-22 PASS: tidak ada ilustrasi baru.
- Liveliness PASS: dials 2/2/2, fokus Work Summary dan total pekerjaan, whitespace antarkelompok, aksen biru untuk aksi, serta logo dan istilah operasional Internal 9.
- C-1 PASS: alasan warna, tata letak, font, jarak, dan gerak tercatat.
- C-2 PASS: interaksi yang terdampak diperiksa; kontrol bisnis tetap ditangani komponen asal.
- C-3 PASS: seluruh bagian dashboard berasal dari modul yang sudah ada.
- C-4 PASS dalam cakupan viewport/state yang diuji; batas tambahan dicatat di atas.
- C-5 PASS: tidak ada data operasional yang direkayasa untuk aplikasi nyata.
- R-05 PASS: tata letak mengikuti ringkasan, pengingat, dan aktivitas kerja yang sebenarnya.
- R-11 PASS: radius dibedakan untuk kontrol, panel, dan modal.
- R-15 PASS: tindakan tetap spesifik, termasuk Buka modul dan Simpan tampilan.
- R-16 PASS: tidak ada copy pemasaran baru.
- R-20 PASS: identitas Internal 9 dan isi operasional dipertahankan.
- R-21 PASS: tema terang sesuai arah workspace yang disetujui.
- R-29 PASS: satu aksen tindakan, netral untuk permukaan, dan warna semantik berlabel.
- R-30 PASS: referensi monday.com secara eksplisit diminta pengguna.
- R-31 PASS: keputusan utama memiliki alasan pada bagian arah desain.

Penerapan dilakukan pada workspace lokal. Tidak ada deployment ke server lain, migrasi database aplikasi nyata, atau perubahan izin akun nyata.

## Revisi tipografi sesuai penanda pengguna

- Penanda 1: teks menu sidebar dikurangi 1 piksel, dari 14 menjadi 13 piksel; label grup 12 menjadi 11 piksel. Bobot menu normal 400 dan menu aktif 500. Label Collapse menjadi 12 piksel dengan bobot 400.
- Penanda 2: greeting dan tanggal dikurangi 1 piksel; judul Work Summary dikurangi 1 piksel pada rentang fluid, dengan batas desktop 36 menjadi 35 piksel dan bobot 650 menjadi 500.
- Penanda 3: angka total Service Desk dan Work Logs dikurangi dari 44 menjadi 32 piksel, dengan bobot 500. Metrik rincian tetap memakai ukuran sebelumnya.
- Pemeriksaan PASS: parse CSS dan git diff --check.
- Pemeriksaan visual PASS: viewport 1440 dan 320 piksel dengan REA, tanpa console error maupun page error; teks membungkus dan kontrol tetap utuh.
- Kontras, target sentuh, tujuan tautan, data, dan animasi tidak berubah.
- Bukti desktop: ev_5162d02f3f5a2a7c22acd7e65c2828259b06ee077a8c0cd5ce46c99535f2b7e0.
- Bukti ponsel: ev_5617aa0472dc69b7c7c4a058ba76b5f7fa893aa4e6d2756420ca45d2d5d02c86.
