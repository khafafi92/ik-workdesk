# CBE dan Rekap Material PR

Menu **SCM > Rekap Material PR** menampilkan satu baris per item PR yang sudah mempunyai CBE final dan vendor terpilih. Kolomnya: No, PR, Vendor, Size, Model/PN, Material, Description general, dan Harga satuan. Material mengambil nama item PR; Description general mengambil spesifikasi item PR.

Nama vendor, rincian item, dan harga satuan memakai snapshot CBE final. Harga satuan adalah harga quotation sebelum diskon tambahan, biaya tambahan, dan PPN pada tingkat CBE. Grand Total vendor terpilih ditampilkan pada lembar CBE, termasuk halaman persetujuan dan cetak. Rekap menyediakan pencarian nomor PR dan paginasi 25 item. Akses tetap mengikuti permintaan sendiri atau lingkup departemen SCM.

PROJECT pada CBE mengambil nama master kode project, kemudian master Work Project atau lokasi bila master kode tidak tersedia. Kode lama dalam nomor PR/SR juga dapat digunakan untuk menemukan master. COMP-19E001 menggunakan nama LTRO-1B. Nama project disimpan dalam snapshot saat finalisasi baru. Dokumen lama tanpa nama project memperoleh fallback tampilan tanpa menulis ulang snapshot.

Nomor CBE menjadi `CBE-<nomor PR/SR>`, misalnya `CBE-004/COMP-19E001/PR/XI/2026`. Migrasi `2026_11_03_100000_align_cbe_document_identity` memperbarui nomor CBE lama dan nama default LTRO pada master COMP-19E001. Snapshot, harga final, versi, dan persetujuan tidak diubah. Migrasi sudah dijalankan pada PostgreSQL lokal setelah pencadangan; tujuh CBE diverifikasi. Server lain memerlukan migrasi yang sama saat deployment.

Validasi: 121 tes SCM terkait lulus, Laravel Pint lulus, build Vite lulus, render HTTP rekap dan cetak CBE menghasilkan 200. Fixture SQLite terpisah dengan 28 item memverifikasi paginasi, pencarian, penghapusan filter, pilihan vendor dari snapshot, harga, dan pembatasan akses. Tes Livewire memverifikasi kegagalan query dan pemulihan melalui Muat ulang data.

Pemeriksaan visual browser belum dilakukan. Computer-use berhenti karena tidak dapat memastikan URL browser Windows untuk pemeriksaan kebijakannya. Laporan anti-slop mencatat pemeriksaan yang lulus dan pemeriksaan visual yang masih tertunda; tidak ada klaim click-through browser.

## COMMENT dan Differences

COMMENT sekarang merupakan kolom teks pada editor draft CBE. Simpan melalui **Simpan draft CBE**. Komentar tidak mengubah catatan PR; nilainya disimpan terpisah, dapat dikosongkan, dan ikut snapshot ketika finalisasi. CBE final serta halaman cetak tetap hanya baca. CBE lama tanpa komentar terpisah tetap menampilkan catatan PR dari snapshot dokumen tersebut.

Total Budget mengambil estimasi PR/SR dalam IDR. Jika nilainya belum diisi, Differences kosong dan lembar menjelaskan penyebabnya. Nilai anggaran nol tetap dihitung sebagai nol. Untuk penawaran mata uang asing tanpa konversi, selisih menjelaskan **Kurs IDR belum dicatat**. Tidak ada perubahan nilai anggaran, aturan persetujuan, atau rumus selisih.

Migrasi tambahan: `2026_11_03_110000_add_comment_to_scm_cbes`. Validasi perubahan ini: 104 tes CBE/pengadaan lulus, Pint lulus, dan build Vite lulus. Pengujian mencakup simpan dan buka ulang komentar multiline, pengosongan, batas panjang, penolakan akses pemohon, finalisasi, tampilan cetak, kompatibilitas dokumen lama, anggaran kosong, anggaran nol, dan mata uang asing.

Migrasi komentar sudah diterapkan pada PostgreSQL lokal menggunakan akun migrasi yang tersedia. Tujuh CBE existing dibandingkan dengan cadangan dan seluruh atribut sebelumnya tetap sama.
