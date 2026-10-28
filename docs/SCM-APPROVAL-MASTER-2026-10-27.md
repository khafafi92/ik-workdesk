# Master Approval PR/SR dan CBE

Semua pengaturan berada di **SCM > Pengaturan > Master Approval**. Pemilih **Dokumen persetujuan** menyediakan **PR/SR** sebagai pilihan awal dan **CBE** untuk aturan CBE. Form dan daftar mengikuti dokumen yang dipilih, sehingga satu panel mengelola keduanya. Pengelola harus memiliki izin `scm.configure`; level Management sendiri tidak memberikan izin mengubah master.

## Pemetaan aturan

Bagian berikut membahas pilihan PR/SR. Untuk CBE, lihat [Aturan dokumen CBE](#aturan-dokumen-cbe).

Setiap master mempunyai perusahaan, divisi/departemen, jenis kebutuhan **HO atau Project**, serta pilihan berlaku untuk **PR dan SR**, **PR**, atau **SR**. Aturan khusus PR/SR didahulukan dari aturan bersama pada perusahaan, divisi, dan jenis kebutuhan yang sama. Project tidak menggunakan aturan HO sebagai cadangan jika master Project belum ada.

Penandatangan dipilih dari akun aktif:

- Persetujuan SPV, bila diperlukan, berlangsung di sistem sebelum Acknowledged by.
- Acknowledged by dapat diisi Manager/atasan yang ditetapkan.
- Crosschecked by dapat dikosongkan, dibuat wajib, opsional, atau wajib hanya pada pengajuan di atas Rp5 juta.
- Approved by wajib diisi. Bila penandatangan untuk pengajuan di atas Rp5 juta berbeda, aktifkan pilihannya dan isi penandatangan tambahan tersebut.

Contoh sesuai arahan pengguna:

| Kebutuhan | Pembuat | Acknowledged by | Crosschecked by | Approved by |
| --- | --- | --- | --- | --- |
| Project | Merista | Manager yang ditetapkan | Kosong | COO II |
| HO, aturan awal tanpa eskalasi | Staf | Manager yang ditetapkan | CBO, opsional | CBO |
| HO, aturan awal di atas Rp5 juta | Staf | Manager yang ditetapkan | CBO, wajib | COO yang ditetapkan |

Penetapan contoh Project perlu akun Manager dan COO II yang benar-benar aktif. Nama pada contoh tidak membuat akun atau tanda tangan percobaan di server produksi.

CBO hanya terlibat untuk kebutuhan HO. CBO tidak ditawarkan sebagai penandatangan Project dan ditolak oleh validasi server jika dikirim melalui perubahan data klien. Pada HO, CBO harus hadir pada Crosschecked by atau Approved by. Jika Approved by diisi pejabat selain CBO, Crosschecked by CBO wajib meskipun pilihan umumnya opsional.

Master menjadi acuan nama penandatangan. Struktur Employee tetap menjelaskan jabatan dan hubungan atasan; pemetaan tersebut tidak mengganti penandatangan yang telah dipilih dalam master baru. Jabatan/level akses juga tidak memberikan hak melihat semua pengajuan. Pejabat hanya mendapat akses ke permintaan yang ditugaskan kepadanya.

## Membuat dan mengubah master

1. Pilih perusahaan, divisi, HO/Project, dan jenis form.
2. Isi penandatangan sesuai kewenangan divisi. Biarkan Crosschecked by kosong jika tidak diperlukan.
3. Simpan master approval.
4. Gunakan **Edit** untuk mengubah penandatangan. Untuk perusahaan/divisi/jenis kebutuhan/form yang berbeda, gunakan **Salin** atau **Master baru**.

Cakupan aturan tidak dipindahkan ketika sedang mengedit. Salin membuat draft konfigurasi baru yang tetap harus disimpan; salinan tidak langsung mengganti aturan lain. Master yang sudah ada tidak ditimpa oleh penyimpanan draft baru dengan cakupan sama. Perubahan bersamaan oleh pengelola lain ditolak dengan pesan agar master dibuka ulang.

## Pemakaian pada PR/SR

Pembuat memilih **Jenis kebutuhan: HO atau Project** pada panel alur persetujuan di luar lembar formulir. Nama penandatangan ditampilkan dari master yang sesuai. Setelah menyimpan dan menandatangani Requested by, pembuat menekan tombol pengajuan sesuai jenis kebutuhan.

Form PR/SR tetap menggunakan susunan Excel dan empat kolom tanda tangan: Requested by, Acknowledged by, Crosschecked by, Approved by. Tidak ditambahkan kolom Project, SPV, atau persetujuan lainnya ke kertas. SPV tercatat pada riwayat sistem. Crosschecked by yang tidak dipakai tidak memiliki penugasan dan tampil kosong pada kertas.

Pengajuan yang sudah diajukan menyimpan penugasan dan tanda tangan sendiri. Mengubah master hanya memengaruhi pengajuan berikutnya; pengajuan berjalan tidak dialihkan atau ditandatangani ulang otomatis. Revisi yang dikembalikan kepada pembuat mengikuti master terbaru ketika diajukan kembali, dengan putaran dan tanda tangan baru.

Mengubah jenis kebutuhan melalui Edit draft menghapus tanda tangan pembuat yang aktif, seperti perubahan isi draft lainnya. Setelah memilih jenis yang benar, pembuat perlu menandatangani kembali. Jenis kebutuhan dan penandatangan tidak dapat diubah melalui manipulasi state tampilan setelah pengajuan.

## Migrasi konfigurasi sebelumnya

Migrasi `2026_10_27_110000_create_scm_approval_policies.php` menambahkan `need_type` pada permintaan dan tabel master approval. Permintaan lama mempertahankan nilai HO sesuai alur aplikasi sebelumnya; pengajuan Project dipilih secara eksplisit oleh pembuat. Aturan atasan/CBO/COO lama disalin sebagai master HO bersama PR dan SR dengan nama penandatangan, versi, dan aturan nominal awal yang sama.

Field teknis lama `approval_scope` dipertahankan untuk kompatibilitas alur dokumen empat kolom. Pemilihan kebijakan HO/Project menggunakan `need_type`, sehingga Project tidak ditentukan dari teks nomor formulir, nama departemen, ataupun posisi karyawan saja.

Tabel permintaan, langkah approval, tanda tangan, nomor, vendor, CBE, role, dan izin lama tidak ditulis ulang oleh migrasi. Definisi master yang sudah diedit melalui pengaturan baru tidak ditimpa oleh pembaruan konfigurasi API lama. Rollback yang menghapus data master baru/Project yang sudah digunakan ditolak.

Untuk instalasi yang belum memiliki field struktur Employee, migrasi `2026_10_27_100000_add_employee_reporting_structure.php` juga diperlukan. Data jabatan dan atasan yang belum dipetakan tetap kosong sampai ditetapkan.

## Aturan dokumen CBE

Diperbarui 29 Oktober 2026. Setelah migrasi `2026_10_28_100000_create_scm_cbe_approvals.php` diterapkan, buka **SCM > Pengaturan > Master Approval**, lalu pilih **Dokumen persetujuan: CBE**.

1. Pilih perusahaan APCA atau KPMOG.
2. Isi **Acknowledged By: Meyfi**, **Crosschecked By: Dipta**, dan **Approved By: Lenaldy** dengan akun aktif yang benar.
3. Tekan **Simpan master approval**. Gunakan **Edit** pada baris perusahaan untuk perubahan berikutnya. Jika pengelola lain sudah menyimpan perubahan, buka ulang master sebelum menyimpan lagi.
4. Siapkan aturan perusahaan lain bila digunakan. CBE mengikuti perusahaan PR/SR, tanpa pilihan HO/Project atau divisi pada aturan CBE. Ganti **Dokumen persetujuan** kembali ke PR/SR untuk mengatur persetujuan permintaan.

Prepared By mengikuti akun SCM yang menandatangani dan mengajukan CBE; Imam menjadi Prepared By ketika Imam sendiri yang melaksanakan langkah itu. Tiga pejabat pada master dan akun Prepared By harus berbeda. Sistem tidak mengambil gambar TTD pejabat lain secara otomatis.

Setiap akun menyimpan gambar TTD sendiri lewat tautan profil pada form/persetujuan. SCM memeriksa draft kemudian memilih **Tanda tangani dan ajukan CBE**. CBE menjadi final dan isi perbandingannya terkunci. Meyfi, Dipta, dan Lenaldy membuka **Permintaan > Persetujuan**, memilih **CBE**, memeriksa dokumen, lalu menandatangani berurutan sesuai giliran. Nama, tanggal dan versi gambar TTD disimpan pada tindakan masing-masing akun. Pengumpulan legalitas CBE baru menunggu keempat tanda tangan lengkap.

Mengubah master tidak mengganti penugasan CBE yang sedang berjalan. Mengganti gambar profil tidak mengubah tanda tangan dokumen yang sudah ditandatangani. CBE lama tidak diberi TTD otomatis. CBE final lama yang belum masuk legalitas dan belum mempunyai penugasan dapat diajukan secara eksplisit oleh akun SCM pembuat aslinya, dengan harga dan snapshot tetap terkunci.

Langkah pengguna lengkap, termasuk vendor dan penanganan pesan kesalahan, tersedia di [panduan SCM](SCM.md#panduan-penggunaan-langkah-demi-langkah).
