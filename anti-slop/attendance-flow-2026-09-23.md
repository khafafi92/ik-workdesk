# Audit alur Attendance Report, 23 September 2026

Lingkup: penyederhanaan pintu masuk Attendance Report, formulir upload, status proses, dan tautan kembali. Audit dilakukan setelah implementasi. Ini bukan audit ulang seluruh aplikasi atau seluruh tabel hasil attendance.

## Arah dan keputusan

Membaca ini sebagai layar kerja pelaporan HR dengan gaya visual Internal9 yang sudah ada, ENERGY 1 / RHYTHM 1 / MOTION 1. Brief pengguna: cukup upload data lalu proses.

- Satu pintu masuk Attendance Report mengurangi pilihan awal antara Report Center dan Upload Period.
- Formulir baru hanya meminta dua file karena periode sudah dapat ditentukan oleh processor dari file Total Jam Kerja.
- Biru dan tipografi panel dipertahankan untuk menjaga kesinambungan dengan aplikasi yang digunakan pengguna.
- Riwayat dan hasil ditempatkan berurutan agar pengguna memilih periode sebelum membuka hasil; pengelolaan upload tersedia sebagai aksi tambahan.
- Detail file dilipat karena berguna saat menelusuri masalah, bukan saat pertama membuka laporan.
- Padding 20 px di desktop dan 16 px di ponsel menjaga tepi konten sejajar. Tombol alur utama minimal 44 px agar mudah disentuh.
- Ikon upload menunjukkan pemilihan berkas. Tidak ada ilustrasi, statistik contoh, atau animasi dekoratif baru.
- Tema terang mengikuti konfigurasi panel yang sudah ada; tidak ada tema/toggle baru dalam lingkup ini.

## Delivery gate

- **Hard Gate PASS (lingkup perubahan):** formulir kosong menampilkan dua pesan wajib pilih file; riwayat kosong dan gagal diuji; tautan upload, kembali, hasil, kelola upload, pergantian periode, dan File sumber berfungsi. Tidak ada tautan hasil kosong pada state tanpa laporan. Viewport 375, 390, 768, dan 1536 px tidak memiliki overflow horizontal pada pusat laporan. Formulir diperiksa pada desktop dan 390 px. Tab menuju hasil dan Enter pada File sumber bekerja dengan outline terlihat. Tidak ada console error pada sesi pemeriksaan. Proses file diuji melalui Livewire dengan Excel sintetis dan SQLite in-memory.
- **Purpose-Gate PASS:** warna, tata letak, tipografi, jarak, wadah, dan ikon memiliki alasan di atas; tidak menambahkan glow, gradient, glass, ilustrasi generik, atau efek dekoratif.
- **Liveliness PASS:** dial 1/1/1 sesuai layar kerja yang tenang; aksi upload menjadi pintu mulai, riwayat menjadi pemilih konteks, dan aksi hasil berada bersama laporan terpilih. Biru panel dan pola label berbahasa kerja digunakan konsisten.
- **Craftsmanship & Quality Locks PASS (lingkup perubahan):** label menjelaskan tindakan, status berasal dari record, berkas gagal dapat diperbaiki, izin proses diperiksa kembali di server, dan laporan tersimpan tidak hilang saat proses gagal. Build dan pengujian di bawah lulus. Tidak ada klaim pemasaran atau angka buatan pada UI.

## Bukti verifikasi

- Suite aplikasi dan audit: 225 tes, 1.310 assertion, lulus sebelum perbaikan akhir pembaruan status gagal.
- Pengujian akhir AttendanceUploadFlowTest, AuditRegressionTest, AttendanceReportProcessorTest: 22 tes, 211 assertion, lulus. Termasuk 7 tes alur baru dan regresi status gagal yang langsung tampil tanpa reload.
- Pint untuk file PHP attendance yang disentuh: lulus. `git diff --check`: lulus. `npm run build`: berhasil.
- Pemeriksaan browser: halaman upload, validasi kosong berbahasa Indonesia, kembali, riwayat gagal dan selesai, detail sumber melalui keyboard, hasil laporan periode 17, breadcrumb kembali ke periode 17, kelola upload, dan kembali ke laporan.
- Tombol upload/proses/kembali dan aksi pusat laporan terukur tinggi 44 px.
- Lebar halaman/viewport pusat laporan: 360/375, 375/390, 753/768, 1521/1536 px. Override viewport dikembalikan ke ukuran semula.
- Contrast checker: #475569 pada putih 7,58:1; putih pada #0068d9 5,27:1; fokus #0057b8 pada putih 6,87:1. Ini bukti pasangan warna yang diperiksa, bukan sertifikasi aksesibilitas seluruh aplikasi.

## Batas dan perilaku yang dipertahankan

- Izin attendance.manage diperlukan untuk proses otomatis. Pengguna dengan attendance.upload saja dapat menyimpan file dan menunggu pemrosesan pengguna yang berwenang. Hak unduh tetap attendance.view.
- Processor, perhitungan jam, aturan lokasi, batas pulang, dan pengelompokan Employee ID tidak diubah.
- Pengujian upload/proses menggunakan data sintetis pada database tes. Pemeriksaan browser tidak memproses ulang atau mengganti laporan operasional pengguna.
- Halaman pengelolaan lama dan tabel hasil tetap tersedia; keduanya tidak didesain ulang dalam pekerjaan ini.
