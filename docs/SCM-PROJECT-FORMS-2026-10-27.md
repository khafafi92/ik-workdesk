# Form PR/SR Project

Saat membuat PR atau SR, pengguna memilih **HO** atau **Project** sebelum memilih perusahaan. Jenis kebutuhan juga terlihat pada panel di luar kertas saat mengedit draft. Pilihan perusahaan tetap menentukan logo dan nomor; nomor yang sudah dicadangkan tidak berubah karena pergantian jenis kebutuhan.

HO mempertahankan formulir sebelumnya. Project mengikuti referensi pengguna:

- PR: `FRM-003 (Purchase Request) rev.3 (1).xls`, worksheet Sheet1. Identitas mencakup Date issue, Requestor, Department/Div, PO Customer, Required Arrival Date, Delivery Point, Job Order. Rincian berisi No., Size, Model/PN, Material, Description General, Qty, Unit, Remark. Empat tanda tangan, tanggal per penandatangan, penerimaan revisi 0–3, dan identitas revisi 3 berada di bawah tabel.
- SR: `FRM-046 (Service Request) rev.2.xlsx`, worksheet SR, area cetak A1:AX49. Judul Service Request and Materials; Requested/Acknowledge/Crosschecked/Approved, Department, No., Request Date, Required Arrival Date berada di bagian atas. Rincian berisi No., Description, Materials Needed, Qty, Unit, lalu Instruction to vendor dan Procedur required. Identitas revisi 2 berada di bagian bawah.

Worksheet FLOW CHART yang tersembunyi pada XLS dan prosedur di luar area cetak SR adalah isi dokumen referensi. Keduanya tidak mengubah aturan aplikasi. Alur dan penandatangan tetap menggunakan Master Approval PR/SR per perusahaan/divisi/jenis kebutuhan/form. Project tidak melibatkan CBO.

Nama Project bersifat opsional dan berada di luar kertas. Pilihannya berasal dari **Master Data > Projects**, hanya proyek aktif tanpa divisi pemilik atau dengan divisi yang dapat diakses pembuat. Daftar boleh diisi menyusul; tidak dibuat proyek percobaan pada produksi. Memilih proyek tidak mengganti penandatangan pada master approval.

Migrasi `2026_10_27_140000_add_project_request_form_fields.php` menambah referensi `work_project_id`, teks `instruction_to_vendor` dan `procedure_required`, serta `materials_needed` pada rincian. Seluruh field awalnya nullable; migrasi tidak mengubah isi permintaan, penugasan, tanda tangan atau master yang sudah ada.

SR Project menyimpan Description sebagai spesifikasi; nama ringkas item untuk proses SCM/penawaran diambil dari Description, maksimal 255 karakter. Materials Needed disimpan sendiri dan boleh kosong untuk jasa tanpa bahan. Pada draft lama yang belum memiliki field tersebut, nama material sebelumnya tetap ditampilkan dan dapat diedit. Edit draft tetap membatalkan tanda tangan aktif pembuat sesuai perilaku sebelumnya; pengajuan berjalan tidak diubah otomatis.

Tampilan lebar memakai struktur kertas Excel. Pada layar sempit, isian serta tanda tangan tersusun vertikal dan rincian menjadi kelompok per baris agar dapat dibaca dan diisi.
