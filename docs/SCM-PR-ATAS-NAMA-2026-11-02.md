# Membuat PR atas nama pemohon lain

Akun **Sys Administrator/pemilik sistem** yang mempunyai izin membuat PR dapat memilih **Requestor** langsung pada form PR HO maupun Project. Akun dengan peran Administrator, Admin, Management, atau izin konfigurasi SCM saja tetap membuat PR atas nama sendiri. SR mengikuti perilaku sebelumnya.

## Penggunaan

1. Buka **Permintaan > PR**, pilih kebutuhan HO/Project, master kode Project bila diperlukan, lalu perusahaan.
2. Pada **Requestor**, pilih akun pemohon aktif yang memiliki izin PR/SR. Daftar menampilkan nama dan departemen untuk membantu pemilihan.
3. Departemen utama pemohon terisi otomatis. Pilihan departemen dan nama Project operasional mengikuti cakupan akses pemohon yang dipilih. Lengkapi rincian, lalu **Simpan draft**. Sysadmin dapat melengkapi lampiran dan mengedit draft yang dibuatnya.
4. Pemohon yang dipilih membuka PR tersebut melalui akunnya sendiri. Pemohon menandatangani **Requested by**, lalu mengajukan persetujuan. Sysadmin pembuat draft tidak mendapat tombol atau hak menggunakan tanda tangan pemohon tersebut.

PR tampil pada daftar pemohon dan pada daftar sysadmin yang membuatkannya. Pada dokumen tampil nama pemohon; keterangan di luar lembar mencatat siapa yang membuat permintaan dan untuk siapa. Nomor tetap sama saat draft diperbarui. Penomoran tidak mengubah penandatangan maupun aturan approval HO/Project.

## Penandatangan dan akses

Pratinjau approval memakai identitas pemohon terpilih, termasuk aturan atasan dan perlakuan pengajuan Manager. Saat diajukan, pengajuan berasal dari akun pemohon yang menandatangani dan mengikuti master perusahaan, departemen, HO/Project, dan PR yang sesuai.

Perubahan isi, pemohon, departemen, atau lampiran draft menghapus tanda tangan Requested by sebagaimana pengeditan draft sebelumnya. Pemohon terbaru perlu memeriksa dan menandatangani kembali. Pemohon hanya dapat diganti selama dokumen masih draft. Sysadmin lain tidak otomatis memperoleh hak mengedit draft yang dibuat akun lain.

Pembatasan juga diperiksa pada server: pengiriman `requester_id` lain oleh akun biasa ditolak, termasuk jika data dikirim di luar kontrol form. Pemohon nonaktif/tanpa izin PR ditolak, dan departemen harus termasuk cakupan pemohon. Pencabutan akses sysadmin menghentikan jalur akses sebagai pembuat draft untuk pemohon lain; akses SCM lain tetap mengikuti izinnya masing-masing.

## Pencatatan

Register nomor mempertahankan akun penerbit nomor. Permintaan menyimpan pemohon pada `requester_id`; relasi nomor mengidentifikasi pembuat awal. Audit penyimpanan tetap mencatat akun pelaksana dan pemohon yang dipilih. Audit penandatanganan/pengajuan mencatat akun pemohon yang melakukannya. Tidak ada tabel, migrasi, atau grant role baru untuk fitur ini.

Pemeriksaan: 274 tes fitur SCM/unit formatter/katalog akses, 2 tes konkurensi PostgreSQL, serta alur browser sysadmin sampai pemohon menandatangani dan mengajukan. Bukti tampilan dan interaksi ada dalam [laporan delivery](../anti-slop/delivery-pr-atas-nama-2026-11-02.md).
