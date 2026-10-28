# Modul SCM: PR/SR, CBE, dan legalitas vendor

Modul terintegrasi dengan pengguna, departemen, Filament, dan hak akses IK WorkDesk. Sidebar menyediakan **Permintaan > PR** dan **Permintaan > SR** untuk permintaan user, serta **SCM > SCM** untuk penerimaan dan pemrosesan oleh tim SCM. Vendor mengisi Form 005 melalui Portal Vendor dengan pendaftaran mandiri dan Vendor ID, atau melalui undangan dari SCM. SCM meninjau kiriman sebelum menerima profil dan dokumen ke master. Modul tidak menambahkan PO, kontrak, penerimaan barang, invoice, pembayaran, atau pesan otomatis.

## Panduan penggunaan langkah demi langkah

Diperbarui 29 Oktober 2026. Buka alamat IK WorkDesk yang diberikan pengelola dan masuk menggunakan akun pribadi. Alamat server yang tercatat untuk IK WorkDesk adalah `http://10.1.20.97:8093`; fitur CBE dan Portal Vendor pada pembaruan ini masih perlu diterapkan sesuai [petunjuk rilis](SCM-DEPLOY-2026-10-29.md). Menu mengikuti izin akun. Jika menu tidak muncul, minta pengelola memeriksa izin dan cakupan departemen akun tersebut.

### 1. Menyiapkan tanda tangan dan master

1. Setiap penandatangan membuka tautan **Atur TTD di profil (tab baru)** pada halaman persetujuan atau form PR/SR. SCM juga dapat memakai tautan **TTD profil** pada tab CBE. Simpan gambar tanda tangan sendiri pada halaman **Tanda tangan profil**. Gunakan PNG/JPG maksimal 2 MB dan 4096 × 4096 piksel. Menyimpan profil belum menandatangani dokumen.
2. Pengelola berizin konfigurasi membuka **SCM > Pengaturan > Master Approval**. Pilihan **Dokumen persetujuan** awalnya **PR/SR**; atur perusahaan, divisi, HO/Project, dan jenis form pada pilihan tersebut. Ikuti [panduan master approval](SCM-APPROVAL-MASTER-2026-10-27.md).
3. Pada panel **Master Approval** yang sama, ubah **Dokumen persetujuan** menjadi **CBE**, lalu pilih perusahaan. Isi **Acknowledged By: Meyfi**, **Crosschecked By: Dipta**, dan **Approved By: Lenaldy** menggunakan akun aktif yang sesuai, lalu tekan **Simpan master approval**. Atur perusahaan lain jika digunakan. Ganti kembali pilihan ke PR/SR untuk melihat atau mengedit aturan PR/SR.
4. **Prepared By** memakai akun SCM yang benar-benar menandatangani dan mengajukan CBE. Jika Imam yang memproses, masuk sebagai Imam. Keempat penandatangan harus memakai akun berbeda. Nama contoh tidak membuat akun atau gambar tanda tangan secara otomatis.

Satu Master Approval mengelola aturan PR/SR dan CBE melalui pemilih dokumen. Masing-masing jenis dokumen mempunyai penugasan sesuai aturannya. Perubahan master berlaku untuk pengajuan berikutnya; penandatangan dokumen yang sudah diajukan tetap mengikuti penugasan saat pengajuan.

### 2. Membuat dan mengajukan PR atau SR

1. Buka **Permintaan > PR** untuk barang atau **Permintaan > SR** untuk jasa, lalu buat permintaan baru.
2. Pilih perusahaan sebelum nomor diterbitkan. Perusahaan tidak dapat diganti setelah nomor terbit.
3. Pilih kebutuhan HO atau Project, isi tanggal dan rincian barang/jasa, jumlah, satuan, serta informasi kebutuhan yang tersedia. Tandai pengajuan di atas Rp5 juta jika sesuai. Kategori tidak perlu dipilih oleh pemohon.
4. Simpan draft, lengkapi lampiran yang diperlukan, lalu **Tanda tangani** pada Requested by. Perubahan isi atau lampiran draft setelah ditandatangani memerlukan tanda tangan ulang.
5. Ajukan persetujuan sesuai jenis kebutuhan. Pejabat membuka **Permintaan > Persetujuan**, memilih **PR/SR**, memeriksa dokumen yang ditugaskan, lalu menandatangani sesuai urutan atau mengembalikan dengan alasan perbaikan.
6. Jika dikembalikan, perbaiki draft, tandatangani ulang, lalu ajukan kembali. SCM baru menerima permintaan setelah seluruh tahap persetujuan yang diwajibkan selesai.

### 3. Menerima permintaan dan mengumpulkan penawaran

1. Petugas membuka **SCM > SCM**, mencari nomor PR/SR pada antrean, lalu membuka detail dan memilih **Terima oleh SCM**.
2. Di **Vendor & penawaran**, pilih minimal tiga vendor berbeda. Buat data vendor terlebih dahulu pada **Vendor & dokumen** bila belum tersedia.
3. Catat nomor/tanggal quotation, mata uang, syarat komersial, rincian harga, diskon dan pajak, lalu lampirkan file quotation. Cocokkan barang/jasa, jumlah, dan satuan dengan permintaan.
4. Jika vendor merevisi quotation, simpan versi baru. Saat menyusun CBE, pilih satu versi untuk setiap vendor dengan mata uang yang sama.

