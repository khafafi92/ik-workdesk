<?php

namespace App\Services\Scm;

use Illuminate\Support\Facades\Validator;

class Validation
{
    public static function make(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        array_walk_recursive($data, static function (&$value): void {
            if ($value === '') {
                $value = null;
            }
        });

        return Validator::make($data, $rules, array_replace([
            'required' => ':attribute wajib diisi.', 'present' => ':attribute harus disertakan.',
            'string' => ':attribute harus berupa teks.', 'integer' => ':attribute harus berupa nomor yang valid.',
            'boolean' => ':attribute harus bernilai ya/tidak.', 'array' => ':attribute harus berupa daftar.',
            'date' => ':attribute harus berupa tanggal yang valid.', 'email' => ':attribute harus berupa email yang valid.',
            'exists' => ':attribute tidak ditemukan atau sudah nonaktif.', 'unique' => ':attribute sudah digunakan.',
            'in' => ':attribute tidak termasuk pilihan yang tersedia.', 'distinct' => ':attribute tidak boleh berulang.',
            'after_or_equal' => ':attribute tidak boleh sebelum :date.',
            'max.string' => ':attribute maksimal :max karakter.', 'max.array' => ':attribute maksimal :max item.',
            'max.file' => ':attribute maksimal :max KB.', 'min.array' => ':attribute minimal :min item.',
            'file' => ':attribute harus berupa file.', 'mimes' => ':attribute harus berupa PDF/JPG/PNG.',
            'mimetypes' => ':attribute harus berupa PDF/JPG/PNG yang valid.',
        ], $messages), array_replace([
            'code' => 'Kode', 'name' => 'Nama', 'date' => 'Tanggal', 'kind' => 'Jenis permintaan',
            'title' => 'Judul', 'reason' => 'Alasan kebutuhan', 'number' => 'Nomor', 'department_id' => 'Departemen',
            'category_id' => 'Kategori', 'document_type_id' => 'Jenis dokumen', 'vendor_id' => 'Vendor',
            'file' => 'File', 'items' => 'Daftar item', 'lines' => 'Baris penawaran', 'currency' => 'Mata uang',
            'lead_time' => 'Waktu penyediaan', 'payment_terms' => 'Syarat pembayaran', 'extra' => 'Biaya tambahan',
            'customer_po' => 'PO Customer', 'job_order' => 'Job Order', 'items.*.brand' => 'Brand', 'items.*.item_reference' => 'Item',
            'company' => 'Perusahaan', 'delivery_point' => 'Delivery Point', 'items.*.size' => 'Size', 'items.*.model_part_number' => 'Model/PN',
            'estimated_total' => 'Estimasi total HO', 'requires_cbo3' => 'Pengajuan di atas Rp5 juta', 'acknowledger_id' => 'Atasan', 'cbo_id' => 'CBO', 'cbo3_id' => 'COO 3',
            'items.*.name' => 'Material', 'items.*.specification' => 'Description General', 'items.*.quantity' => 'Qty',
            'items.*.unit' => 'Unit', 'lines.*.description' => 'Spesifikasi ditawarkan',
            'lines.*.quantity' => 'Jumlah ditawarkan', 'lines.*.unit' => 'Satuan ditawarkan', 'lines.*.price' => 'Harga satuan',
            'lines.*.discount' => 'Diskon nominal', 'lines.*.tax' => 'Pajak nominal',
            'expires_at' => 'Tanggal kedaluwarsa', 'issued_at' => 'Tanggal terbit', 'valid_until' => 'Masa berlaku penawaran',
            'candidate_id' => 'Kandidat vendor', 'followup_status' => 'Status tindak lanjut', 'requested_at' => 'Tanggal diminta',
            'selected_vendor_id' => 'Vendor terpilih', 'quotation_ids' => 'Versi penawaran', 'quotation_ids.*' => 'Versi penawaran',
            'commercial_terms' => 'Pengaturan komersial CBE', 'commercial_terms.*.non_pkp' => 'Status Non PKP', 'commercial_terms.*.discount' => 'Diskon tambahan CBE',
            'provider_type' => 'Jenis penyedia', 'business_category' => 'Kategori usaha', 'is_active' => 'Status aktif',
            'has_expiry' => 'Penggunaan kedaluwarsa', 'is_required' => 'Kewajiban dokumen', 'status' => 'Status',
            'pic_name' => 'Nama PIC', 'pic_position' => 'Jabatan PIC', 'pic_phone' => 'Telepon PIC', 'pic_email' => 'Email PIC',
            'lines.*.request_item_id' => 'Item permintaan',
        ], $attributes));
    }
}
