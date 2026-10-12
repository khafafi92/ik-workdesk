# Tanya Aku: pemeriksaan penyedia AI

Mode During dipilih pengguna. Lingkup: menambahkan konfigurasi dan pilihan OpenAI, DeepSeek, Claude, serta Gemini pada halaman Tanya Aku yang sudah ada.

## Arah dan alasan desain

Perubahan mengikuti form, warna, tipografi, navigasi Collaboration, dan tampilan terang IK WorkDesk yang sudah ada. Tidak ada desain ulang shell aplikasi. ENERGY 1 / RHYTHM 2 / MOTION 1.

- Pilihan penyedia berada sebelum form agar admin tahu konfigurasi yang sedang diedit.
- Satu form berganti penyedia, sehingga API key dan anggaran tiap penyedia tidak tercampur.
- Pilihan model berada sebelum percakapan agar pengguna mengetahui tujuan pengiriman pertanyaan.
- Label ID model pada jawaban berasal dari data tersimpan, untuk menunjukkan model yang menjawab.
- Warna biru `#005fbd` menandai aksi simpan/kirim dan fokus, sesuai kontrol Tanya Aku yang sudah ada.
- Inter mengikuti font panel yang telah ditetapkan; ukuran dan jarak mengikuti field WorkDesk.
- Susunan konfigurasi dan ringkasan token memisahkan tindakan dari pemantauan. Percakapan menjadi fokus halaman pengguna.
- Gerakan hanya berupa perubahan keadaan kontrol dan teks loading; tidak menambahkan animasi dekoratif.

## Bukti

Browser Chromium memakai SQLite, akun QA, key dummy, dan respons API simulasi yang terpisah dari database operasional. Tidak ada panggilan API berbayar. [Rekaman browser](evidence/tanya-aku-providers/browser-checks.json) memuat tindakan, ukuran viewport, warna computed, dan daftar kesalahan JavaScript kosong.

[Master desktop](evidence/tanya-aku-providers/settings-desktop.png), [Master ponsel](evidence/tanya-aku-providers/settings-mobile.png), [chat desktop](evidence/tanya-aku-providers/chat-desktop.png), [chat ponsel](evidence/tanya-aku-providers/chat-mobile.png), [akun pengguna biasa](evidence/tanya-aku-providers/chat-user-mobile.png).

Kontras dihitung dengan `.agents/skills/antislop-human/contrast-check.py` berdasarkan warna computed browser: body `#323338` pada putih 12.60:1; bantuan `#555866` pada putih 7.06:1; placeholder `#64748b` pada putih 4.76:1; teks tombol putih pada `#005fbd` 6.23:1; error `#a12639` pada putih 7.36:1; batas input `#858b99` pada putih 3.41:1 untuk non-text. Tombol loading juga tetap putih pada biru dengan opacity 1, termasuk fieldset saat menyimpan.

## Delivery Gate

