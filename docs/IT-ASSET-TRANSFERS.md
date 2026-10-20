# Pemindahan pemakai aset IT

Di **Master Perangkat**, buka **Aksi perangkat > Pindahkan aset** atau buka halaman Edit dan pilih **Pindahkan aset**.

1. Pilih **Pemakai baru** dan user penerima, atau **Kembalikan ke stok**.
2. Isi tanggal perpindahan dan catatan serah terima.
3. Pilih **Simpan perpindahan**.

Pemakai baru mengubah status pemakaian menjadi Digunakan. Pengembalian ke stok mengosongkan pemakai dan mengubah status menjadi Stok / Belum Digunakan. Nomor aset, identitas perangkat, department pengelola, dan pemeriksaan yang sudah tersimpan tetap dipertahankan.

Tab **Riwayat** pada detail perangkat menampilkan tanggal perpindahan, pemakai lama, pemakai baru, petugas pencatat, waktu pencatatan, dan catatan serah terima. Riwayat dipaginasi dan tidak dapat diedit atau dihapus. Nama disalin saat pencatatan agar penggantian nama akun tidak menulis ulang riwayat. Penghapusan perangkat secara soft delete tetap menyimpan riwayatnya.

Mengubah pemakai lewat form edit juga menghasilkan catatan perpindahan dengan tanggal pencatatan dan keterangan bahwa perubahan dilakukan melalui edit perangkat. Menyimpan perubahan lain tidak menambah catatan perpindahan. Form yang terbuka sebelum perpindahan terakhir harus dimuat ulang agar tidak menimpa pemakai yang sudah diperbarui petugas lain.

Pemindahan mengikuti permission `it.devices.manage` dan cakupan department perangkat. Hanya perangkat aktif yang belum dihapuskan dan bukan objek ruangan yang dapat dipindahkan. Penerima harus mempunyai akun dan data karyawan aktif. Penerima yang sama, tanggal mendatang, tanggal sebelum perangkat terdaftar/perpindahan terakhir, serta catatan kosong ditolak.

Riwayat pemakaian sebelum fitur aktif tidak direkonstruksi. Pemakai saat ini menjadi asal perpindahan pertama yang benar-benar dicatat. Snapshot pemakai pada pemeriksaan lama tetap memakai data saat pemeriksaan.

## Database

Migration tambahan: `2026_10_20_010000_create_it_device_transfers_table.php`. Jalankan migration ini sebelum kode baru menerima request. Migration hanya membuat tabel `it_device_transfers`, tanpa mengubah pemakai atau data pemeriksaan existing. Pada lingkungan runtime dengan akses database terbatas, akun migrasi membuat tabel; runtime memerlukan SELECT dan INSERT pada tabel serta USAGE pada sequence. Cache view harus dibuat oleh akun web atau dibersihkan agar request web membangunnya dengan pemilik yang sesuai.

```shell
php artisan migrate --force --path=database/migrations/2026_10_20_010000_create_it_device_transfers_table.php
php artisan view:clear
```

Pengujian `ItDeviceTransferTest` mencakup perpindahan, pengembalian ke stok, alur tombol tabel/detail, pencatatan lewat edit, form kedaluwarsa, validasi penerima/tanggal, hak akses, rollback jika log gagal, riwayat read-only, pagination, dan preservasi pemeriksaan final.
