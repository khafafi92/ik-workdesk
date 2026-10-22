# Nomor dan penerimaan PR/SR

22 Oktober 2026. Referensi dibaca kembali dari `D:\Downloads\PR No 013 Maintenance Box epson l15150.xlsx`, sheet PR sel U3: `013/ APCA-HO/PR/X/2026`. Nomor baru memakai pola yang sama dengan spasi setelah slash dinormalisasi. Pengguna memilih mulai 001 untuk trial dan menghapus pilihan kategori saat menerima.

- Form baru menampilkan nomor otomatis dan readonly, sebelum data kebutuhan diisi. Contoh Oktober 2026: `001/APCA-HO/PR/X/2026` dan `001/APCA-HO/SR/X/2026`.
- Nomor urut terpisah untuk PR dan SR, berlaku per tahun, dan terus naik saat bulan berganti. Bulan Romawi dan tahun mengikuti tanggal penerbitan nomor. Nomor yang sudah diterbitkan tetap sama pada edit draft, kirim dan penerimaan, serta tidak dialihkan ke jenis lain.
- Nomor dicadangkan atas nama pemohon saat form dibuka. Menutup form tanpa menyimpan tidak mengembalikan nomor ke urutan. Nomor tidak dapat diklaim pemohon lain atau dipakai untuk membuat dua permintaan.
- Form menyediakan Kirim ke SCM sebagai tindakan utama. Simpan draft tetap tersedia untuk melanjutkan atau menambah lampiran sebelum mengirim. Simpan dan kirim langsung berjalan dalam satu transaksi.
- Status yang terlihat tetap Draft sebelum diterima, termasuk sesudah dikirim. Permintaan terkirim memiliki keterangan menunggu penerimaan dan isinya terkunci. Internal state `submitted` dipertahankan untuk membedakan draft privat dari permintaan yang masuk antrean SCM.
- SCM cukup klik Terima oleh SCM tanpa pilihan kategori. Status menjadi Diterima SCM dan alur berlanjut ke vendor, CBE, legalitas. Ringkasan dan filter menggabungkan draft privat serta draft terkirim sebagai satu label Draft, tanpa memperlihatkan draft privat kepada SCM.
- Kategori, persyaratan dan nomor lama dipertahankan. Permintaan baru tidak diberi kategori otomatis. Pada proses tanpa kategori, SCM dapat menambahkan persyaratan dokumen melalui tab Legalitas dengan alasan; aturan dokumen wajib dan penguncian transaksi selesai tetap berlaku.

## Verifikasi

40 tes SCM, katalog menu dan izin tambahan lulus dengan 484 assertion. Cakupan baru meliputi format nomor, urutan PR/SR, pergantian bulan/tahun, nomor sebelum simpan, pemakaian satu kali, penolakan pemohon lain, transaksi gagal yang dapat diperbaiki dengan nomor sama, kirim langsung, penerimaan tanpa master kategori, serta satu filter Draft dengan akses antrean tetap terbatas.

Runner PostgreSQL terpisah lulus: satu tes dengan 14 assertion. Dua worker membuat PR bersamaan dan memperoleh urutan 001 dan 002 yang berbeda. Nomor tidak berubah saat diterima tanpa kategori. Uji finalisasi CBE bersamaan tetap lulus. Database uji dibersihkan dan jumlah record database utama tidak berubah.

Browser Chrome lokal membuktikan PR 001 disimpan sebagai draft, dikirim dengan status Draft, kemudian diterima satu klik tanpa kategori dengan nomor tetap. SR 001 dikirim langsung dari form dan diterima dengan cara yang sama. Data diberi penanda DEMO dan hanya disimpan di lokal. Form SR pada 320 px tidak menambah overflow; nomor readonly terukur di DOM. Override ukuran dikembalikan. Simpan/kirim/terima diperiksa dengan Enter dan dialog konfirmasi. Konsol terakhir tidak memuat error/warning.