### 4. Mengelola berkas vendor dan Form 005

**Untuk vendor yang mengisi sendiri**, buka **Portal Vendor** melalui tautan pada halaman login atau alamat `/vendor`. Pada instalasi lokal, alamatnya `http://127.0.0.1:8000/vendor`. Setelah paket diterapkan ke server, gunakan alamat IK WorkDesk server diikuti `/vendor`.

1. Vendor baru memilih perusahaan tujuan, mengisi nama perusahaan dan jenis penyedia, lalu menekan **Mulai pendaftaran vendor**. SCM tidak perlu membuat profil master terlebih dahulu.
2. Sistem memberikan **Vendor ID** berawalan VND. Salin dan simpan ID tersebut sebelum melanjutkan Form 005. ID digunakan kembali untuk membuka data perusahaan dan berkas vendor yang sama.
3. Lengkapi lima bagian Form 005 dan unggah dokumen yang diminta. Untuk melanjutkan di lain waktu, buka Portal Vendor, isi **Vendor ID**, lalu tekan **Buka data vendor**. Data yang sudah disimpan akan muncul kembali.
4. Kirim form setelah lengkap. Selama menunggu pemeriksaan SCM, kiriman terkunci. Bila SCM meminta perbaikan, masukkan Vendor ID yang sama untuk membuka isian dan memperbaiki berkas sesuai catatan, lalu kirim ulang.
5. Setelah kiriman diterima SCM, masukkan Vendor ID yang sama saat membutuhkan pembaruan berikutnya. Sistem menyiapkan kiriman baru berisi data dan salinan berkas sebelumnya; pernyataan harus diisi ulang. Pembaruan masuk master setelah diperiksa dan diterima SCM. Profil yang sedang berlaku serta riwayat kiriman lama tetap tersimpan.

Vendor ID diberikan pada saat pendaftaran dan dipakai oleh pengelola data vendor tersebut. Jika ID hilang atau akses dicabut, hubungi SCM. Semua berkas tetap terikat pada vendor dan versi kirimannya.

**Untuk petugas SCM**, tautan dan alamat Portal Vendor tersedia di **SCM > Vendor & dokumen**. Kiriman pendaftaran baru muncul pada antrean pemeriksaan sebelum menjadi master vendor. Setelah diterima, profil dan berkas masuk pada vendor yang sama. Undangan langsung untuk vendor yang sudah terdaftar tetap tersedia:

1. Buka **Vendor & dokumen**, cari vendor yang dimaksud, lalu **Edit** untuk memilihnya. Riwayat dokumen ikut terfilter pada vendor tersebut. Periksa nama vendor tujuan sebelum mengunggah file.
2. Untuk pengisian oleh vendor, pilih **Buat tautan undangan Form 005**, tentukan perusahaan tujuan serta konteks transaksi bila diperlukan, lalu sampaikan tautan khusus itu kepada vendor.
3. Vendor membuka tautan tanpa akun internal, mengisi lima bagian, memilih asal/jenis dokumen, menyimpan bagian, serta mengunggah berkas yang diminta. Pilihan asal/jenis harus disimpan sebelum mengunggah dokumen yang mengikuti pilihan baru.
4. Vendor melengkapi ringkasan kekurangan di bagian 5 dan mengirim pernyataan. Setelah dikirim, isian terkunci sampai SCM meminta perbaikan.
5. SCM memilih **Periksa Form 005**, memeriksa jawaban dan file, lalu menerima atau meminta perbaikan dengan alasan. Jika diminta perbaikan, vendor memakai kembali tautan yang masih berlaku, memperbaiki isian/berkas dan mengirim ulang.

Tautan berisi token khusus vendor dan berlaku 30 hari. Simpan serta sampaikan hanya kepada vendor terkait. Tautan baru mencabut undangan lama yang belum diterima dan memulai formulir baru; gunakan alur perbaikan untuk memperbaiki kiriman yang sama. Penerimaan Form 005 memasukkan profil dan dokumen ke master, tetapi checklist legalitas setiap pengadaan tetap diperiksa oleh SCM.

### 5. Menyusun dan menandatangani CBE

1. Buka tab **CBE** pada pengadaan. Pilih minimal tiga vendor, masing-masing satu versi quotation.
2. Periksa harga, Non PKP/PPN, diskon tambahan dan syarat komersial. Pilih vendor pemenang serta isi alasan pemilihannya. Gunakan **Simpan draft CBE** selama masih perlu perubahan.
3. Periksa seluruh angka dan vendor, lalu tekan **Tanda tangani dan ajukan CBE** dan konfirmasi. Sistem membubuhkan TTD Prepared By dari profil akun SCM pelaksana dan mengunci isi CBE final. Tahap ini belum membubuhkan TTD pejabat berikutnya.
4. Meyfi masuk menggunakan akunnya, membuka **Permintaan > Persetujuan**, memilih **CBE** lalu **Periksa CBE**, memeriksa perbandingan dan menekan **Tanda tangani sebagai Acknowledged By**.
5. Dipta melakukan langkah yang sama untuk **Crosschecked By**, dilanjutkan Lenaldy untuk **Approved By**. Tombol tanda tangan hanya tersedia bagi akun yang sedang mendapat giliran.
6. Gunakan **Buka CBE untuk cetak / simpan PDF**. Empat kolom memuat gambar TTD, nama dan tanggal setelah masing-masing akun menandatangani. Perubahan gambar profil berikutnya tidak mengganti TTD pada dokumen yang sudah ditandatangani.

