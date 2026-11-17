# Delivery check: inventori, ATK ke PR, dan pengadaan GA

Catatan revisi: laporan ini merekam implementasi sebelum koreksi navigasi berikutnya. Perilaku dan status verifikasi terbaru dicatat pada [pemeriksaan Purchasing Officer](delivery-purchasing-officer-2026-11-06.md). Hasil browser pada laporan lama tidak menjadi bukti pemeriksaan visual revisi terbaru.

Tanggal: 6 November 2026, Asia/Jakarta. Mode: During, sesuai jawaban pengguna. Cakupan pemeriksaan adalah perubahan inventori, konteks HO/Project pada ATK, konversi kekurangan stok ke PR, dan pembatasan proses GA/SCM. Ini bukan audit ulang seluruh halaman lama IK WorkDesk.

## Arah dan alasan desain

Halaman operasional untuk peminta, GA, dan SCM, mengikuti bahasa visual Filament dan formulir perusahaan yang sudah digunakan IK WorkDesk. ENERGY 1 / RHYTHM 1 / MOTION 1. Tabel dipakai untuk membandingkan stok, jenis kebutuhan, dan tahap pengadaan. Tab memisahkan saldo barang dari dokumen permintaan. Warna aksen menandai tab aktif, fokus keyboard, dan tautan tindakan. Lebar minimum tabel menjaga nomor dokumen dan instruksi tetap terbaca; tabel digeser di dalam kontainernya pada layar sempit. Tidak ada aset atau arah merek baru.

## Bukti pengujian

- `tests-delivery.json`: 42 tes ATK dan pembagian tim lulus, 270 assertion.
- `tests-scm.json`: 312 tes feature/unit SCM lulus, 3.198 assertion. Suite ini termasuk tes pembagian tim, sehingga jumlah kedua suite tidak boleh dijumlahkan sebagai jumlah tes unik.
- `tests-final.json`: 60 tes terkait ATK, pembagian tim, User Management, dan PR delegasi lulus, 419 assertion.
- `tests-postgres.txt`: 4 tes concurrency PostgreSQL lulus, 54 assertion. Runner membuat dan membersihkan database uji tersendiri serta memverifikasi jumlah record seluruh tabel database utama tetap sama.
- `npm run build` dan `php vendor/bin/pint --dirty --test` lulus. `git diff --check` tidak menemukan masalah whitespace.
- Artefak pengujian disimpan di `output/inventory-20261106/`.

## Catatan browser

Microsoft Edge memakai `127.0.0.1:8011` dengan SQLite QA terpisah. Setelah menemukan benturan sesi lintas port, cookie QA dipisahkan menjadi `inventory_qa_20261106`. Data uji diberi nama QA. Database operasional dan pemetaan akun pengguna tidak dimigrasikan atau diubah.

1. Menu ATK dibuka dan Inventori diakses dari navigasi.
2. Stok ATK menampilkan data gudang yang sama dengan master. Pencarian `tidak-ada-qa` menampilkan penjelasan tanpa hasil. Tab memindahkan fokus ke Hapus filter dengan outline yang terlihat; Enter menghapus pencarian.
3. Register PR/SR dibuka. Filter KPMOG menyisakan Project KPMOG. Filter Project tetap mempertahankan baris yang sesuai. Filter SR pada kombinasi itu menampilkan kondisi tanpa hasil.
4. Dialog Ajukan PR kekurangan dibuka, ditutup dengan Escape, dibuka kembali, lalu disubmit. Draft `001/KPMOG-OFC/PR/XI/2026` terbentuk. Tombol konversi hilang setelah draft terkait tersedia.
5. Penyerahan 4 pulpen melalui dialog Gudang Utama berhasil. Stok sistem turun dari 4 menjadi 0, jumlah diserahkan menjadi 4, dan status ATK menjadi Sebagian dipenuhi. Catatan GA ikut tersimpan.
6. URL PR yang tercantum pada referensi ATK dibuka. Draft berisi 6 pulpen dan 3 kertas, tanpa map yang stoknya cukup. Referensi nomor ATK tampil. Pengajuan tetap tertahan karena master persetujuan QA belum dikonfigurasi.
7. Pengadaan GA hanya menampilkan HO. Request HO dibuka dan Terima oleh GA berhasil mengubah tahap menjadi Diterima GA serta membuka Vendor & penawaran. Tab CBE membuka formulir CBE yang sama dengan workflow SCM.
8. Inventori dan kedua tabnya diperiksa pada lebar 400 dan 320 piksel. Filter tersusun satu kolom. Menu mobile terbuka dan tertutup. Scroll horizontal berada di kontainer tabel. Form ATK pada 320 piksel menampilkan pilihan HO/Project; memilih Project memunculkan Master kode Project yang wajib diisi dan memiliki nama aksesibel.
9. Inventori diperiksa pada zoom browser 200%, lalu zoom dikembalikan ke 100%. Teks dan kontrol tetap terlihat dan konten dapat digulir.
10. Konsol Inventori tidak menampilkan error JavaScript; panel Issues pada dokumen Inventori menampilkan nol issue. Form ATK lama memunculkan diagnostik label pada dua searchable select Filament saat pemuatan. Nama dan ID kontrol tersedia pada accessibility tree setelah inisialisasi; tidak ada perubahan pada komponen vendor. Diagnostik itu tidak diklaim sebagai audit bersih atas seluruh form lama.
11. Kelola stok ATK diklik dari Inventori dan membuka Master Barang ATK. Stok pulpen hasil penyerahan terbaca 0, sedangkan Actual tetap 4 sesuai perilaku pemeriksaan fisik yang sudah ada.