| Pemeriksaan | Hasil dan bukti |
| --- | --- |
| R-02 | PASS: copy perubahan dan laporan memakai kalimat Indonesia langsung, tanpa em dash. |
| R-03 | PASS: kedua halaman tidak overflow horizontal pada viewport 320, 390, 768, 1024, 1440 px. Select minimal 46 px dan tombol minimal 45 px. |
| R-17 | PASS: token berasal dari data usage tersimpan; angka pada screenshot merupakan fixture QA yang dinyatakan di atas. |
| R-18 | PASS: tidak menambahkan testimonial atau profil pelanggan. |
| R-23 | PASS: menggunakan logo, font, dan navigasi yang sudah ada; tidak membuat aset baru untuk produk. |
| R-24 | PASS: tautan Buka Master Tanya Aku benar-benar membuka halaman pengaturan; pengguna biasa mendapat 403. |
| R-25 | PASS: kontras teks dan batas kontrol dihitung; angka tercantum di atas. |
| R-26 | PASS: dropdown, field model/key/anggaran, toggle aktif/default, simpan, dan kirim menjalankan fungsi nyata dalam click-through. |
| R-27 | PASS: riwayat kosong, semua penyedia nonaktif, loading pergantian penyedia/pengiriman/penyimpanan, serta error key tanpa jawaban palsu diuji. |
| R-28 | PASS: tidak menambahkan FAQ. |
| R-32 | PASS: pilihan penyedia dapat diubah dengan keyboard; Tab mencapai field berikutnya dan fokus outline biru 3 px terlihat pada form serta log chat. |
| R-33 | PASS: UI ditulis langsung dengan apply_patch di Blade dan CSS; tidak memakai skrip penggantian source. |
| R-34 | PASS: mengikuti satu tema terang yang sudah dikonfigurasi panel; tidak menambah tema baru. |
| R-35 | PASS: build frontend selesai; browser menjalankan seluruh kontrol yang diubah; tidak ada pageerror. Pengujian API memakai simulasi, bukan key asli. |
| R-36 | PASS: klaim enkripsi, akses admin, serta pemisahan riwayat/key didukung pengujian. Tidak menambahkan klaim pemasaran. |
| R-37 | PASS: ekstensi mengikuti desain panel yang ada sesuai batas lingkup AGENTS.md; Design Read dan dials telah dinyatakan sebelum UI dibuat. |
| R-38 | PASS: label model berasal dari konfigurasi/metadata pesan. Key dan akun dummy hanya dipakai pada lingkungan QA. |
| R-01 | PASS: warna aksi/fokus mengikuti Tanya Aku; tidak menambahkan gradient atau glow. |
| R-04 | PASS: tidak menambahkan ikon. |
| R-06 | PASS: memakai tipografi panel untuk konsistensi form operasional. |
| R-07 | PASS: tidak menambahkan background dekoratif. |
| R-08 | PASS: tidak menambahkan panah dekoratif. |
| R-09 | PASS: tidak menambahkan badge. |
| R-10 | PASS: tidak menambahkan glassmorphism. |
| R-12 | PASS: tidak menambahkan shadow. |
| R-13 | PASS: tidak menambahkan glow. |
| R-14 | PASS: memakai satu form penyedia; tidak membuat feature cards. |
| R-19 | PASS: MOTION 1; perubahan keadaan kontrol memberi umpan balik permintaan tanpa animasi berulang. |
| R-22 | PASS: tidak menambahkan ilustrasi. |
| Liveliness | PASS: dials eksplisit; aksi simpan/kirim sebagai fokus; jarak memisahkan konfigurasi, ringkasan, pilihan model, dan percakapan; aksen dan tipografi mengikuti WorkDesk. |
| C-1 / R-31 | PASS: alasan setiap keputusan utama ditulis di atas. |
| C-2 | PASS: daftar click-through di bawah merekam tindakan kontrol. |
| C-3 / R-05 | PASS: hanya konfigurasi provider, pemakaian token, dan percakapan sesuai lingkup tugas. |
| C-4 | PASS: state kosong/loading/error, ukuran desktop/tablet/ponsel, serta keyboard diuji. Reflow pada viewport 720 x 500 juga diperiksa sebagai ukuran setara zoom 200% desktop; keyboard virtual perangkat fisik tidak digunakan. |
| C-5 | PASS: tidak ada klaim tanpa data; batas pengujian API asli dinyatakan. |
| R-11 | PASS: radius mengikuti field, tombol, dan panel Tanya Aku yang ada. |
| R-15 / R-16 | PASS: aksi bernama Simpan pengaturan dan Kirim pertanyaan; copy menyebut penyedia, key, model, serta batas internal. |
| R-20 / R-21 | PASS: mengikuti identitas dan konfigurasi tema terang panel operasional yang sudah ada. |
| R-29 / R-30 | PASS: satu aksen biru dengan warna netral; tidak meniru produk lain. |

## Click-through

- Penyedia DeepSeek: form dan ringkasan token berubah, field key kosong.
- Isi ID model dan batas token, aktifkan tanpa key: validasi key muncul.
- Isi key DeepSeek, simpan: status key tersimpan, field key kembali kosong.
- Pilih Claude, isi model/key/batas, aktifkan, pilih default, simpan: berhasil.
- Buka chat: Claude terpilih sebagai default.
- Pilih Claude, DeepSeek, dan OpenAI lalu kirim pertanyaan: jawaban simulasi dan metadata model tampil.
- Kirim pertanyaan yang memicu kegagalan API simulasi: error tampil, draft pertanyaan bertahan, tidak menyimpan jawaban palsu.
- Pilih penyedia melalui keyboard, lalu Tab: konfigurasi berganti, fokus field terlihat.
- Pengiriman diperlambat melalui intercept browser: teks Menghubungi tampil dan pilihan penyedia terkunci.
- Nonaktifkan seluruh penyedia melalui Master: chat menampilkan Tanya Aku belum tersedia.
- Klik Buka Master Tanya Aku: membuka form yang benar.
- Login pengguna biasa: Master menolak akses 403; chat hanya memuat model aktif, tanpa key dan tanpa riwayat akun admin.
- Ukuran 320, 390, 768, 1024, 1440 px: kedua halaman reflow tanpa horizontal overflow; screenshot desktop dan ponsel diperiksa secara visual.