CBE final terkunci. Periksa isinya sebelum pengajuan; versi ini belum menyediakan pembukaan ulang/revisi CBE final. Pada CBE lama yang masih berada di tahap vendor terpilih dan belum mempunyai penugasan TTD, akun SCM pembuat aslinya dapat memilih **Tanda tangani dan ajukan persetujuan CBE lama**. Harga dan snapshot lama tetap tersimpan. Dokumen historis tidak diberi tanda tangan otomatis.

### 6. Melengkapi legalitas dan melihat hasil

1. Sesudah keempat TTD CBE baru lengkap, SCM membuka pengadaan dan memilih **Mulai pengumpulan legalitas**.
2. Periksa daftar persyaratan, gunakan dokumen vendor yang sesuai atau unggah versi baru, lalu tetapkan hasil pemeriksaan setiap item.
3. Jika dasar dokumen perlu diubah menjadi NPWP dan KTP, gunakan pilihan yang disediakan dan isi alasan. Penuhi seluruh persyaratan wajib dengan file dan masa berlaku yang sesuai.
4. Selesaikan pengadaan setelah seluruh dokumen wajib diterima. Transaksi selesai terkunci.
5. Pemohon membuka kembali PR/SR miliknya untuk melihat CBE final dan penawaran vendor terpilih. File legalitas dan quotation vendor lain tetap mengikuti batas akses SCM.

### Bantuan saat langkah tertahan

| Pesan atau keadaan | Tindakan pengguna |
| --- | --- |
| Aturan CBE belum diatur | Buka Master Approval, pilih CBE, lalu isi aturan perusahaan pada PR/SR tersebut. |
| Penandatangan harus berbeda atau tidak aktif | Periksa akun pada master, termasuk akun SCM yang menjadi Prepared By. |
| Gambar TTD belum tersedia | Akun yang akan menandatangani menyimpan gambar TTD sendiri pada profil. |
| Data/master berubah | Buka ulang dokumen atau master, periksa perubahan terbaru, lalu ulangi tindakan. |
| Masih menunggu pejabat lain | Buka Persetujuan, pilih CBE, lalu lihat nama penandatangan berikutnya. |
| Tautan vendor kedaluwarsa/dicabut | Hubungi SCM untuk pengaturan undangan; tautan baru memulai formulir baru. |
| Legalitas belum bisa dimulai | Lengkapi TTD CBE baru terlebih dahulu. |
| Dokumen wajib belum lengkap/kedaluwarsa | Perbarui file dan pemeriksaannya sebelum menyelesaikan pengadaan. |

## Menjalankan lokal di Windows PowerShell

Gunakan database lokal yang sudah dikonfigurasi. Jangan menjalankan reset atau `migrate:fresh` pada database aplikasi.

```powershell
php artisan migrate --path=database/migrations/2026_10_22_100000_create_scm_procurement_tables.php
php artisan migrate --path=database/migrations/2026_10_22_150000_move_scm_category_to_receipt.php
php artisan migrate --path=database/migrations/2026_10_22_160000_create_scm_request_numbering.php
php artisan migrate --path=database/migrations/2026_10_22_170000_add_scm_request_form_reference_fields.php
php artisan migrate --path=database/migrations/2026_10_23_100000_add_scm_alternative_legal_documents.php
php artisan migrate --path=database/migrations/2026_10_23_110000_add_scm_request_companies.php
php artisan migrate --path=database/migrations/2026_10_23_120000_add_request_prepared_signatures.php
php artisan migrate --path=database/migrations/2026_10_23_130000_add_scm_ho_approvals.php
php artisan migrate --path=database/migrations/2026_10_23_140000_allow_pending_cbo3_on_scm_routes.php
php artisan migrate --path=database/migrations/2026_10_24_100000_add_scm_cbe_commercial_terms.php
php artisan migrate --path=database/migrations/2026_10_24_120000_create_scm_vendor_registrations.php
php artisan migrate --path=database/migrations/2026_10_27_100000_add_employee_reporting_structure.php
php artisan migrate --path=database/migrations/2026_10_27_110000_create_scm_approval_policies.php
php artisan migrate --path=database/migrations/2026_10_27_140000_add_project_request_form_fields.php
php artisan migrate --path=database/migrations/2026_10_27_170000_add_foreign_vendor_document_types.php
php artisan migrate --path=database/migrations/2026_10_28_100000_create_scm_cbe_approvals.php
php artisan db:seed --class=ScmSeeder
npm run build
php artisan config:cache
php artisan route:cache
php artisan filament:optimize-clear
php artisan serve --host=127.0.0.1 --port=8000
```

Migrasi awal menambah tabel berawalan `scm_`. Migrasi berikutnya mengizinkan kategori permintaan kosong sampai diterima SCM, dengan foreign key tetap berlaku. Tidak ada record yang dihapus atau kategori lama yang dikosongkan. Seeder awal menambah permission dan jenis NPWP/NIB/SBU/KTP secara idempotent, tanpa menganggap semuanya wajib. Migrasi 23 Oktober menambahkan dasar dokumen pada kasus legalitas; kasus lama tetap memakai persyaratan sebelumnya.

