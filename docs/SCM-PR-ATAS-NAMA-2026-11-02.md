# Membuat PR atas nama pemohon lain

Akun aktif dengan `is_admin` atau role aktif **system-admin** mempunyai seluruh izin aplikasi dan akses semua departemen. Khafafi memakai kewenangan ini. Administrator biasa, Admin, Management, dan pengelola SCM tetap mengikuti izin operasional serta penugasannya.

## Penggunaan

1. Buka **Permintaan > PR** atau **SR**, pilih HO/Project, master kode Project bila diperlukan, lalu perusahaan.
2. Pada **Requestor**, pilih pemohon. Daftar mencakup seluruh akun dan menandai akun nonaktif.
3. Departemen utama pemohon terisi otomatis; superuser dapat menggantinya dengan departemen lain. Nama Project operasional dapat dipilih dari seluruh departemen. Lengkapi rincian, lalu **Simpan draft**.
4. Superuser dapat membuka dan mengedit draft semua pengguna, menambah lampiran, menandatangani Requested by menggunakan profilnya sendiri, lalu mengajukan persetujuan.
5. Pada **Persetujuan**, superuser melihat penugasan seluruh pengguna dan dapat menjalankan tahap yang sedang menunggu, termasuk CBE. Panel tindakan menampilkan penugasan asli dan nama penandatangan yang akan dipakai.

PR/SR tampil pada daftar pemohon dan seluruh superuser. Nomor dan pemohon tetap tersimpan sebagai identitas permintaan. Register nomor mempertahankan penerbit awal. Tanda tangan dan nama yang tampil adalah milik akun pelaksana yang sedang masuk.

## Penandatangan dan akses

Alur approval mengikuti identitas pemohon terpilih dan master perusahaan, departemen, HO/Project, serta PR/SR. Urutan tahap, validasi data, versi dokumen, tanda tangan profil yang tersedia, alasan revisi, dan kelengkapan dokumen tetap diperiksa.

Perubahan isi, pemohon, departemen, atau lampiran draft menghapus tanda tangan Requested by sehingga perlu ditandatangani kembali. Pemohon hanya dapat diganti selama dokumen masih draft.

Akun biasa tidak dapat mengirim `requester_id` pengguna lain, membuka draft di luar cakupannya, atau menjalankan persetujuan yang bukan penugasannya. Akun nonaktif tidak memperoleh kewenangan superuser. Pencabutan status superuser mengembalikan akses ke izin operasional yang tersisa.

## Pencatatan

Audit menyimpan akun pelaksana, pemohon/penugasan asli, serta penanda tindakan superuser. Tidak ada migrasi, pengubahan role pengguna, maupun penggantian tanda tangan profil untuk penerapan ini.

Lihat [laporan pengujian dan tampilan superuser](../anti-slop/delivery-superuser-2026-11-02.md).
