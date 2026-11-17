# Pemeriksaan Inventori bersama dan gudang per entitas

6 November 2026. Mode During mengikuti pilihan pengguna. Implementasi dan migrasi aplikasi lokal diperiksa; audit visual belum lengkap. Laporan ini tidak menyatakan seluruh Delivery Gate lulus.

## Arah dan alasan

Halaman operasional Inventori untuk petugas gudang, Purchasing Officer, SCM, IT, dan peminta mengikuti bahasa visual IK WorkDesk. ENERGY 1 / RHYTHM 1 / MOTION 1.

- Satu menu Inventori menampung Master Barang, Gudang, Stok per gudang, Register PR/SR, Aset/perangkat, Riwayat mutasi, dan Ringkasan ATK agar pendataan memakai sumber yang sama.
- Tab membagi jenis data yang memiliki kolom serta pekerjaan berbeda tanpa menambah menu master berulang.
- Tabel memakai nomor/kode barang, entitas, gudang, satuan, dan asal pencatatan karena data tersebut diperlukan untuk memeriksa saldo.
- Warna, tipografi, jarak, border, dan fokus mengikuti stylesheet SCM agar formulir serta dokumen perusahaan konsisten.
- Kontainer scroll lokal mempertahankan keterbacaan tabel; grid formulir yang ada menyediakan susunan satu, dua, dan tiga kolom sesuai lebar.
- Tidak ada ilustrasi, ikon dekoratif, kartu promosi, warna, atau animasi baru.

## Perilaku yang diperiksa

Master Barang menghubungkan PR, ATK, dan perangkat. PR tetap menyimpan salinan nama/spesifikasi/satuan sehingga pengubahan master tidak mengubah dokumen lama. SKU perangkat berbeda dari unit dengan nomor aset dan serial number. Ruang server tidak diperlakukan sebagai stok.

Gudang HO dan Project memiliki konteks KPMOG/APCA terpisah. Label Project mengikuti master tanpa mengganti identitas gudang. Saldo juga menyimpan departemen asal pencatatan untuk mempertahankan batas akses. Izin `inventory.view-all` menyediakan tampilan seluruh entitas tanpa memberikan kewenangan mutasi atau penerimaan PR. Izin akun nyata tidak ditambah otomatis.

Penerimaan gudang memerlukan PR dengan CBE final dan vendor terpilih, gudang yang sesuai konteks, serta jumlah yang tidak melebihi sisa PR. Token yang sama tidak menggandakan penerimaan. Transfer biasa dibatasi dalam entitas yang sama. Pendaftaran unit dari stok mengurangi satu unit yang belum didaftarkan; pendataan aset lama tetap dapat dilakukan tanpa mengambil stok.

Saldo ATK lama ditampung sebagai saldo belum dipetakan dengan kepemilikan entitas kosong. Petugas menentukan jumlah KPMOG/APCA melalui formulir pemetaan; total tetap sama dan tidak boleh dipetakan berlebih. Penyerahan dan konversi kekurangan PR tidak memakai saldo lama yang belum jelas entitasnya. Petugas tetap menyerahkan ATK, lalu peminta mengonfirmasi penerimaan melalui mekanisme lama. Konfirmasi menambah saldo departemen dan tidak menambah stok gudang lagi.

## Bukti pengujian

- `tests-shared-forms.json`: 14 tes inventori bersama lulus, 107 assertion. Mencakup migrasi data lama, pemetaan entitas, penyerahan/konfirmasi ATK, master PR dan salinan dokumen, penerimaan sebagian, batas jumlah, token, transfer, role baca seluruh entitas, pendaftaran unit, serta formulir Livewire.
- `tests-shared-recheck.json`: 60 tes inventori, ATK, PR kekurangan, dan penomoran PR/SR lulus, 384 assertion. Termasuk pemeriksaan ulang rollback migrasi pada SQLite setelah indeks unik dilepas sebelum kolomnya.
- `tests-shared-final.json`: regresi utama menjalankan 340 tes; 339 lulus dan satu error rollback indeks unik SQLite. Error tersebut diperbaiki dan tes terkait lulus dalam pemeriksaan ulang 60 tes. Ini bukan klaim satu eksekusi 340 tes yang semuanya hijau.
- `tests-shared-it.json`: 165 tes IT lulus, 3.043 assertion. Integrasi katalog tidak memaksa modul IT yang memakai skema tersendiri bergantung pada tabel inventori sebelum migrasinya tersedia.
- `tests-shared-postgres.txt`: 6 tes PostgreSQL lulus, 69 assertion, termasuk penerimaan dan pengeluaran stok serentak. Database uji dibuat tersendiri lalu dibersihkan. Jumlah record seluruh tabel utama tetap sama selama runner tersebut.
- Suite di atas saling tumpang tindih; jumlahnya tidak dijumlahkan sebagai jumlah tes unik.
- `build-shared-delivery.txt`: build frontend lulus. Pint dan `git diff --check` lulus.
- Checker kontras lokal: teks `#111827` pada putih 17,74:1; `#4b5563` pada `#f9fafb` 7,23:1; aksen `#9a3412` pada putih 7,31:1.