Untuk data demo fiktif, hanya pada lingkungan `local` atau `testing`:

```powershell
php artisan db:seed --class=ScmDemoSeeder
```

Akun pengembangan: `scm.pemohon@example.test` dan `scm.petugas@example.test`, kata sandi awal `SCM-Demo-2026!`. Seeder hanya membuat akun bila belum ada dan tidak mengganti kata sandi akun yang ada. Semua vendor, PR/SR, dan dokumen demo diberi penanda DEMO/fiktif. Jangan menjalankan seeder demo atau memakai akun ini pada produksi. Seeder demo menyiapkan satu PR dan satu SR sampai selesai, kategori demo dengan NIB wajib, tiga vendor, serta contoh pemilihan harga terendah dan vendor lain dengan alasan.

## Hak akses dan penggunaan

Administrator mengatur izin melalui checklist menu Users, grup Permintaan dan SCM:

- `scm.request`: membuat/edit draft sendiri, mengirim ke SCM, melihat permintaan sendiri serta lampiran permintaan. Setelah CBE final, pembuat PR/SR dapat membuka CBE dan penawaran vendor terpilih dari permintaan tersebut. File legalitas dan file penawaran vendor lain tetap privat untuk SCM.
- `scm.process`: menerima permintaan yang sudah dikirim dalam cakupan departemen yang diizinkan; mengelola vendor, penawaran, CBE dan legalitas. Draft pengguna lain tetap privat.
- `scm.configure`: kategori, jenis dokumen, default persyaratan, serta Master Approval. Pilihan PR/SR berlaku per perusahaan, divisi, HO/Project, dan jenis form; pilihan CBE berlaku per perusahaan. Tidak otomatis memperoleh hak transaksi SCM.

Menu **Permintaan > Persetujuan** menyatukan pilihan PR/SR dan CBE. Profil TTD juga tersedia untuk akun aktif yang ditetapkan sebagai pejabat persetujuan. Pejabat PR/SR hanya dapat membuka isi dan lampiran permintaan yang ditugaskan kepadanya; penetapan PR/SR sendiri tidak memberi akses CBE, penawaran vendor, atau legalitas. Penandatangan CBE memilih CBE pada menu yang sama untuk membaca dan mencetak CBE yang ditugaskan kepadanya serta melihat gambar TTD di dalamnya. Penugasan CBE tidak memberi akses pengelolaan vendor, file legalitas, atau file quotation. Angka pada sidebar menghitung giliran tanda tangan PR/SR dan CBE yang dapat ditindaklanjuti akun tersebut. Tautan CBE lama diarahkan ke menu gabungan dengan pilihan dokumen yang sama. Akses file tetap mengikuti aturan SCM dan pembuat PR/SR.

SCM menggunakan permission yang benar-benar diberikan melalui role aktif atau permission langsung aktif. Flag admin atau role system-admin saja tidak memberi akses transaksi SCM. Cakupan departemen tetap mengikuti mekanisme proyek. Vendor merupakan master bersama dalam instalasi proyek yang saat ini tidak memiliki tenant terpisah.

Pada 22 Oktober 2026, atas permintaan pengguna, akun utama Khafafi di lingkungan lokal diberi ketiga izin SCM secara eksplisit. Sidebar dashboard utama juga menyediakan tautan **SCM - Pengadaan PR/SR** sesuai hak akses. Aktivasi ini khusus lokal, bukan perubahan default izin administrator atau penerapan ke server. Bukti tersedia di [catatan aktivasi menu lokal](../output/scm-browser-20261022/AKTIVASI-MENU-LOKAL.md).

User membuat draft pada menu PR atau SR, memilih kebutuhan HO atau Project pada panel di luar lembar form, dan menandatangani Requested by sebelum mengajukan persetujuan. Jenis PR/SR mengikuti menu dan tidak dapat diubah dari form. Estimasi nominal tidak diwajibkan dan tidak ditambahkan ke kertas; penanda pengajuan di atas Rp5 juta tetap berada di luar form. Kategori tidak diminta pada form user. Lokasi historis tetap tersimpan.

Saat memilih **Buat PR** atau **Buat SR**, user memilih **KPMOG** atau **APCA** sebelum nomor diterbitkan. Pilihan tersimpan pada permintaan, nomor cadangan, dan snapshot CBE final. Perusahaan tidak dapat diganti setelah nomor diterbitkan; buat permintaan baru jika salah perusahaan. Hak akses tetap mengikuti pemilik permintaan, permission, dan cakupan departemen yang sudah ada.

Formulir APCA dan KPMOG menggunakan susunan, kolom Brand/Item, jumlah baris, serta kolom tanda tangan yang sama. Kebutuhan HO dan Project memakai empat kolom Requested by, Acknowledged by, Crosschecked by, dan Approved by. Dokumen historis sebelum alur HO mempertahankan susunan lamanya. Perbedaan tampilan antara kedua perusahaan hanya logo. Logo KPMOG diambil dari lampiran `FRM-003 (Purchase Request) rev.3.xls`; perbedaan kolom, Delivery Point, dan kode/revisi pada contoh tidak diterapkan. Data tambahan dari versi sebelumnya tetap tersimpan tanpa migrasi penghapusan. Judul Purchase Request atau Service Request mengikuti menu, dan nomor tetap mengikuti seri perusahaan yang sebelumnya disetujui pengguna.

