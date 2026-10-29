# Referensi alamat Indonesia

Data wilayah dan kode pos disimpan di aplikasi agar pilihan alamat tetap tersedia pada jaringan internal tanpa permintaan ke layanan pihak ketiga saat vendor mengisi formulir.

Sumber berlisensi MIT:

- [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah), commit `92cb44c90172f18b941ed9f652ee2106b462d73f`, berkas `db/wilayah.sql`.
- [cahyadsn/wilayah_kodepos](https://github.com/cahyadsn/wilayah_kodepos), commit `ba8497156c5cc9bcbfc527f7b8875d403eda2354`, berkas `json/wilayah_kodepos.min.json`.

Snapshot diambil 29 Oktober 2026. Nama file sumber kedua disimpan sebagai `postcodes.json` saat regenerasi. Hash sumber tersimpan pada `source-sha256.json`; teks lisensi disertakan. Snapshot memuat 38 provinsi, 514 kota/kabupaten, 7.285 kecamatan, dan 83.762 kelurahan/desa. Nama DKI Jakarta ditampilkan dalam bentuk pendek; awalan Kota Administrasi dihilangkan dari nama kota Jakarta. Kode wilayah tetap memakai sumber aslinya. Kode pos kosong pada sumber tidak ditebak; pengguna tetap dapat mengisinya.

`cities.json` memuat kota dan provinsinya. Berkas gzip per provinsi memuat kecamatan, kelurahan/desa, dan kode pos, dikelompokkan menurut kode kota. Pemilihan kode pos memakai kelurahan/desa, bukan satu kode untuk seluruh kota. Sumber merupakan referensi komunitas; pembaruan wilayah/kode pos harus ditinjau saat mengganti snapshot.

Untuk regenerasi, unduh kedua berkas pada commit yang dicatat dan kedua berkas LICENSE, lalu jalankan `python scripts/build-indonesia-address-data.py <direktori-sumber>`. Direktori tersebut harus berisi `wilayah.sql`, `postcodes.json`, `LICENSE-wilayah`, dan `LICENSE-postcodes`. Skrip hanya mengubah data referensi, tidak menjalankan SQL atau mengubah database aplikasi.
