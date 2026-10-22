# Status verifikasi SCM

22 Oktober 2026. Implementasi dan data demo berada di lingkungan lokal. Tidak ada push atau deployment server.

## Hasil yang sudah diverifikasi

| Pemeriksaan | Hasil aktual |
| --- | --- |
| Seluruh PHPUnit, SQLite memory | Pengujian ulang setelah QA browser: 532 tes lulus, 5.481 assertion. JUnit: `output/scm-browser-20261022/phpunit.xml` |
| Tes SCM setelah perbaikan nilai kosong | 15 tes lulus, 144 assertion; termasuk regresi formulir Livewire dengan tanggal opsional kosong |
| SCM, katalog akses, dan izin tambahan | 26 tes lulus, 330 assertion. Log: `tmp/scm-implementation/final-focused.txt` |
| Dua proses PostgreSQL nyata | Pengujian ulang: 1 tes lulus, 9 assertion; nomor PR unik, tepat satu finalisasi CBE berhasil, dan tanggal kosong dinormalisasi. Runner juga memakai tanggal kandidat/masa berlaku quotation kosong |
| Perlindungan database utama | Database uji PostgreSQL terpisah dibersihkan; jumlah record tabel utama sama sebelum/sesudah pengujian |
| Migrasi lokal | 15 tabel SCM ditambahkan. Jumlah record tabel lama tetap sama sebelum pengisian demo. Backup PostgreSQL dibuat di direktori temp pengguna; lokasi tercatat di `tmp/scm-implementation/local-preparation.json` |
| Data demo | Satu PR dan satu SR fiktif sampai Selesai; tiga vendor, item jamak, quotation, CBE, NIB demo privat, dan audit. Seeder dijalankan ulang secara idempotent |
| Render dengan data lokal | Pemohon, antrean SCM, vendor/dokumen, dan pengaturan merespons HTTP 200 melalui kernel Laravel dan transaksi PostgreSQL read-only |
| Build frontend | `npm run build` lulus. Log: `tmp/scm-implementation/build.txt` |
| Format seluruh kode | `php vendor/bin/pint --test` lulus. Log: `tmp/scm-implementation/pint-test.txt` |
| Whitespace | `git diff --check` lulus |
| Kontras CSS SCM | Semua delapan pasangan yang diperiksa lulus WCAG AA; rasio 4,83:1 sampai 17,74:1, termasuk warna teks, tautan, dan batas kontrol pada tema terang/gelap |
| QA browser terhubung | PR dan SR baru berhasil sampai Selesai melalui UI. PR diuji di Edge, SR di Chrome. Riwayat, unduhan privat, penolakan akses 403, filter, master vendor, kategori/jenis dokumen/persyaratan juga diperiksa |
| Reflow dan target sentuh | CBE final diperiksa pada 320/375/640/768/1024/1280/1440 px; form pada enam lebar 320–1280 px; vendor pada 320/375/768/1280 px. Tidak ada overflow workspace pada pemeriksaan akhir. Tombol/form yang diukur minimal 44 px |

## Hasil QA browser

Browser sudah terhubung pada pengujian lanjutan 22 Oktober 2026. Pengujian memakai Computer Use dengan ekstensi browser, tanpa Playwright lokal. Laporan rinci, daftar perbaikan, batas pemeriksaan, dan tautan bukti tersedia di [laporan QA browser](../output/scm-browser-20261022/LAPORAN.md).

Empat perbaikan hasil browser: normalisasi nilai kosong sebelum validasi SCM agar tanggal/ID opsional tidak dikirim sebagai string kosong ke PostgreSQL; registrasi stylesheet SCM pada panel Filament; pemecahan nomor panjang serta penyusutan anak grid/flex pada layar 320 px; dan tinggi tombol utama Filament minimal 44 px di dalam SCM. Kontrol akses dan aturan proses dipertahankan.

## Pemeriksaan yang masih tertunda

Pemilihan file lewat ekstensi masih ditolak pada Edge dan kedua profil Chrome, termasuk setelah pengguna melaporkan izin file telah diaktifkan, penyambungan ulang, dan pemuatan ulang halaman. Percobaan memeriksa halaman pengaturan ekstensi diblokir kebijakan URL browser. Karena itu unggah baru melalui browser, penolakan PDF palsu melalui browser, dan versi unggahan baru dengan nomor berawalan nol belum ditandai lulus. Backend unggahan tetap tercakup tes otomatis.

Halaman cetak CBE sudah dibuka dan datanya cocok dengan snapshot, tetapi penyimpanan PDF melalui dialog cetak sistem belum diverifikasi. Zoom browser 200% dan keyboard virtual perangkat nyata belum diverifikasi; shortcut zoom yang dikirim alat tidak mengubah viewport atau rasio piksel. Fokus keyboard, Tab, Enter, rincian audit, serta dialog konfirmasi sudah diperiksa pada jalur yang dicatat, tetapi belum menjadi audit keyboard lengkap untuk setiap kontrol. Panel memang menggunakan `darkMode(false)` dan tidak memiliki toggle tema, sehingga pergantian tema tidak ditambahkan.

Gate UI akhir belum dinyatakan lulus menyeluruh. Modul belum dinyatakan siap rilis dan tidak ada deployment.

## Arah UI dan alasan implementasi

Pengguna memilih anti-slop selama implementasi. Arah mengikuti UI IK WorkDesk yang sudah ada: ENERGY 1 / RHYTHM 1 / MOTION 1. Tipografi, shell Filament, tema, dan komponen tombol dipakai ulang untuk konsistensi modul internal. Tidak ada aset visual atau ikon dekoratif baru.

Tabel permintaan mengutamakan nomor/judul, departemen, dan tahap karena pengguna perlu menentukan tindak lanjut. Detail dipisah menjadi Permintaan, Vendor & penawaran, CBE, Legalitas, Riwayat agar data komersial hanya dirender untuk SCM yang berwenang. Form menggunakan satu kolom pada layar sempit, dua kolom mulai 640px, tiga kolom mulai 1200px. Tabel perbandingan diberi region scroll berlabel dan fokus keyboard. Status selalu memakai teks, tidak hanya warna. Warna aksen dipakai untuk tindakan/fokus/pesan, tanpa animasi dekoratif. Ringkasan mengambil jumlah nyata sesuai hak akses, bukan angka contoh.

Label dan validasi SCM menggunakan bahasa Indonesia. Loading memiliki teks status, kesalahan memiliki daftar dengan `role=alert`, dan keadaan kosong menjelaskan tindakan berikutnya. Data demo diberi nama DEMO/fiktif; tidak ada klaim legalitas asli atau verifikasi pemerintah. Penyimpanan dokumen privat, kontrol status, dan pembatasan akses diuji melalui backend/HTTP.

Langkah berikutnya adalah menyelesaikan pemeriksaan yang masih tertunda di atas. Cara menjalankan serta akun demo ada di [SCM.md](SCM.md).