Nomor APCA dimulai dari `001/APCA-HO/PR/X/2026` untuk PR dan `001/APCA-HO/SR/X/2026` untuk SR pada trial Oktober 2026. Urutan terpisah per jenis dan tahun, tetap naik saat bulan berubah. Nomor dipertahankan ketika draft disimpan, dikirim dan diterima. Nomor lama tidak diubah.

Nomor baru diterbitkan setelah perusahaan dipilih. KPMOG memakai awalan `KPMOG-OFC`, mulai `001/KPMOG-OFC/PR/X/2026` dan `001/KPMOG-OFC/SR/X/2026` untuk trial sesuai pilihan pengguna. Counter dipisahkan per perusahaan, jenis, dan tahun. Nomor APCA lama dan counter yang sedang berjalan dipertahankan. Migrasi perusahaan tidak boleh dibatalkan setelah nomor KPMOG dibuat karena penghapusan identitas perusahaan dapat merusak riwayat.

Alur yang ditampilkan: Draft, Menunggu persetujuan, Diterima SCM, Pengumpulan penawaran, Penyusunan CBE, Vendor terpilih, Pengumpulan legalitas, Selesai. User melengkapi draft/lampiran, menandatangani Requested by, lalu memilih Ajukan persetujuan HO. SCM belum dapat melihat atau menerima permintaan selama persetujuan berjalan. Setelah seluruh tahap wajib selesai, permintaan masuk antrean SCM. Pada menu permintaan lama, status yang sudah dikirim tetap tampil sebagai Draft dengan keterangan menunggu penerimaan; pada menu persetujuan disebut Menunggu SCM. Isinya terkunci. SCM cukup klik Terima oleh SCM, tanpa memilih kategori, lalu mencari minimal tiga vendor, mengumpulkan quotation, membuat CBE, memilih satu vendor, dan melengkapi legalitas. Vendor dapat dibuat pada master atau dipilih dari vendor yang sudah terdaftar.

Semua peralihan divalidasi server dengan transaksi, row lock, dan versi permintaan. Jika ada perubahan bersamaan, muat ulang data sebelum mengulang tindakan. Nomor PR/SR memakai counter terkunci dan unique constraint; nomor CBE tetap menggunakan ULID. CBE final tidak dapat dibuka ulang. Revisi setelah final merupakan kebutuhan lanjutan, bukan fitur versi ini.

## Angka, penawaran, dan snapshot

Input angka tanpa pemisah ribuan, desimal memakai koma atau titik. Jumlah mendukung tiga desimal. Uang disimpan sebagai integer minor units, dua desimal, dengan pembulatan setengah ke atas pada bruto baris. Input ekstrem yang melampaui batas integer ditolak. Quotation asli dihitung ulang server: jumlah × harga satuan, dikurangi diskon nominal, ditambah pajak nominal baris; biaya tambahan header ditambahkan satu kali. Harga kosong bukan nol. Kurs tidak dihitung otomatis.

Finalisasi memerlukan minimal tiga vendor berbeda, satu versi penawaran per vendor, mata uang yang sama, item lengkap, serta jumlah dan satuan setara dengan permintaan. Kandidat dapat ditambah sampai sebelum CBE final. SCM menentukan satu pemenang berdasarkan harga atau kesesuaian spesifikasi dan mencatat alasannya; harga akhir mengikuti hasil evaluasi CBE untuk quotation yang dipilih. Revisi penawaran selalu membuat versi baru. CBE menyimpan snapshot identitas vendor, item, syarat, dan harga sehingga perubahan master berikutnya tidak mengubah CBE historis.

CBE baru menggunakan master PPN **11%** dari `config/scm.php` (`cbe_vat_percent`). Status **Non PKP** dipilih per versi penawaran/vendor pada tabel CBE; jika dicentang, PPN menjadi nol dan kolom nominal PPN nonaktif. Jika dilepas, PPN dihitung kembali. Diskon tambahan opsional diisi nominal, kosong berarti nol; diskon quotation tetap dihitung dan diskon tambahan tidak boleh melebihi nilai barang/jasa setelah diskon quotation. Dasar PPN adalah subtotal dikurangi seluruh diskon, ditambah biaya tambahan. PPN dibulatkan setengah ke atas ke dua desimal. Pajak nominal quotation asli tidak ditambahkan lagi pada hasil CBE. Server menghitung ulang angka saat simpan/finalisasi dan menyimpan tarif, pilihan Non PKP, diskon, serta hasil evaluasi dalam snapshot. Quotation asli tetap utuh; rincian penawaran terpilih membedakan total quotation asli dan total evaluasi CBE. CBE final lama tetap menggunakan snapshot lama dan tidak dihitung ulang.

