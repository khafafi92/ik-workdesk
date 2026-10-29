# Alamat pada Form 005 vendor

Label isian dan dokumen tampil tanpa nomor referensi, misalnya **Kota**, **Provinsi**, dan **Salinan NPWP**. Nomor bagian navigasi serta identitas Form 005 tetap dipakai untuk perpindahan halaman.

Untuk vendor Indonesia, ketik atau pilih kota/kabupaten dari daftar. Provinsi terisi otomatis, misalnya Jakarta Selatan menjadi DKI Jakarta. Pilih kecamatan dan kelurahan/desa untuk mengisi kode pos. Mengganti kota atau kecamatan mengosongkan pilihan turunannya agar kode pos lama tidak tertinggal. Negara terkunci pada Indonesia dan ditegakkan kembali oleh server saat menyimpan.

Satu kota memiliki banyak kode pos, sehingga aplikasi tidak menebak satu kode untuk seluruh kota. Bila referensi belum memuat kode pos kelurahan, isian kode pos dapat diisi manual. Bila permintaan data wilayah gagal, formulir menyediakan isian manual dan tombol **Muat ulang wilayah**. Formulir tanpa JavaScript tetap menyediakan isian alamat manual.

Vendor luar Indonesia tetap memakai kota, provinsi, negara, dan kode pos manual. Alamat lama tanpa kode wilayah tetap dapat dibuka dan disimpan. Alamat dengan pilihan wilayah baru diverifikasi menurut relasi kota, kecamatan, dan kelurahan; nama provinsi, negara dan kode pos berasal dari referensi server.

Referensi wilayah dan kode pos disertakan dalam aplikasi, tanpa memerlukan koneksi internet vendor. Sumber, snapshot, lisensi dan cara regenerasi tercatat pada [referensi alamat](../resources/data/indonesia-addresses/README.md). Pembaruan ini tidak memerlukan migrasi database, seeder, atau perubahan hak akses.
