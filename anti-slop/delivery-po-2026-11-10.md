# Pemeriksaan Purchase Order, 10 November 2026

Mode During, mengikuti bahasa visual aplikasi SCM dan susunan contoh `PO Blank.xlsx`. Form ditujukan kepada petugas Purchasing Officer/SCM; dokumen adalah draft, tanpa tanda tangan palsu. Warna, font dan kontrol memakai gaya aplikasi, sedangkan tabel PO mengikuti kebutuhan cetak. ENERGY 1 / RHYTHM 1 / MOTION 1.

- PASS, kegunaan: nomor PO diisi manual; vendor, quotation dan nilai berasal dari CBE. Legalitas untuk vendor terpilih harus sudah selesai. Nama dan nominal dalam contoh browser adalah data uji di SQLite terpisah.
- PASS, validasi: pengujian menolak CBE yang belum disetujui, legalitas yang belum selesai, legalitas vendor lain, nomor kosong, nomor duplikat, versi usang, total yang tidak cocok, dan akses tanpa kewenangan. Pengujian integrasi menjalankan CBE, legalitas dan pembuatan PO melalui service aplikasi.
- PASS, interaksi: tab PO dibuka, nomor serta tujuan diisi, draft disimpan, tautan cetak dibuka, dan tombol cetak memanggil fungsi print. Nomor mendapat fokus keyboard dengan outline 3 px. Tidak ada error JavaScript pada pemeriksaan tersebut.
- PASS, responsif: tampilan diukur pada 1440 px dan 320 px, termasuk ukuran teks 200% pada 320 px; lebar dokumen tidak melebihi viewport. Tabel rincian dapat digulir dalam wadah tersendiri.
- PASS, cetak: PDF hasil browser memiliki satu halaman A4 portrait untuk contoh satu item; total dan label SELLER, SCM, SPV, Manager dan CBO terbaca. Area tanda tangan tetap kosong dan dokumen diberi label draft.
- PASS, kontras: teks #111827 pada putih 17,74:1; teks #4b5563 pada putih 7,56:1, dihitung dengan pemeriksa kontras skill.
- PASS, verifikasi aplikasi: build Vite, Pint untuk file baru, PHP syntax dan `git diff --check` lulus. Regresi CBE, pengadaan, akses tim, dan PO lulus 110 tes dengan 1.362 assertion; uji PO tambahan dilaporkan pada hasil tes terakhir di `tmp/po-final-tests.json`.
- PASS, ruang lingkup dan data: tidak mengubah akses CBE atau legalitas, tidak memodifikasi snapshot CBE final, dan tidak menjalankan migrasi pada database pengguna. Migrasi PO baru perlu diterapkan saat instalasi/deployment.