Hasil pengadaan tampil pada PR/SR yang sama: CBE final beserta lampirannya dan penawaran harga vendor terpilih beserta file versi yang dipilih. CBE dan rincian penawaran terpilih dapat dicetak/disimpan PDF melalui browser. Jika file asli quotation belum diunggah sebelum finalisasi, layar menyatakan file belum dilampirkan dan tetap menyediakan rincian dari snapshot final. User pemilik permintaan dapat membaca hasil ini, tetapi tidak mengedit CBE, membuka draft CBE, atau mengunduh file penawaran vendor lain maupun versi yang tidak dipilih. CBE final memuat perbandingan harga seluruh vendor yang dievaluasi, sesuai akses yang diminta pengguna.

## Dokumen privat dan legalitas

### Form 005 untuk pengisian oleh vendor

Vendor baru membuka `/vendor` untuk mendaftar sendiri dan memperoleh Vendor ID. ID yang sama digunakan untuk membuka kembali data dan mengajukan pembaruan, sesuai langkah pada bagian 4 di atas. Undangan langsung untuk master vendor yang sudah ada tetap tersedia melalui langkah berikut.

Di **SCM > Vendor & dokumen**, simpan atau pilih vendor melalui **Edit**, lalu gunakan **Buat tautan undangan Form 005**. Pilih perusahaan tujuan dan, jika relevan, konteks transaksi konstruksi (nilai IDR dan durasi kontrak). Salin tautan yang muncul dan sampaikan melalui saluran organisasi; aplikasi tidak mengirim pesan otomatis. Vendor membuka halaman tersendiri di `/vendor/form-005/{token}/bagian/1`, tanpa akun/panel internal IK WorkDesk. Lima halaman mengikuti referensi Form 005: Data Perusahaan, Manajemen Mutu, K3L/HSE, Manajemen Proses, dan Pernyataan. Daftar dokumen mengikuti pilihan perusahaan Indonesia, luar Indonesia, atau pengganti NPWP/KTP untuk vendor Indonesia yang memenuhi pilihan tersebut; lihat [panduan dokumen vendor](SCM-VENDOR-PROFILES-2026-10-27.md).

Undangan berlaku 30 hari. Token acak 256 bit disimpan sebagai hash. Tautan baru mencabut undangan vendor yang belum diterima sebelumnya. Kiriman dan file hanya dapat diakses dengan token undangan yang valid atau akun SCM berizin. Link kedaluwarsa/dicabut menghasilkan HTTP 410 dengan petunjuk menghubungi SCM. Data dan file tidak disajikan lewat URL publik storage. Respons portal memakai no-store, no-referrer, noindex, nosniff, dan anti-frame. Upload PDF/JPG/PNG diperiksa ukuran, MIME, ekstensi dan signature; unggahan pengganti membuat versi baru.

Master mencakup akta pendirian, perubahan terakhir jika ada, perizinan SIUP/SUJK/UJK/SIUPAL, TDP, domisili, sertifikat asosiasi, surat pernyataan hukum, NPWP, PKP, referensi bank, KTP, company profile, pengalaman, katalog, dan daftar referensi. Perubahan akta wajib jika ada; salinan PKP tidak wajib bagi Non PKP. Surat pernyataan hukum dan referensi bank wajib hanya jika **ketiga** kondisi terpenuhi: konstruksi, nilai transaksi **di atas Rp100.000.000**, dan kontrak **lebih dari 3 bulan**. Konteks ditetapkan SCM dan tidak dapat diubah vendor. Referensi bank asli diserahkan ke SCM, sedangkan salinannya diunggah pada portal. Lampiran mutu/K3L/HSE mengikuti jawaban vendor; sertifikat yang relevan harus memiliki masa berlaku. Instruksi kerja coating, loading-unloading dan JSA tersedia sebagai lampiran jika relevan.

Vendor menyimpan setiap bagian, melengkapi unggahan, kemudian mengirim bagian Pernyataan. Server menahan kiriman yang belum lengkap dan mengunci edit setelah pengiriman. SCM membuka **Periksa Form 005** untuk membaca jawaban, mengunduh file, meminta perbaikan dengan alasan, menerima ke master, atau mencabut undangan. Penerimaan memperbarui profil dan menambahkan versi dokumen melalui aturan SCM yang sudah ada; kode vendor, status aktif, dan hak akses tetap dipertahankan. Perubahan profil internal sejak undangan dibuat menahan impor agar data tidak tertimpa. Jika impor gagal, transaksi profil/dokumen dibatalkan dan salinan file baru dibersihkan. Riwayat isian, unggahan, pengiriman ulang, dan pemeriksaan tersimpan pada event kiriman.

Persyaratan wajib Form 005 berlaku pada pengiriman kualifikasi ini. Checklist legalitas pengadaan yang sudah ada tetap mengikuti snapshot/kategori/proses dan ditinjau SCM; penerimaan Form 005 tidak otomatis menandai checklist diterima atau menyelesaikan pengadaan.

Disk `scm` menyimpan file di `storage/app/scm-private`, tanpa URL publik. Unduhan melalui route terautentikasi dengan otorisasi di setiap permintaan. PDF/JPG/PNG divalidasi ukuran, ekstensi, MIME, dan signature, memakai nama UUID. Batas default 10 MB; dapat diatur dengan `SCM_UPLOAD_MAX_KB=10240` pada environment. Batas unggahan PHP/web server juga harus mencukupi.