## Validasi aplikasi

- `php artisan test --compact --filter=AiAssistant`: 24 tests, 121 assertions, seluruhnya lulus.
- `npm run build`: lulus.
- Pint pada seluruh file PHP fitur dan `git diff --check`: lulus pada pemeriksaan akhir.
- Migrasi tambahan: simulasi SQL PostgreSQL dan penerapan PostgreSQL lokal berhasil.
- Seluruh suite: 286 tests, 282 lulus, 4 gagal. Tiga kegagalan akses Daily Activity dan satu kegagalan DepartmentRoleHierarchy direproduksi tanpa perubahan fitur pada worktree HEAD `ea9dc09`: 8 tests, 4 lulus, empat kegagalan yang sama. Perubahan di luar Tanya Aku tidak dilakukan.

API DeepSeek/Claude/Gemini dengan key asli belum diuji. Administrator perlu mengisi key serta ID model yang tersedia pada akun masing-masing.

Server QA sudah dihentikan. Pembersihan `tmp/qa-ai-providers` dan worktree `tmp/qa-ai-baseline` ditolak pemeriksaan otomatis dengan alasan yang tersedia: `blocked by policy`. Kedua folder sementara tetap ada; penghapusan tidak diulang melalui cara lain.

## Pemeriksaan tambahan Gemini

Gemini ditambahkan melalui enum dan format API Google tanpa perubahan Blade, CSS, atau arah desain. Model baru menggunakan kontrol pengaturan dan chat yang sama. API key dikirim melalui header `x-goog-api-key`; endpoint tetap pada domain Google. Isi thinking tidak ditampilkan, tetapi token thinking ikut dihitung sebagai output. Token cache tidak dihitung dua kali.

[Rekaman Gemini](evidence/tanya-aku-providers/gemini-browser-checks.json), [Master desktop](evidence/tanya-aku-providers/gemini-settings-desktop.png), [Master ponsel](evidence/tanya-aku-providers/gemini-settings-mobile.png), [chat desktop](evidence/tanya-aku-providers/gemini-chat-desktop.png), [chat ponsel](evidence/tanya-aku-providers/gemini-chat-mobile.png), [chat pengguna](evidence/tanya-aku-providers/gemini-user-mobile.png). Semua memakai SQLite terpisah dan API simulasi dengan key dummy. Sidebar ponsel ditutup dan scroll dikembalikan ke atas sebelum mengambil screenshot akhir.

| Pemeriksaan tambahan | Hasil dan bukti |
| --- | --- |
| R-03 / R-32 / C-4 | PASS: kedua halaman tidak overflow pada 320, 390, 768, 1024, 1440 px; select 46.375 px; pilihan provider melalui keyboard berfungsi. Screenshot desktop dan ponsel diperiksa secara visual. |
| R-24 / R-26 / C-2 | PASS: pilih Gemini, isi model/key/batas, aktifkan, pilih default, simpan; chat membuka Gemini dan pengguna biasa dapat mengirim pertanyaan. Master tetap 403 bagi pengguna biasa. |
| R-27 | PASS: key awal kosong, key tersimpan tidak ditampilkan, respons error menjadi pesan lokal dan draft bertahan; respons blocked/tanpa usage/hanya thinking ditolak dalam test. |
| R-17 / R-36 / R-38 | PASS: angka screenshot berasal dari fixture; token input/output/thinking dan pemisahan riwayat/key diuji. API asli belum dipanggil. |
| R-25 / R-33 / R-35 | PASS: warna dan kontrol tidak berubah sehingga bukti kontras di atas tetap berlaku; perubahan menggunakan apply_patch; tidak ada pageerror browser. |
| R-02 / R-37 / C-1 / C-3 / C-5 | PASS: copy menyebut Gemini dan langkah konfigurasi; tetap mengikuti form WorkDesk; biaya dan key mengacu dokumentasi resmi Google; batas pengujian dinyatakan. |

Validasi terbaru: 30 tests AI, 153 assertions, seluruhnya lulus. Pint pada enum, service, dan test provider serta `git diff --check` lulus. Tidak ada perubahan frontend yang memerlukan build baru atau migrasi baru untuk Gemini. Hasil suite penuh dan reproduksi baseline sebelumnya tetap tercatat di atas; suite penuh tidak diulang untuk tambahan ini. Server QA dihentikan setelah pemeriksaan Gemini.
