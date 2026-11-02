# Melihat Vendor ID untuk membuka formulir

Pada **SCM > Data Vendor**, akun Sys Administrator dapat melihat **Vendor ID Portal** berawalan `VND-` dan menekan **Buka formulir** untuk melanjutkan pengisian melalui alur portal yang sudah ada. Klik teks ID untuk memilih seluruhnya, lalu salin bila perlu diberikan kepada pengelola data vendor.

Nomor pendaftaran **Form #...** dan **Kode Master Vendor** mempunyai label terpisah. Kode master diterbitkan setelah profil diterima SCM. Kedua identitas ini berbeda dari Vendor ID untuk membuka portal.

Vendor ID baru disimpan terenkripsi dan hanya ditampilkan kepada superuser aktif. Administrator biasa atau SCM operasional tetap dapat memeriksa data sesuai izin sebelumnya, tanpa melihat atau mengganti Vendor ID. Halaman yang menampilkan ID tidak boleh disimpan cache. JSON model dan riwayat penggantian tidak memuat ID tersebut.

## Pendaftaran lama

Sebelumnya sistem hanya menyimpan hash Vendor ID, sehingga ID lama tidak dapat dibaca kembali. Penerapan ini tidak mengganti ID lama secara otomatis.

1. Temukan vendor pada Data Vendor.
2. Tekan **Terbitkan Vendor ID pengganti** dan konfirmasikan perubahan akses.
3. ID pengganti akan tampil. Tekan **Buka formulir** untuk melanjutkan.

Setelah dikonfirmasi, ID dan tautan formulir lama berhenti berlaku. Jawaban, dokumen, status pendaftaran, dan hubungan ke Master Vendor dipertahankan. Riwayat mencatat akun superuser yang menerbitkan pengganti. Akses yang telah dicabut tidak dapat dipulihkan melalui tombol ini. Penggantian memakai formulir terbaru dan pemeriksaan versi untuk mencegah perubahan dari halaman yang sudah kedaluwarsa.

Form yang sudah dikirim tetap mengikuti pemeriksaan SCM. Pembaruan setelah diterima mengikuti alur portal yang membuat draft pembaruan, tanpa menulis ulang kiriman lama.

## Status dan langkah berikutnya

Data Vendor menampilkan petunjuk sesuai status masing-masing pendaftaran:

- **Draft / belum dikirim:** buka **Lengkapi dan kirim formulir**, lengkapi bagian 1 sampai 4 dan dokumen wajib, lalu isi serta konfirmasikan **Bagian 5: Pernyataan**. Tekan **Kirim form dan dokumen ke SCM**. Jumlah bagian tersimpan dan unggahan tidak memastikan kelengkapan.
- **Terkirim / menunggu pemeriksaan:** SCM membuka **Periksa kiriman**, memeriksa jawaban/lampiran, lalu memilih **Terima profil dan dokumen ke master** atau **Minta perbaikan vendor**.
- **Perlu perbaikan:** catatan SCM tampil di daftar; vendor memperbaiki formulir dan mengirim kembali melalui Bagian 5.
- **Diterima SCM:** profil masuk Master Vendor. Untuk pendaftaran melalui Portal Vendor, tombol **Buat draft pembaruan** memulai pembaruan melalui Vendor ID. Vendor undangan mengikuti pembaruan/undangan dari SCM.
- **Akses dicabut atau kedaluwarsa:** petunjuk menjelaskan akses yang diperlukan; jawaban/lampiran tetap tersedia untuk dilihat.

Pada bagian 1 sampai 4 tersedia tautan **Periksa kelengkapan dan kirim (Bagian 5)**. Simpan perubahan sebelum berpindah bagian. Bagian 5 menunjukkan isian/dokumen yang kurang dan menolak pengiriman yang belum lengkap. Setelah pengiriman, isian terkunci selama pemeriksaan. Hasil pemeriksaan yang dimulai dari Data Vendor mempertahankan tautan kembali ke Data Vendor.
