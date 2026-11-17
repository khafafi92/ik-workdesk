# Pemeriksaan penyatuan menu PR/SR

Revisi ini telah digantikan oleh pemisahan pengajuan dan pemrosesan sesuai arahan terbaru pengguna. Lihat [pemeriksaan Purchasing Officer](delivery-purchasing-officer-2026-11-06.md) untuk perilaku serta status verifikasi saat ini.

6 November 2026. Anti-slop diterapkan selama implementasi sesuai pilihan pengguna. Laporan ini mencakup revisi penyatuan menu setelah implementasi inventori. Status: kode dan pengujian diperiksa, audit visual revisi belum lengkap. Ini bukan pernyataan lulus Delivery Gate.

## Arah dan alasan

Halaman operasional PR/SR untuk peminta, GA, dan SCM mengikuti tampilan IK WorkDesk yang sudah ada. ENERGY 1 / RHYTHM 1 / MOTION 1. Pengguna meminta satu kelompok Permintaan dengan pembedaan entitas dan HO/Project.

- Warna dan tipografi mengikuti stylesheet SCM agar formulir perusahaan tetap konsisten.
- PR dan SR memakai halaman yang sama untuk pengajuan dan pemrosesan sesuai izin, sehingga petugas membuka dokumen yang sama.
- Kolom entitas, kebutuhan, dan penangan menjelaskan siapa yang memproses tanpa membuat menu GA tambahan.
- Filter memakai grid lama dan label eksplisit agar dapat dibaca serta dioperasikan melalui kontrol native.
- Tabel memakai kontainer scroll lokal agar nomor dan tahap dokumen tidak dipadatkan pada layar sempit.
- Jarak, fokus, dan target kontrol mengikuti komponen yang sudah ada; tidak ada aset, kartu dekoratif, atau animasi baru.

## Perubahan yang diperiksa

Menu Pengadaan GA dihapus. Halaman antrean SCM lama tidak didaftarkan pada navigasi; route lama dipertahankan untuk kompatibilitas. Rekap Material PR dan Data Vendor berada di kelompok Permintaan. Tautan inventori dan navigasi aplikasi menuju PR/SR. Tab Vendor & dokumen dan Pengaturan tersedia sesuai izin pada halaman yang sama.

HO KPMOG/APCA mengikuti izin GA. Project mengikuti izin SCM per entitas. Permintaan sendiri tetap terlihat bagi peminta, sedangkan proses pengadaan, departemen, draft privat, jenis PR/SR, dan pengaturan memiliki pemeriksaan akses. Izin luas `scm.process` tetap kompatibel; pembatasan tim memerlukan izin role yang sesuai sebagaimana dijelaskan pada panduan inventori.

## Bukti dan batas pemeriksaan

- Delapan tes pembagian tim/role lulus dengan 91 assertion pada `output/inventory-20261106/tests-unified-roles.json`.
- Regresi terakhir menjalankan 324 tes dengan 3.427 assertion: 323 lulus dan satu error saat fixture lama menulis file tanda tangan sementara (`signatures/policy-1.png`), pada `tests-unified-serial.json`. Tes yang error dijalankan ulang bersama dua pemeriksaan hasil CBE pada menu bersama; ketiganya lulus pada `tests-unified-recheck.json`. Tidak ada kegagalan assertion pada regresi terakhir. Ini bukan klaim satu eksekusi 324 tes yang seluruhnya hijau.
- Build frontend lulus pada `output/inventory-20261106/build-unified.txt`.
- Pint dan `git diff --check` lulus setelah revisi.
- Pemeriksaan Edge QA sebelum penghentian memperlihatkan kelompok Permintaan berisi PR, SR, Persetujuan, Nomor PR/SR, Rekap Material PR, dan Data Vendor. Daftar PR memperlihatkan HO dengan penangan GA serta Project dengan penangan SCM. Draft terkait ATK memakai halaman PR yang sama.
- Pemeriksaan browser berikutnya dihentikan oleh Computer Use karena URL browser pada Windows tidak dapat dipastikan untuk pemeriksaan kebijakannya. Tidak ada pemeriksaan browser lanjutan setelah penghentian tersebut.
- Klik filter baru, tab pemrosesan pada PR/SR yang disatukan, reflow mobile, zoom, dan keyboard pada revisi ini belum diperiksa ulang secara visual. Pengujian backend tidak menggantikan bukti tersebut.
- Tidak ada migrasi database operasional, deployment, atau perubahan role akun nyata pada revisi ini.

## Hard Gate

