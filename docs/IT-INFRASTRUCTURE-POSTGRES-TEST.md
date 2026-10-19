# Pengujian PostgreSQL Infrastruktur IT

Tanggal: 16 Oktober 2026. Pengujian dilakukan atas permintaan pengguna setelah delivery fondasi Tahap 2.

## Hasil

PostgreSQL 18.4 pada server yang digunakan aplikasi: PASS, 43 tests, 776 assertions. Seluruh suite fondasi dijalankan pada database PostgreSQL sementara terpisah. Tidak diperlukan perubahan migration, model, policy, atau seeder modul IT untuk meluluskan suite PostgreSQL.

```text
php tests/run-it-infrastructure-postgres.php
```

Keluaran pengujian:

```text
PostgreSQL 18.4: database uji terpisah dibuat.
{"tool":"phpunit","result":"passed","tests":43,"passed":43,"assertions":776,"duration_ms":36010}
Database uji sementara sudah dibersihkan.
PASS: schema public, daftar migration, dan jumlah record tabel utama tetap sama.
```

Suite mencakup pembuatan delapan tabel dan constraint PostgreSQL, pengisian 12 template/269 item, idempotensi permission dan checklist, CRUD melalui model, snapshot, finalisasi, riwayat, lifecycle/lisensi, foreign key gabungan, cakupan akses, serta penyelesaian tindak lanjut tanpa mengubah hasil final.

## Isolasi

Runner membaca konfigurasi koneksi existing di dalam proses, tanpa mencetak atau menyimpan credential. Runner membuat database kosong dengan nama acak `ik_it_test_` diikuti 16 digit heksadesimal. Credential diteruskan ke proses PHPUnit melalui environment, bukan argumen command atau file konfigurasi.

TestCase memverifikasi environment testing, driver PostgreSQL, nama database konfigurasi, dan current_database() sebelum memulai transaksi. Nama harus sama persis dengan penanda database sementara yang diberikan runner. Setiap test menggunakan transaksi yang di-rollback pada teardown. Tidak ada RefreshDatabase, migrate:fresh atau pemuatan seluruh folder migration.

Hanya sepuluh migration allowlist fondasi yang dimuat, sama dengan suite SQLite. Kedua migration APCA tidak dimuat. Runner memverifikasi OID dan pemilik database sementara sebelum membersihkannya setelah proses PHPUnit berakhir. Database utama tidak menjadi target migration atau seeder.

Sebelum dan sesudah suite, runner membandingkan fingerprint daftar kolom schema public, seluruh record daftar migration, serta jumlah record pada users, employees, departments, tickets, work_tasks, ltro_assets, ltro_mttr_records, atk_items, atk_requests dan outgoing_letters. Hasilnya sama. Pemeriksaan ini memverifikasi metadata dan jumlah record, bukan checksum seluruh isi setiap baris.

## Perubahan file untuk mendukung pengujian

| File | Perubahan |
| --- | --- |
| tests/run-it-infrastructure-postgres.php | Runner database PostgreSQL sementara, eksekusi PHPUnit, cleanup dan perbandingan metadata utama. |
| phpunit.it-infrastructure-postgres.xml | Konfigurasi suite PostgreSQL terisolasi tanpa credential tersimpan. |
| tests/Support/ItInfrastructureTestCase.php | Guard database PostgreSQL sementara serta transaksi/rollback per test; mode SQLite dipertahankan. |
| tests/Feature/ItInfrastructure/ItInfrastructureSchemaTest.php | Memeriksa constraint sesuai driver PostgreSQL atau SQLite. |
| docs/IT-INFRASTRUCTURE-SPEC.md | Memperbarui spesifikasi isolasi pengujian. |
| docs/IT-INFRASTRUCTURE-STAGE-2.md | Menghubungkan hasil delivery awal dengan verifikasi PostgreSQL. |
| docs/IT-INFRASTRUCTURE-POSTGRES-TEST.md | Laporan pengujian ini. |

Regresi SQLite setelah penyesuaian harness: PASS, 43 tests, 690 assertions. Perintah: `php vendor/phpunit/phpunit/phpunit -c phpunit.it-infrastructure.xml --do-not-cache-result`.

Pint, sintaks PHP pada tiga file PHP pengujian yang berubah, dan git diff --check: PASS.

Tidak ada perubahan kode Ticket Master, LTRO, ATK, route, navigasi atau tampilan. Database aplikasi belum menerima migration modul IT. Tahap UI tetap menunggu review.
