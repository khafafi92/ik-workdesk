# Form 005 dan dokumen vendor

Lima bagian kuesioner tetap mengikuti Book1.xlsx: Data Perusahaan, Manajemen Mutu, Manajemen K3L/HSE, Manajemen Proses, Pernyataan. Seluruh bagian diselesaikan melalui satu undangan khusus vendor, tanpa akun panel IK WorkDesk. Asal perusahaan awalnya Indonesia. Penggunaan sementara tetap pada jaringan lokal/VPN; domain publik diatur pengguna kemudian.

## Pilihan dokumen

- **Perusahaan Indonesia:** dokumen wajib Form 005 adalah Akta Pendirian, Perizinan, NPWP, dan KTP. Legalitas lain, Company Profile, List of Experience, dan Catalogue Products menjadi lampiran opsional. Perubahan akta, PKP, pernyataan hukum dan referensi bank tetap tersedia mengikuti kondisi yang relevan. Batas konstruksi tetap di atas Rp100 juta sekaligus lebih dari tiga bulan.
- **Perusahaan luar Indonesia:** Deed of Incorporation & Latest Amendments, Business License, Company Registry Certificate, Company Domicile Certificate, serta dokumen tambahan. Lampiran Quality dan HSE/Equipment/Operator muncul pada bagian 2–4 sesuai jawaban sistem, sertifikasi, penggunaan peralatan, blasting dan vessel loading. Nama dokumen mengikuti daftar pengguna, termasuk ISO 9001: 2008 Certificate. Tidak diminta NPWP/KTP, PKP atau legalitas Indonesia.
- **Vendor Indonesia tanpa dokumen perusahaan:** pilih pengganti NPWP dan KTP. Hanya dua lampiran tersebut diminta pada Form 005. Kuesioner lima bagian dan pemeriksaan SCM tetap berlangsung. Pilihan pengganti ditolak di server untuk perusahaan luar Indonesia.

Daftar referensi lama tetap tersimpan dalam riwayat bila sudah diunggah; daftar baru memakai List of Experience sesuai daftar tambahan pengguna. Dokumen yang tidak berlaku setelah perubahan asal/jenis vendor tidak dihapus. File tersebut tidak menghalangi kiriman atau diimpor ke master pada pilihan baru, dan diberi keterangan pada halaman pemeriksaan.

## Tampilan dan penyimpanan

Isian dikelompokkan per identitas, alamat/kontak, pimpinan/PIC dan informasi pemasok. Pertanyaan lanjutan hanya terlihat ketika relevan. Setiap unggahan berbentuk baris ringkas yang dibuka untuk nomor/tanggal/file. Baris unggahan dua kolom pada layar lebar dan satu kolom pada ponsel. Jumlah file terunggah ditampilkan berdasarkan file nyata.

Pilihan asal atau jenis dokumen harus disimpan sebelum unggahan untuk daftar baru. Halaman menjelaskan hal ini dan menyembunyikan daftar lama selama pilihan belum disimpan. Validasi server memakai jawaban tersimpan, bukan status kontrol di browser. File tetap privat, berversi, diperiksa isi/ekstensi, dan terikat satu undangan. Masa berlaku, pencabutan, versi bersamaan, kiriman terkunci, pemeriksaan dan rollback impor tetap berlaku.

SCM memeriksa kiriman sebelum profil dan dokumen masuk master. Kiriman baru yang sudah diterima menyimpan asal serta dasar dokumennya. Saat pengumpulan legalitas baru dimulai, sistem mengambil daftar tersebut; persyaratan khusus kategori tetap dipertahankan. Dokumen Indonesia menjadi opsional pada dasar asing/pengganti, sedangkan persyaratan khusus lain tidak dihapus. Dokumen tidak otomatis berstatus Diterima di pengadaan; SCM tetap memeriksa setiap item. Checklist pengadaan yang sudah berjalan tidak ditulis ulang ketika form vendor berubah. Form lama tanpa metadata jenis tetap memakai master kategori sebelumnya.

Perubahan 29 Oktober: email/telepon pimpinan opsional; tanggal sertifikasi dan lampiran kedaluwarsa diterima pada Form 005 dengan peringatan merah. Rincian tersedia di [Form 005 dan perusahaan pada CBE](SCM-FORM005-CBE-2026-10-29.md).

Migrasi `2026_10_27_170000_add_foreign_vendor_document_types.php` hanya menambah sepuluh jenis dokumen yang belum tersedia. Jenis lama, profil vendor, token undangan, isian, file, PR/SR, CBE dan persetujuan tidak diperbarui oleh migrasi.
