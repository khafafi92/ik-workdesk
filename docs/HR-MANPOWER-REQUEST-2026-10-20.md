# Kebutuhan Manpower HR

Service Desk memakai satu kategori **Kebutuhan Manpower** di departemen HR. Penempatan untuk plant, kantor, atau proyek dijelaskan pada lokasi penempatan. Tidak ada pemisahan proses antara manpower plant dan kebutuhan manpower.

## Form

- Wajib per permintaan: tujuan kebutuhan, lokasi/proyek penempatan, dan tanggal tenaga kerja dibutuhkan.
- Satu permintaan dapat berisi beberapa posisi. Setiap posisi wajib memiliki nama jabatan, jumlah orang, spesifikasi pekerjaan, serta persyaratan kandidat.
- Alasan kebutuhan, jenis tenaga kerja, dan catatan tambahan opsional.
- Total dihitung dari jumlah setiap posisi, bukan dipercaya dari nilai yang dikirim browser. Jumlah harus bilangan bulat positif.
- Subjek dan uraian dihasilkan dari rincian yang sudah divalidasi. Work Log HR memperoleh uraian tersebut saat permintaan dibuat. Perubahan setelah pembuatan mengikuti perilaku edit Work Log yang sudah ada.
- Kategori biasa tetap menggunakan Subject dan Description. Mengaktifkan form manpower pada suatu kategori tidak memaksa permintaan lama yang belum memiliki rincian terstruktur untuk diisi ulang.

## Kategori dan penerapan

Migrasi `2026_10_20_000000_add_manpower_requests.php` menambah flag kategori dan JSON rincian tiket. Setelah migrasi, jalankan:

```powershell
php artisan db:seed --class=ManpowerRequestCategorySeeder --force
```

Seeder bisa diulang. Nama lama Kebutuhan Man Power dan Manpower Plant disatukan menjadi satu pilihan aktif. ID kategori duplikat tetap ada tetapi dinonaktifkan agar riwayat tidak terhapus. Jika kategori utama sengaja telah dinonaktifkan, seeder tidak mengaktifkannya kembali. Alur kerja dan kategori departemen lain tidak diubah.

Penerapan lokal: satu kategori aktif Kebutuhan Manpower; Manpower Plant dinonaktifkan. Hash seluruh baris tiket sebelum dan sesudah penggabungan sama. Tidak dibuat tiket percobaan di database aplikasi.

## Notifikasi dan email tujuan

- Penerima: pengguna dengan employee aktif pada departemen tujuan, serta izin Service Desk `tickets.view` atau `tickets.manage`. Izin langsung, izin role aktif, dan administrator mengikuti pemeriksaan izin aplikasi yang sudah ada.
- Notifikasi tersimpan langsung pada lonceng aplikasi, termasuk untuk penerima tanpa email. Penulisan berada dalam transaksi pembuatan sehingga ikut batal jika permintaan gagal disimpan.
- Email hanya diantrekan untuk penerima dengan alamat email, setelah transaksi berhasil commit. Email memuat nomor, ringkasan posisi beserta total, prioritas, pengaju, departemen tujuan, dan tautan detail lengkap.
- Mailer email penerima dipilih oleh MailerResolver dan benar-benar diterapkan pada MailMessage. Alur persetujuan CBO yang sudah ada tetap berlaku.
- Queue email permintaan baru: `service-desk-notifications`. Worker lokal sudah dijalankan tersembunyi dan diperiksa hidup. Helper berikut memulai worker jika belum ada, tanpa membuat worker kedua:

```powershell
powershell -File scripts/start-service-desk-worker.ps1
```

Untuk server gunakan pengelola proses dengan perintah:

```shell
php artisan queue:work --queue=service-desk-notifications --sleep=3 --tries=3 --timeout=90 --backoff=30
```

Jalankan kembali worker setelah restart komputer atau penghentian proses. Setelah deployment gunakan prosedur restart worker aplikasi agar kode baru termuat.

Saat pemeriksaan lokal terdapat satu penerima HR yang memenuhi izin dan memiliki email; mailer KPMOG memiliki konfigurasi kredensial. Tidak dilakukan pengiriman email nyata, sehingga penerimaan SMTP belum diuji. Antrean default lama berisi 194 job dan 18 failed job dibiarkan tetap. Worker baru hanya mengonsumsi queue khusus di atas. Log worker tersedia di `storage/logs/service-desk-worker.log` dan `storage/logs/service-desk-worker-error.log`.

## Validasi

- PASS: 60 pengujian, 298 assertion pada ManpowerRequestTest, LegalRequestDetailsTest, PermitWorkflowTest, TicketCollaborationPromotionTest, TicketAssignedPicTest, SecurityHardeningTest, dan WorkLogAuthorizationTest.
- PASS: beberapa posisi, jumlah tidak valid, catatan kosong, manipulasi payload/total/departemen, akses pengguna luar departemen, edit dan tampilan riwayat, serta seeder berulang.
- PASS: routing notifikasi untuk izin role dan izin langsung, penerima tanpa email, pengecualian pengguna nonaktif/tanpa izin/divisi lain, penyimpanan notifikasi format Filament, tautan detail, pemilihan mailer, dan pengaturan after-commit/queue khusus. Transport email diuji dengan simulasi.
- PASS: Pint untuk PHP fitur, `git diff --check`, dan `php artisan security:check` lokal.

## Pemeriksaan tampilan

Anti-slop diterapkan selama implementasi dengan tampilan Internal9/Filament yang sudah ada; ENERGY/RHYTHM/MOTION 1/1/1. Tidak ada aset atau arah visual baru. Kolom sejajar mengikuti grid Filament; kelompok posisi berulang mengikuti kebutuhan permintaan. Warna fokus dan border dipertegas agar batas dan fokus kontrol terbaca.

- PASS Hard Gate: satu pilihan kategori HR terlihat di browser; Subject/Description berganti menjadi form; tambah/hapus posisi dan total otomatis diperiksa. Form kosong tertahan oleh validasi browser, dan validasi server diuji terpisah. Tidak ada tiket atau data contoh tersimpan.
- PASS aksesibilitas: tambah/hapus posisi memakai Enter; Tab menuju kontrol berikutnya dengan fokus terlihat. Kontrol baru minimal 44 piksel. Border 4,76:1, fokus 5,27:1, dan teks error 6,47:1 terhadap putih.
- PASS responsif: viewport 390 dan 768 piksel tidak mengalami overflow horizontal; kolom bertumpuk pada ponsel. Viewport dikembalikan setelah pemeriksaan.
- PASS Purpose Gate dan Liveliness: pola warna, tipografi, tombol, dan layout memakai komponen aplikasi; kelompok posisi memisahkan rincian kandidat; tidak ada dekorasi tambahan, statistik pemasaran, atau aset buatan.
- PASS Craftsmanship: form berjalan pada server lokal; pemilihan kategori, tambah/hapus, perhitungan, validasi, keyboard, dan tampilan baca/edit diperiksa melalui browser atau pengujian fitur. Console browser tidak berisi error pada pengujian form.

Bukti tampilan: `output/hr-manpower-review-20261020/category-form.png`.
