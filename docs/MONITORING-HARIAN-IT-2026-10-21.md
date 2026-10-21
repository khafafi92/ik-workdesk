# Monitoring Harian IT: lima pemeriksaan

Perubahan mengikuti permintaan 21 Oktober 2026. Pada **Infrastruktur IT > Monitoring Harian IT**, tersedia lima pintasan pengisian berikut.

| Pemeriksaan | Isian |
| --- | --- |
| Cek internet | Internet status, pilihan Status, Keterangan, Tanggal dan jam. |
| Cek firewall | FortiView status, pilihan Status, Quarantine berisi IP yang diblokir, Keterangan, Tanggal dan jam. |
| Cek ruang server | Suhu berupa angka dalam °C, Keterangan, Tanggal dan jam. |
| Cek backup | Nama File Station, Status, Keterangan, Tanggal dan jam untuk setiap baris. Mendukung lebih dari satu File Station. |
| Cek VM | Nama VM, Patch & update, Keterangan versi update, Keterangan, Tanggal dan jam untuk setiap VM. Mendukung lebih dari satu VM; rincian dapat ditutup dan dibuka. |

Status internet, FortiView, dan File Station menyediakan **Normal**, **Ada gangguan**, dan **Belum diperiksa**. Pilihan Ada gangguan mewajibkan keterangan. Pada VM, pilih **Up to date** atau **Update**; pilihan Update mewajibkan keterangan versi yang akan diperbarui. Nilai versi dibersihkan jika status draft diubah menjadi Up to date. Tidak ada hasil Normal atau Up to date yang diisi otomatis.

Quarantine menerima IPv4 dan IPv6, satu per baris; kolom dapat dikosongkan jika tidak ada IP. Daftar ini merupakan pencatatan manual, tidak memblokir IP pada firewall. Suhu menerima angka termasuk nol dan desimal. Tidak dibuat ambang alarm suhu karena pengguna belum menentukan ambangnya.

Tanggal dan jam dicatat per pemeriksaan, File Station, atau VM. Waktu wajib diisi ketika hasil sudah dipilih atau suhu diisi, dan tidak boleh berada di masa depan. Waktu pengecekan umum serta identitas sistem/perangkat yang sudah ada tetap dipertahankan. Timestamp per baris dinormalisasi ke zona waktu aplikasi dengan format `Y-m-d H:i:s` saat disimpan.

**Simpan Draft** tetap dapat menyimpan baris yang belum diperiksa. **Finalisasi** mewajibkan seluruh hasil terisi dan mengunci catatan. Progres langsung mengikuti penambahan baris. Nama File Station/VM yang sama, termasuk perbedaan huruf besar dan spasi tepi, ditolak dalam satu catatan.

## Riwayat dan hak akses

Schema tabel tidak berubah dan tidak memerlukan migration baru. Rincian baru disimpan pada JSON `checks` yang sudah tersedia. Penanda internal versi per baris membedakan format baru dari format lama; penanda ini tidak ditampilkan sebagai pilihan pengguna.

Catatan lama tidak dikonversi atau disimpan ulang. Halaman lihat dan ubah draft lama menggunakan rincian serta validasi sebelumnya, termasuk target/latency koneksi, hasil job backup, dan kondisi daya VM. **Periksa lagi** mempertahankan format sumber: sumber lama tetap memakai checklist lama, sedangkan sumber baru menampilkan format baru dengan hasil, IP quarantine, suhu, waktu baris, dan versi update dikosongkan. Gunakan lima pintasan utama untuk membuat catatan dengan format terbaru.

Permission pemeriksaan Infrastruktur IT, cakupan department IT, identitas pencatat dari server, pemeriksaan revisi draft, serta penguncian final tetap digunakan. Format catatan baru yang sudah tersimpan tidak dapat diturunkan melalui payload edit untuk melewati validasi versi update. Bantuan nama VM dari master tetap tersedia dan hasil aktual tetap harus diisi tim.

## Verifikasi

- SQLite memory terisolasi: **29 tes, 646 assertion, seluruhnya lulus**.
- PostgreSQL 18.4 terisolasi: **29 tes, 704 assertion, seluruhnya lulus**. Runner juga meluluskan pemeriksaan alokasi nomor aset pada database uji, membersihkan database sementara, dan mengonfirmasi snapshot schema/migration/jumlah record utama sumber tetap sama.
- Tes meliputi lima form create/view/edit, beberapa File Station/VM, IP valid/tidak valid, suhu nol/desimal/teks, tanggal per baris wajib dan masa depan, versi update wajib dan pembersihannya, nama duplikat, progres setelah tambah baris, pengulangan, finalisasi, format lama, department, revisi draft, serta akses petugas dan pembaca laporan.
- Browser lokal: lima form, pilihan hasil dan validasi isian kosong, angka suhu nol, dua File Station/VM, buka/tutup rincian, dialog hapus dan Escape, Tab menuju waktu, Batal melalui keyboard, serta rincian catatan lama tanpa input aktif. Tidak menambah atau menyimpan ulang catatan operasional saat QA.
- Lebar aktual browser **338, 416, 910, dan 1.405 CSS px** diperiksa; `scrollWidth` sama dengan `clientWidth`. Viewport sementara dikembalikan setelah pemeriksaan. Teks loading dan pesan kesalahan tetap tersedia. Log browser yang tertangkap pada pemeriksaan tidak berisi warning/error.
- Pint, lint PHP, dan whitespace lulus. Tidak ada perubahan aset frontend yang memerlukan build ulang.

Ini verifikasi modul monitoring, bukan pernyataan seluruh aplikasi bebas masalah. Pemeriksaan belum mencakup keyboard ponsel fisik atau zoom teks 200%; perintah zoom browser pada sesi kontrol tidak mengubah skala sehingga tidak dihitung sebagai verifikasi. Pengujian tidak menghubungkan aplikasi ke perangkat IT untuk menjalankan pemeriksaan, backup, atau patch secara otomatis. Perubahan diterapkan pada workspace/aplikasi lokal, belum dideploy ke `10.1.20.97:8093`.

## Bukti

- [Tes baru](../tests/Feature/ItInfrastructure/ItDailyMonitoringFormatTest.php) dan [regresi format sebelumnya](../tests/Feature/ItInfrastructure/ItDailyMonitoringTest.php).
- [SQLite](../output/it-daily-monitoring-format-20261021/sqlite.txt), [JUnit SQLite](../output/it-daily-monitoring-format-20261021/sqlite.xml), [PostgreSQL](../output/it-daily-monitoring-format-20261021/postgres.txt), [JUnit PostgreSQL](../output/it-daily-monitoring-format-20261021/postgres.xml).
- [Bukti form VM desktop](../output/it-daily-monitoring-format-20261021/vm-form-desktop.jpg): state form pengujian yang belum disimpan.
- [Delivery gate anti-slop](../anti-slop/audit-006-2026-10-21.md).

Menjalankan ulang di workspace ini:

```powershell
php vendor/phpunit/phpunit/phpunit -c output/it-daily-monitoring-format-20261021/sqlite-config.xml --filter ItDailyMonitoring --do-not-cache-result
php tests/run-it-infrastructure-postgres.php --filter ItDailyMonitoring
```

Konfigurasi SQLite yang disertakan memaksa environment testing, database memory, dan bypass cache konfigurasi aplikasi; path absolut di dalamnya mengacu pada workspace saat pengujian. Runner PostgreSQL membuat database sementara dan tidak menjalankan tes pada database operasional.