Versi dokumen tidak ditimpa. Nomor dokumen berupa string agar nol awal tidak hilang. Versi existing dapat dipakai ulang pada checklist yang cocok vendor dan jenis dokumennya. Kategori dan aturan historis tetap tersimpan. Checklist permintaan lama tetap menyalin persyaratan kategorinya saat pengumpulan dimulai; pada permintaan baru tanpa kategori, SCM menambahkan persyaratan per proses di tab Legalitas dengan alasan dan audit. Transaksi selesai terkunci. Dokumen wajib tanpa file, berstatus belum diterima/perlu perbaikan, atau kedaluwarsa menghalangi penyelesaian. Kedaluwarsa setelah selesai tidak membuka ulang sejarah. Kelengkapan ini administratif internal, bukan verifikasi keaslian ke instansi.

Jika vendor terpilih tidak memiliki SBU/NIB, SCM memilih **Gunakan NPWP dan KTP** pada tab Legalitas dan mengisi alasan. Kedua jenis harus aktif. NPWP dan KTP menjadi wajib, SBU/NIB menjadi opsional, dan persyaratan lain tetap berlaku. Sistem menyimpan ID jenis dokumen pada snapshot kasus dan mencatat audit `pengganti_npwp_ktp`; perubahan nama/kode master tidak melepas kewajiban kedua dokumen. Penyelesaian memerlukan NPWP dan KTP berstatus diterima dengan file yang tersedia serta tanggal yang masih berlaku bila diisi. SCM tidak dapat menonaktifkan kewajiban keduanya melalui perubahan persyaratan proses tersebut. Pengadaan selesai tetap terkunci. Legalitas hanya dikelola SCM sebagai syarat kelengkapan vendor dan tidak muncul sebagai lampiran hasil pengadaan untuk pembuat PR/SR.

## Pengujian

Antrean menyediakan tombol **Hapus semua filter** dan detail permintaan menampilkan langkah berikutnya sesuai tahap. Lampiran quotation dan CBE tersedia pada tab masing-masing. Di **Vendor & dokumen**, kiriman Form 005 yang menunggu pemeriksaan tampil sebelum daftar vendor; edit vendor memilih filter riwayat dokumen sekaligus membersihkan isian unggahan dari vendor sebelumnya. Filter riwayat dapat diubah tanpa mengganti vendor tujuan unggahan. Form 005 menyediakan **Simpan dan lanjut** agar perpindahan ke bagian berikutnya menyimpan jawaban terlebih dahulu. Pada pengaturan, perubahan jenis master dan pembatalan edit mengosongkan isian lama.

Bagian 5 Form 005 merangkum kekurangan isian bagian 1–4 serta dokumen wajib, hilang, kedaluwarsa, atau masa berlaku sertifikat mutu yang tidak sesuai. Tautan ringkasan menuju isian atau lampiran pada undangan yang sama. Ringkasan menggunakan validasi yang sama dengan pengiriman; pernyataan bagian 5 tetap diisi dan divalidasi saat dikirim. Status kiriman dan status akses tautan ditampilkan terpisah. SCM dapat membaca kiriman yang kedaluwarsa, tetapi penerimaan/perbaikan ditahan sesuai aturan akses yang sudah ada. Membuat undangan baru membuat formulir baru dan mencabut undangan lama yang belum diterima; isian historis tetap disimpan.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/Scm
php tests/run-scm-postgres.php --no-progress
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test
npm run build
```

PHPUnit utama menggunakan SQLite `:memory:` dan cache pengujian terpisah. Runner PostgreSQL hanya menerima target lokal, membuat database acak `ik_scm_test_*` dengan kredensial admin lokal yang sudah tersedia, menguji dua proses bersamaan, memverifikasi jumlah record database aplikasi, lalu membersihkan database uji. Jangan menjalankan file pengujian PostgreSQL langsung terhadap database aplikasi.

Pada 22 Oktober 2026, modul diterapkan atas persetujuan pengguna ke project IK WorkDesk yang sama di `http://10.1.20.97:8093`, direktori `/var/www/ik-workdesk`. Akun Khafafi memiliki tiga izin eksplisit. Data demo lokal tidak dimasukkan. Batas unggah SCM server diatur `SCM_UPLOAD_MAX_KB=2048` mengikuti batas PHP 2 MB. Pembuatan dan penerimaan PR/SR tidak memerlukan kategori. Backup, verifikasi data lama, dan hasil penerapan tercatat pada [rilis SCM](SCM-PENERAPAN-2026-10-22.md).

Untuk penerapan berikutnya, cadangkan database dan dokumen terlebih dahulu, jalankan migrasi/seeder awal secara terkontrol, bangun aset, dan bangun ulang cache konfigurasi/route. Hasil pengujian aktual dan batas QA browser tercatat pada [SCM-VERIFIKASI.md](SCM-VERIFIKASI.md).
## Persetujuan HO dan TTD profil

Pembuat PR/SR dan pejabat persetujuan menyimpan gambar TTD PNG/JPG sekali melalui menu pengguna **Tanda tangan profil**. File maksimal 2 MB dan 4096 × 4096 piksel, disimpan privat. Pembuat menekan **Tanda tangani** di Requested by. Tombol ini menyimpan draft terlebih dahulu apabila formulir sedang diedit. TTD tidak ditempel otomatis ketika profil atau formulir dibuka.

