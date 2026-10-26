# Level Management dan Approval Legal

CBO, COO I, COO II, dan COO III menggunakan satu level utama **Management** pada User Management. Jabatan tetap dicatat pada Employee. Level ini tidak memberikan akses administrasi, menu operasional, atau kewenangan persetujuan secara otomatis.

Untuk CBO yang menyetujui pengajuan Service Desk ke Legal:

1. Buka User Management dan edit akun CBO.
2. Pilih level utama **Management**.
3. Pada **Akses Menu > Kewenangan Persetujuan**, centang **Approval Legal**, lalu simpan.

Checklist ini mengatur izin langsung `legal-tasks.approve`. Pemegang izin dapat membuka Work Logs, menerima notifikasi pengajuan Legal, menyetujui atau menolak pengajuan, serta melihat riwayat keputusan sesuai aturan yang sudah ada. Tidak perlu mencentang Work Logs yang juga memberikan kemampuan pengelolaan pekerjaan. COO tanpa tugas persetujuan Legal cukup menggunakan Management tanpa checklist tersebut.

Persetujuan PR/SR SCM tetap mengikuti nama penandatangan di Pengaturan SCM, termasuk penempatan CBO sebelum COO pada pengajuan yang memerlukan COO. Mengubah checklist Approval Legal tidak mengubah rute, urutan, atau riwayat approval SCM. Level Management sendiri tidak menambahkan seseorang sebagai penandatangan.

## Migrasi akun lama

Jalankan migrasi `2026_10_26_110000_add_management_access_level.php` melalui proses deployment biasa. Migrasi membuat role Management tanpa izin bawaan, memindahkan akun dengan role CBO lama ke Management, serta menyalin izin role CBO yang aktif menjadi izin langsung. Dengan demikian persetujuan Legal yang sudah diberikan tetap berjalan dan dapat dicabut melalui checklist. Role lain serta izin langsung yang sudah ada dipertahankan. Level utama lain yang telah tersimpan secara eksplisit tidak ditimpa, kecuali Requester/CBO.

Definisi role `cbo` lama tetap tersedia untuk kompatibilitas integrasi lama, tetapi tidak ditawarkan dalam pilihan level utama pada form user. Akun yang dimigrasikan tidak lagi mewarisi role tersebut. Izin yang sengaja diberikan melalui role tambahan lain tetap mengikuti role itu; checklist mengatur izin langsung pengguna.

Rollback migrasi ini tidak mengembalikan role CBO karena hal itu dapat mengaktifkan lagi kewenangan Legal yang sudah dicabut setelah migrasi. Cadangkan tabel akses sebelum penerapan.

## Penerapan lokal

Migrasi telah diterapkan pada database aplikasi lokal. Budhy, jabatan COO III, dipindahkan dari Requester ke Management. Checklist Approval Legal tidak diberikan kepadanya. Hak efektif seluruh pengguna dan hash tabel Service Desk, Work Logs, akses department, PR/SR, rute approval, riwayat approval, serta CBE diperiksa dan tetap sama.

Bukti pengujian dan pemeriksaan tampilan: `output/management-access-20261026/verification.md`.