## Hard Gate

- R-02 PASS: salinan baru pada Inventori, layanan konversi, halaman GA, dan panduan tidak mengandung em dash.
- R-03 PASS: reflow 320/400 piksel dan zoom 200% diperiksa; tabel memakai scroll lokal. Kontrol baru memakai target minimum 44 piksel melalui CSS dan atribut aksi.
- R-17 PASS: stok, jumlah item, dan ringkasan pengadaan berasal dari query database; data browser jelas memakai fixture QA.
- R-18 PASS: tidak ada testimonial atau identitas promosi yang ditambahkan.
- R-23 PASS: menu Inventori dan proses GA berasal dari permintaan pengguna; logo dan aset perusahaan memakai sumber yang sudah ada.
- R-24 PASS: Inventori, master ATK, PR terkait, dan pengadaan GA memiliki route terdaftar; tujuan master dan PR dibuka pada browser.
- R-25 PASS: checker lokal menghasilkan 17,74:1 untuk teks `#111827` pada putih; 7,23:1 untuk `#4b5563` pada `#f9fafb`; 7,31:1 untuk aksen `#9a3412` pada putih. Outline fokus memakai aksen yang sama.
- R-26 PASS: tab, pencarian, filter, reset, konversi PR, penyerahan stok, dan penerimaan GA menghasilkan perubahan yang diamati; otorisasi aksi diuji di backend.
- R-27 PASS: tanpa hasil diperiksa pada stok dan register. Loading memakai `wire:loading` dengan status teks dan tombol yang dinonaktifkan; kegagalan query memiliki pesan `role="alert"` serta aksi refresh pada source Inventori.
- R-28 PASS: tidak ada FAQ yang ditambahkan.
- R-32 PASS: reset filter berhasil dengan Tab/Enter, dialog PR tertutup dengan Escape, dan select berfungsi dengan keyboard. Fokus terlihat pada screenshot browser.
- R-33 PASS: perubahan ditulis langsung pada PHP, Blade, dan CSS melalui patch source; tidak ada skrip penulisan ulang CSS.
- R-34 PASS: panel yang ada menggunakan `darkMode(false)`; perubahan mengikuti tema terang yang disediakan aplikasi.
- R-35 PASS: build dan suite terkait lulus; pemeriksaan browser atas alur yang berubah tercatat di atas. Perilaku workflow SCM yang dipakai ulang juga diperiksa melalui suite SCM.
- R-36 PASS: tidak ada klaim keamanan, kepatuhan, pelanggan, atau peningkatan kinerja baru.
- R-37 PASS: arah implementasi mengikuti aplikasi yang ada dan dials dinyatakan sebelum perubahan UI; tidak ada redesain merek.
- R-38 PASS: isi produk berasal dari data aplikasi, status workflow, dan kebutuhan pengguna; fixture browser ditandai QA.