Dasar pemilihan pejabat pada form baru adalah pilihan **Pengajuan di atas Rp5 juta**, yang berada di luar lembar PR/SR dan tidak dicentang secara default. Kolom estimasi nominal tidak lagi ditampilkan atau diwajibkan. HO dan Project memakai master masing-masing pada APCA dan KPMOG; Project tidak mengambil rute HO sebagai cadangan. Tabel berikut menjelaskan rute awal HO, sedangkan penugasan berikutnya mengikuti master yang disimpan.

| Pilihan di atas Rp5 juta | Acknowledged by | Crosschecked by | Approved by |
| --- | --- | --- | --- |
| Tidak dicentang, pengaju bukan atasan yang ditetapkan | Atasan | CBO, boleh ditandatangani atau dilewati oleh CBO | CBO |
| Tidak dicentang, pengaju adalah atasan yang ditetapkan | Dilewati otomatis | Dilewati otomatis | CBO |
| Dicentang | Atasan; dilewati jika pengaju adalah atasan yang ditetapkan | CBO, wajib | COO 3 |

Jika pembuat adalah atasan yang ditetapkan pada perusahaan/departemennya, Acknowledged by otomatis dilewati dengan catatan audit. Jika pengajuan juga tidak memerlukan COO 3, Crosschecked by dilewati otomatis sehingga tahap berikutnya langsung Approved by oleh CBO pada rute tersebut. Keputusan Approved by tetap memerlukan tanda tangan CBO; nama pejabat tidak ditanam dalam kode. Tahap berikutnya hanya dapat diputuskan akun yang ditugaskan, mengikuti urutan. Pengaturan disalin menjadi penugasan pada tiap putaran pengajuan; penggantian pejabat dalam master tidak mengalihkan permintaan yang sedang berjalan.

Untuk permintaan lama yang masih menunggu Crosschecked by, administrator aktif atau pemilik izin konfigurasi SCM dapat memeriksa `php artisan scm:skip-manager-crosschecks --actor=ID_ADMIN --request=ID_PR`. Tanpa `--apply`, perintah hanya memeriksa. Opsi `--apply` melewati tahap opsional yang masih tertunda jika snapshot Acknowledged by sudah dilewati karena pengaju adalah atasannya sendiri dan Approved by masih ditugaskan kepada CBO yang sama. Penyesuaian menambah versi permintaan dan audit atas nama administrator pelaksana. Permintaan yang memerlukan COO 3, pengaju lain, tahap yang sudah ditandatangani, dan putaran lama dipertahankan.

Pengaturan berada di **SCM > Pengaturan > Master Approval**, pilih **Dokumen persetujuan: PR/SR**. HO dan Project memakai aturan masing-masing. Pilih Acknowledged by, Crosschecked by bila diperlukan, dan Approved by per perusahaan/divisi/form. CBO hanya untuk HO; Project mengikuti penandatangan yang dipilih pada master Project, tanpa mengambil rute HO. Aturan awal HO dan eskalasi nominal sebelumnya dipertahankan saat migrasi. Lihat [panduan master approval](SCM-APPROVAL-MASTER-2026-10-27.md). Budhy (COO III) sebelumnya telah ditetapkan sebagai penandatangan lanjutan pada pengaturan lokal IT untuk APCA dan KPMOG, menggunakan akun yang dibuat pengguna; pemetaan lokal tidak menyatakan akun atau rute yang sama sudah ada di server produksi.

Pejabat dapat mengembalikan permintaan untuk revisi dengan alasan minimal 10 karakter. TTD Requested by aktif dihapus dan tahap yang belum diputus dibatalkan. Tanda tangan dan keputusan putaran lama tetap tersimpan. Pengajuan ulang memerlukan TTD Requested by baru dan membuat putaran persetujuan baru mengikuti pilihan serta pengaturan saat itu.

Data lama tanpa pilihan eksplisit tetap membaca estimasi yang tersimpan sebagai aturan kompatibilitas. Saat draft lama diedit, pilihan awal mengikuti klasifikasi sebelumnya dan dapat diubah sebelum ditandatangani ulang. Pilihan eksplisit lebih diutamakan daripada estimasi historis. Penugasan yang sudah berjalan tidak diubah oleh migrasi atau penggantian pejabat. Estimasi lama dipertahankan untuk riwayat/anggaran CBE; permintaan baru tanpa estimasi tidak diberi nominal anggaran buatan.

Nama penanda tangan, waktu, dan versi gambar disimpan serta dicatat dalam audit. Penggantian gambar profil tidak mengubah dokumen yang sudah ditandatangani. Menyimpan ulang isi draft atau menambah lampiran permintaan menghapus tanda tangan aktif sehingga pembuat harus menandatangani kembali. Dokumen yang sudah dikirim tetap terkunci. Dokumen historis yang sudah dikirim sebelum fitur ini tetap dapat diproses SCM tanpa persetujuan atau TTD retroaktif.

Bagian **Keterangan kebutuhan** tidak ditampilkan lagi. Draft baru memperoleh judul daftar dari nama material; alasan kosong tidak menghalangi penyimpanan. Metadata judul/alasan/catatan pada dokumen lama tetap disimpan. TTD ini berupa gambar dan catatan tindakan akun pengguna, tanpa integrasi penyedia tanda tangan tersertifikasi.