Bukti berada di `output/scm-browser-20261022/`: `pr-nomor-001-menunggu-scm.jpg`, `pr-nomor-001-diterima-scm.jpg`, `sr-nomor-001-form-320.jpg`, dan `sr-nomor-001-diterima-scm.jpg`.

## Gate UI perubahan ini

| Pemeriksaan | Status dan bukti |
| --- | --- |
| R-02, R-16, R-36 | PASS: nomor mengikuti referensi pengguna, status/keterangan sesuai tindakan aktual, tanpa klaim tambahan |
| R-03 | PASS: nomor/form 320 px tidak membuat overflow dokumen; memakai kontrol dan ukuran komponen yang sudah diverifikasi |
| R-17, R-18, R-38 | PASS: ringkasan memakai hitungan query; pengujian transaksi memakai data DEMO lokal |
| R-23, R-24 | PASS: tidak menambah aset atau navigasi; menu PR, SR, SCM tetap sesuai arahan pengguna |
| R-25, R-29 | PASS: tidak mengubah warna/font; kontras komponen menggunakan hasil sebelumnya 4,83:1 sampai 17,74:1 |
| R-26, R-27, R-35 | PASS: nomor muncul, simpan, kirim langsung, dan terima diuji melalui browser; kegagalan validasi serta pemakaian ulang nomor diuji backend |
| R-32 | PASS: input nomor memiliki label serta readonly; simpan/kirim/terima dijalankan dengan Enter; dropdown kategori dihapus |
| R-33, R-34 | PASS: perubahan langsung pada sumber PHP/Blade, tema terang tetap mengikuti panel |
| Purpose gate, R-37, liveliness | PASS: arah IK WorkDesk ENERGY 1 / RHYTHM 1 / MOTION 1 dipakai ulang; nomor dan Kirim ke SCM memperjelas tindakan utama tanpa dekorasi baru |
| C-1 sampai C-5, quality locks | PASS: komponen konsisten, nomor tahan konkurensi, akses dan snapshot lama tetap terlindungi tes |

Batas QA modul yang tidak berubah, termasuk unggah file lewat ekstensi dan simpan PDF, tetap ada pada [status verifikasi SCM](SCM-VERIFIKASI.md).

## Penerapan server

Commit `50d8fe9df288e828cf28503e4b9d9671a6e81593` diterapkan ke IK WorkDesk `10.1.20.97:8093`. [CI commit aplikasi](https://github.com/khafafi92/ik-workdesk/actions/runs/39748420537) lulus: build, migrasi, Pint dan seluruh tes aplikasi.

Backup privat kode, environment dan database PostgreSQL tersedia di `/home/r92/ik-workdesk-releases/20261022-153505-50d8fe9df288-scm-update/backup`; dump diperiksa dengan `pg_restore --list`. Isi 91 tabel lama, termasuk permintaan, audit, user, role dan permission, identik sebelum/sesudah penerapan. Dua tabel nomor baru ditambahkan tanpa mengubah dokumen lama. Pemeriksaan ini mengecualikan migration, cache, session dan antrean.

Dashboard, PR, SR, antrean, vendor dan pengaturan merespons HTTP 200 dalam transaksi read-only. Worker aktif dan maintenance berakhir. Browser akun khafafi memverifikasi PR yang sudah dikirim tampil Draft, pesan menunggu penerimaan tersedia, dan tombol Terima oleh SCM tidak meminta kategori. Permintaan tersebut tidak diterima atau diubah oleh pengujian browser.

Form PR baru dibuka terpisah dan menampilkan `001/APCA-HO/PR/X/2026` sebagai readonly. Nomor ini dicadangkan untuk trial pengguna; tidak ada permintaan fiktif yang disimpan/dikirim di server. Kedua halaman disediakan untuk pengguna melanjutkan. Konsol form baru tanpa error/warning. Bukti lokal: `output/scm-server-20261022/pr-nomor-001-form-server.jpg` dan `pr-pending-draft-tanpa-kategori-server.jpg`.