## Purpose Gate

- R-01 PASS: warna mengikuti stylesheet aplikasi; tidak ada gradien atau glow baru.
- R-04 PASS: ikon penyerahan yang sudah ada tetap menunjukkan tindakan stok keluar; tidak ada ikon dekoratif baru.
- R-06 PASS: font dan formulir perusahaan yang sudah ada dipertahankan untuk konsistensi pembacaan dokumen.
- R-07 PASS: tidak ada pola latar baru.
- R-08 PASS: tidak ada panah dekoratif baru pada tombol.
- R-09 PASS: status ATK dan pengadaan mewakili keadaan database; tidak ada badge pemasaran.
- R-10 PASS: tidak ada glassmorphism baru.
- R-12 PASS: kontainer dan modal menggunakan komponen aplikasi yang sama; tidak ada tambahan shadow pada setiap elemen.
- R-13 PASS: tidak ada efek glow baru.
- R-14 PASS: komposisi mengikuti kebutuhan saldo dan register, bukan kumpulan kartu fitur.
- R-19 PASS: tidak ada animasi baru; transisi komponen tetap mengikuti Filament.

## Liveliness

- Dials PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 dicatat dan sesuai halaman operasional.
- Konsistensi PASS: tabel, kontrol, dan modal mengikuti pola aplikasi yang sudah ada.
- Fokus PASS: judul data, stok, dan tindakan berikutnya menjadi hierarki utama, tanpa banner tambahan.
- Whitespace PASS: jarak memisahkan navigasi dataset, filter, dan tabel; tidak ada blok pengisi.
- Aksen PASS: aksen fungsional terlihat pada tab aktif, tautan, dan fokus keyboard.
- Identitas PASS: label gudang/departemen, konteks entitas, nomor ATK/PR, dan formulir perusahaan dipertahankan.
- Design Read PASS: halaman operasional GA/SCM dengan visual aplikasi yang ada dinyatakan sebelum implementasi UI.

## Craftsmanship dan Quality Locks

- C-1 PASS: pemisahan tab dan scroll lokal memiliki alasan operasional yang dicatat di bagian arah desain.
- C-2 PASS: tindakan yang diuji menghasilkan perubahan nyata, dengan konversi idempoten dan validasi stok.
- C-3 PASS: kedua dataset diperlukan untuk stok ATK dan pemantauan permintaan yang diminta pengguna.
- C-4 PASS: kondisi kosong, keyboard, viewport sempit, dan zoom diuji; akses langsung GA ke Project ditolak pada tes.
- C-5 PASS: tidak ada bukti sosial atau metrik buatan.
- R-05 PASS: halaman menggunakan filter dan tabel sesuai tugas operasional; tidak ada struktur landing page.
- R-11 PASS: bentuk kontrol dan kontainer mengikuti komponen aplikasi, tanpa bentuk pill baru.
- R-15 PASS: tindakan bernama Stok ATK, Register PR/SR, Hapus filter, Ajukan PR kekurangan, dan Terima oleh GA.
- R-16 PASS: teks baru menjelaskan data dan tindakan; tidak ada istilah promosi AI.
- R-20 PASS: alur gudang, peminta, entitas, nomor Project, dan formulir pengadaan tetap spesifik untuk IK WorkDesk.
- R-21 PASS: tema mengikuti konfigurasi panel yang sudah ada, tanpa perubahan default tema.
- R-29 PASS: tidak ada palet baru; teks, garis, dan aksen mengikuti token SCM yang ada.
- R-30 PASS: tidak ada peniruan produk lain atau perubahan kerangka visual aplikasi.
- R-31 PASS: alasan tabel, tab, aksen, font, dan lebar minimum ditulis di bagian arah desain.

## Batas penerapan

Kode siap ditinjau dan pengujian tercatat lulus. Dua migrasi baru belum diterapkan pada database operasional. Akun Indah, Meyfi, Imam, Hevy, dan staf belum dipetakan. Izin lama `scm.process` tetap luas untuk kompatibilitas; untuk membatasi staf per tim, ganti izin lama dengan pilihan GA/Project yang sesuai dan periksa izin yang diwariskan oleh role. Langkah pengaturan ada pada `docs/INVENTORY.md`.
