# Pemeriksaan tindakan hapus vendor

- Mode During, menggunakan komponen tabel dan tombol SCM yang sudah ada.
- R-26 dan R-35 PASS: browser dengan database SQLite pengujian membatalkan konfirmasi, menyembunyikan vendor dengan riwayat pendaftaran, dan menyembunyikan vendor tanpa riwayat. Tidak ada kesalahan JavaScript.
- R-25 dan R-32 PASS: warna serta fokus mengikuti tombol SCM yang ada; label menyebut kode vendor dan konfirmasi memakai dialog browser.
- Layar 320 piksel PASS: tidak ada overflow pada halaman; tabel memakai wilayah gulir yang ada.
- Integritas: pengujian mencakup riwayat pendaftaran, pengadaan yang masih berjalan, versi lama dan akses tanpa hak. Arsip mempertahankan relasi historis dan mencegah pemilihan vendor untuk proses baru.
- Tidak ada perubahan palet, tipografi, dependensi, atau aset build.