## Penerapan lokal dan pemeriksaan data

Tiga migrasi fitur inventori bertanggal 6 November diterapkan pada PostgreSQL lokal `ik_workdesk`. Migrasi lain yang masih pending tidak dijalankan. Backup custom PostgreSQL dibuat dan daftar isinya diverifikasi dengan `pg_restore --list` sebelum migrasi. Nama backup: `storage/app/private/backups/inventory-before-20261106-160515.dump`.

`local-inventory-migration.json` mencatat checksum data asli sebelum/sesudah pada sembilan tabel: master ATK, permintaan/item ATK, saldo departemen ATK, perangkat, permintaan/item PR/SR, serta kedua tabel pemetaan izin. Semua sama. Perubahan yang ditambahkan adalah tabel inventori, referensi baru, dan definisi izin; tidak ada penetapan izin pengguna/role.

Migrasi menghasilkan 127 referensi Master Barang dan 7 konteks gudang dari data lokal saat pemeriksaan. Data perangkat lama tidak digabung berdasarkan kemiripan nama. Saldo ATK yang belum memiliki entitas masih memerlukan pemetaan oleh petugas.

`local-inventory-render.json` mencatat status HTTP 200 untuk ketujuh dataset. Pemeriksaan dilakukan melalui kernel aplikasi dengan sesi array untuk pengujian baca. Tidak ada selisih saldo ATK terhadap saldo inventori, tidak ada ATK tanpa referensi katalog, dan tidak ada perangkat non-ruang tanpa referensi katalog. Render server bukan pengganti pemeriksaan visual browser. Tidak ada deployment ke server lain.

## Batas pemeriksaan browser

Database QA SQLite terpisah pada port 8011 dimigrasikan dan diisi fixture inventori. Edge masih menampilkan halaman Inventori versi sebelumnya saat pertama diobservasi. Saat mencoba memuat ulang, Computer Use menghentikan kontrol karena URL Windows tidak dapat dipastikan untuk pemeriksaan kebijakannya. Tidak ada pemeriksaan browser lanjutan atau klaim click-through revisi setelah penghentian tersebut.

Pemeriksaan visual desktop, mobile 320/400 piksel, zoom 200%, keyboard, dan console untuk revisi ini masih tertunda. Sesuai [skill anti-slop](../.agents/skills/antislop/SKILL.md), “Delivery without a clean gate is a failure.” Karena itu laporan ini tidak memberikan sign-off visual.

## Hard Gate