- R-02 PASS: teks antarmuka yang ditambahkan memakai kalimat operasional tanpa em dash.
- R-03 TERTUNDA: source memakai grid responsif, target kontrol lama, serta scroll tabel lokal; pemeriksaan visual mobile revisi belum lengkap.
- R-17 PASS: ringkasan dan daftar berasal dari query dokumen sesuai akses, bukan angka buatan.
- R-18 PASS: tidak ada testimonial yang ditambahkan.
- R-23 PASS: penyatuan navigasi mengikuti permintaan eksplisit pengguna; tidak ada aset baru.
- R-24 PASS: PR/SR memakai route yang sudah ada; halaman dan tautannya dijalankan dalam tes aplikasi.
- R-25 PASS: tidak ada perubahan warna; teks baru memakai token SCM yang sudah diperiksa pada laporan inventori.
- R-26 PASS: filter, reset, pemilihan dokumen, penerimaan, dan konfigurasi terhubung pada metode Livewire dengan pemeriksaan akses; perilaku backend diuji.
- R-27 PASS: kondisi tanpa hasil, status loading, serta ringkasan error aplikasi tersedia pada view yang dipakai ulang.
- R-28 PASS: tidak ada FAQ yang ditambahkan.
- R-32 TERTUNDA: label select terhubung pada ID dan outline fokus tersedia pada source; pemeriksaan keyboard revisi belum lengkap.
- R-33 PASS: perubahan ditulis langsung pada PHP/Blade melalui patch source.
- R-34 PASS: konfigurasi panel tetap `darkMode(false)` dan stylesheet tema terang dipakai.
- R-35 TERTUNDA: build dijalankan dan backend diuji; click-through visual revisi belum lengkap akibat penghentian Computer Use.
- R-36 PASS: tidak ada klaim keamanan, kepatuhan, pelanggan, atau kinerja baru.
- R-37 PASS: arah mengikuti aplikasi dan koreksi pengguna, dengan dials 1/1/1.
- R-38 PASS: isi produk berasal dari status, entitas, kebutuhan, dan peran aplikasi; data browser adalah fixture QA.

## Purpose Gate

- R-01 PASS: tidak ada gradien atau glow baru.
- R-04 PASS: tidak ada ikon baru.
- R-06 PASS: tipografi perusahaan dipertahankan untuk konsistensi dokumen.
- R-07 PASS: tidak ada pola latar baru.
- R-08 PASS: tidak ada panah dekoratif baru.
- R-09 PASS: label penangan dan tahap berasal dari kebutuhan serta status dokumen.
- R-10 PASS: tidak ada glassmorphism baru.
- R-12 PASS: tidak ada shadow baru.
- R-13 PASS: tidak ada glow baru.
- R-14 PASS: tabel dan filter melayani pekerjaan pengadaan, tanpa kartu fitur baru.
- R-19 PASS: tidak ada animasi baru.
- R-22 PASS: tidak ada ilustrasi baru.

## Liveliness

- Dials PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 mengikuti halaman operasional yang sudah ada.
- Konsistensi PASS: tidak ada perubahan kerangka visual atau gaya komponen.
- Fokus PASS: daftar dokumen dan tindakan proses tetap menjadi isi utama.
- Whitespace PASS: grid dan jarak lama memisahkan filter dari tabel.
- Aksen PASS: tab aktif dan fokus memakai aksen aplikasi yang sudah ada.
- Identitas PASS: entitas KPMOG/APCA, HO/Project, nomor dokumen, dan formulir perusahaan dipertahankan.
- Design Read PASS: tujuan halaman bersama serta arah mengikuti aplikasi dinyatakan sebelum revisi UI.

## Craftsmanship dan Quality Locks

- C-1 PASS: alasan kolom, filter, navigasi, dan gaya ditulis di bagian arah.
- C-2 PASS: kontrol terhubung dengan aksi nyata dan akses diuji pada backend.
- C-3 PASS: navigasi dikurangi sesuai permintaan pengguna; tidak ada bagian pengisi baru.
- C-4 TERTUNDA: role dan batas akses diuji, tetapi ketahanan tampilan serta keyboard revisi belum diperiksa ulang secara lengkap.
- C-5 PASS: tidak ada bukti sosial atau angka promosi buatan.
- R-05 PASS: susunan mengikuti daftar kerja pengadaan yang sudah ada.
- R-11 PASS: bentuk kontrol dan radius lama dipertahankan.
- R-15 PASS: tindakan menyebut PR/SR, penawaran, penerimaan, dan pengaturan secara konkret.
- R-16 PASS: tidak ada istilah promosi AI.
- R-20 PASS: isi menyebut proses dan entitas IK WorkDesk yang nyata.
- R-21 PASS: konfigurasi tema panel tidak diubah.
- R-29 PASS: token warna lama dipakai tanpa palet baru.
- R-30 PASS: tidak ada peniruan desain produk lain.
- R-31 PASS: alasan keputusan utama ditulis pada bagian arah.

Audit visual tidak dinyatakan selesai sampai butir tertunda diperiksa pada browser yang dapat dikendalikan.