- R-02 PASS: copy baru menyebut data dan tindakan secara konkret tanpa em dash.
- R-03 TERTUNDA: source memakai grid responsif, kontrol 44 piksel, dan scroll tabel lokal; reflow revisi belum diperiksa langsung.
- R-17 PASS: saldo, jumlah, dan daftar berasal dari query serta transaksi database, dengan bukti kesesuaian saldo lokal.
- R-18 PASS: tidak ada testimonial baru.
- R-23 PASS: modul bersama dan gudang per entitas mengikuti instruksi pengguna; tidak ada aset visual baru.
- R-24 PASS: route Inventori dan seluruh dataset dirender 200; tujuan PR/SR, perangkat, dan pengaturan ATK memakai route terdaftar.
- R-25 PASS: tiga kombinasi warna teks/aksen diuji dengan checker lokal dan memenuhi AA.
- R-26 PASS: kontrol terhubung pada metode Livewire dan service dengan pemeriksaan akses; formulir utama serta mutasi diuji melalui tes aplikasi.
- R-27 PASS: view menyediakan kondisi tanpa hasil, status loading, tombol yang dinonaktifkan selama penyimpanan, dan ringkasan error; kondisi role serta validasi diperiksa pada tes.
- R-28 PASS: tidak ada FAQ baru.
- R-32 TERTUNDA: label kontrol terhubung ke ID, kontrol native dipakai, dan outline fokus tersedia; keyboard revisi belum diperiksa langsung.
- R-33 PASS: perubahan PHP/Blade ditulis melalui patch source, tanpa skrip penulisan ulang CSS.
- R-34 PASS: konfigurasi panel mempertahankan tema terang yang sudah ada.
- R-35 TERTUNDA: build, tes aplikasi, transaksi PostgreSQL, migrasi, dan render server diperiksa; click-through visual belum lengkap akibat penghentian Computer Use.
- R-36 PASS: tidak ada klaim keamanan, kepatuhan, pelanggan, atau kinerja yang dibuat-buat.
- R-37 PASS: arah mengikuti aplikasi dan instruksi pengguna dengan dials 1/1/1.
- R-38 PASS: label/status mengikuti data aplikasi; data QA jelas merupakan fixture.

## Purpose Gate

- R-01 PASS: tidak ada gradien atau glow baru.
- R-04 PASS: tidak ada ikon dekoratif baru.
- R-06 PASS: font perusahaan dipertahankan untuk konsistensi pembacaan.
- R-07 PASS: tidak ada pola latar baru.
- R-08 PASS: tidak ada panah dekoratif baru.
- R-09 PASS: label aktif, entitas, kebutuhan, dan tahap memiliki fungsi operasional.
- R-10 PASS: tidak ada glassmorphism baru.
- R-12 PASS: tidak ada shadow baru.
- R-13 PASS: tidak ada glow baru.
- R-14 PASS: susunan berisi tabel dan formulir pekerjaan yang diperlukan, tanpa kartu fitur tambahan.
- R-19 PASS: tidak ada animasi baru.
- R-22 PASS: tidak ada ilustrasi baru.

## Liveliness

- Dials PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 mengikuti halaman operasional.
- Konsistensi PASS: komponen formulir dan tabel lama digunakan kembali.
- Fokus PASS: satu dataset atau formulir pencatatan menjadi pekerjaan utama view.
- Whitespace PASS: jarak lama memisahkan navigasi, filter, formulir, dan tabel.
- Aksen PASS: tab aktif, tautan, serta fokus memakai aksen aplikasi.
- Identitas PASS: kode barang, gudang, entitas, nomor PR, dan identitas aset mempertahankan konteks IK WorkDesk.
- Design Read PASS: arah, tujuan, dan dials dinyatakan sebelum revisi UI.

## Craftsmanship dan Quality Locks

- C-1 PASS: alasan master bersama, tab, tabel, dan gaya tercatat di bagian arah.
- C-2 PASS: tindakan mempunyai perilaku nyata, validasi, serta pemeriksaan akses pada backend.
- C-3 PASS: setiap dataset melayani kebutuhan pendataan atau penelusuran barang yang diminta.
- C-4 TERTUNDA: role, batas saldo, retry, dan kondisi transaksi diuji; ketahanan visual revisi belum diperiksa lengkap.
- C-5 PASS: tidak ada bukti sosial atau metrik promosi buatan.
- R-05 PASS: komposisi mengikuti tabel kerja inventori dan formulir transaksi.
- R-11 PASS: bentuk/radius kontrol mengikuti komponen aplikasi.
- R-15 PASS: tindakan menyebut barang, gudang, penerimaan, pemetaan, serta mutasi secara spesifik.
- R-16 PASS: tidak ada istilah promosi AI.
- R-20 PASS: isi spesifik untuk entitas, gudang, ATK, PR, dan perangkat IK WorkDesk.
- R-21 PASS: konfigurasi tema tidak diubah.
- R-29 PASS: token warna aplikasi dipertahankan.
- R-30 PASS: tidak ada peniruan desain produk lain.
- R-31 PASS: keputusan utama mempunyai alasan tertulis di bagian arah.
