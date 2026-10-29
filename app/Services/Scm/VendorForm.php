<?php

namespace App\Services\Scm;

class VendorForm
{
    public const TITLES = [1 => 'Data Perusahaan', 2 => 'Manajemen Mutu', 3 => 'Manajemen K3L / HSE', 4 => 'Manajemen Proses', 5 => 'Pernyataan'];

    public const INDONESIA_LEGAL = ['AKTA_PENDIRIAN', 'AKTA_PERUBAHAN', 'IZIN_USAHA', 'TDP', 'DOMISILI', 'SERTIFIKAT_ASOSIASI', 'PERNYATAAN_HUKUM', 'NPWP', 'PKP', 'REFERENSI_BANK', 'KTP'];

    public const ADDITIONAL_DOCUMENTS = ['COMPANY_PROFILE', 'DAFTAR_PENGALAMAN', 'KATALOG_PRODUK'];

    public static function fields(int $step, array $answers = []): array
    {
        $fields = match ($step) {
            1 => [
                'name' => ['Nama lengkap perusahaan', 'text', true],
                'previous_name' => ['Nama perusahaan sebelumnya (jika ada)', 'text'],
                'address' => ['Alamat', 'textarea', true], 'city' => ['Kota', 'text', true],
                'province' => ['Provinsi', 'text', true], 'district' => ['Kecamatan', 'text'], 'village' => ['Kelurahan/desa', 'text'],
                'postcode' => ['Kode pos', 'text', true],
                'country' => ['Negara', 'text', true], 'phone' => ['Telepon perusahaan', 'text', true],
                'fax' => ['Fax (jika ada)', 'text'], 'email' => ['Email perusahaan', 'email', true],
                'website' => ['Website (jika ada)', 'url'],
                'ceo_name' => ['Nama pimpinan', 'text', true], 'ceo_email' => ['Email pimpinan', 'email', true],
                'ceo_phone' => ['Telepon pimpinan', 'text', true], 'pic_name' => ['Nama contact person', 'text', true],
                'pic_position' => ['Jabatan contact person', 'text', true], 'pic_email' => ['Email contact person', 'email', true],
                'pic_phone' => ['Telepon contact person', 'text', true],
                'supplied_before' => ['Pernah memasok barang/jasa kepada perusahaan tujuan?', 'choice', true],
                'supplied_description' => ['Barang/jasa yang pernah dipasok (jika ya)', 'textarea'],
                'has_amendments' => ['Apakah ada perubahan akta perusahaan?', 'choice', true],
                'non_pkp' => ['Apakah perusahaan Non PKP?', 'choice', true],
            ],
            2 => [
                'quality_system' => ['Memiliki sistem manajemen mutu terdokumentasi?', 'choice', true],
                'quality_standards' => ['Standar yang digunakan (jika ada)', 'textarea'],
                'quality_certified' => ['Sistem telah disertifikasi badan terakreditasi?', 'choice', true],
                'certifying_body' => ['Nama badan sertifikasi (jika tersertifikasi)', 'text'],
                'quality_expiry' => ['Tanggal berakhir sertifikat (jika tersertifikasi)', 'date'],
                'certification_progress' => ['Rencana/progres sertifikasi jika belum tersertifikasi', 'textarea'],
                'quality_remarks' => ['Informasi tambahan manajemen mutu', 'textarea'],
            ],
            3 => [
                'ohs_system' => ['Memiliki sistem manajemen K3 formal?', 'choice', true],
                'ohs_standards' => ['Standar/pedoman K3 yang digunakan', 'textarea'],
                'ohs_certified' => ['Sistem K3 telah disertifikasi?', 'choice', true],
                'health_policy' => ['Memiliki kebijakan kesehatan terdokumentasi?', 'choice', true],
                'drug_policy' => ['Memiliki kebijakan obat-obatan dan alkohol?', 'choice', true],
                'risk_assessment' => ['Memiliki prosedur identifikasi bahaya, penilaian risiko, dan pengendalian?', 'choice', true],
                'ohs_remarks' => ['Informasi tambahan K3', 'textarea'],
                'environment_system' => ['Memiliki sistem manajemen lingkungan terdokumentasi?', 'choice', true],
                'environment_standards' => ['Standar/pedoman lingkungan yang digunakan', 'textarea'],
                'environment_certified' => ['Sistem lingkungan telah disertifikasi?', 'choice', true],
                'environment_offence' => ['Pernah menerima sanksi atau teguran dari instansi terkait lingkungan?', 'choice', true],
                'environment_details' => ['Rincian masalah lingkungan (jika ya)', 'textarea'],
                'environment_remarks' => ['Informasi tambahan lingkungan', 'textarea'],
                'equipment_control' => ['Cara memastikan peralatan terdaftar, tersertifikasi, diinspeksi, dan dirawat dengan aman', 'textarea', true],
                'operator_licensed' => ['Operator memiliki lisensi K3 dan buku kerja sesuai peralatan?', 'choice', true],
            ],
            4 => [
                'receipt_inspection' => ['Memiliki sistem inspeksi saat penerimaan material?', 'choice', true],
                'nonconformity_system' => ['Memiliki sistem penanganan ketidaksesuaian?', 'choice', true],
                'process_notes' => ['Penjelasan prosedur coating, loading-unloading, dan JSA; jelaskan jika tidak relevan', 'textarea', true],
            ],
            5 => [
                'full_name' => ['Nama jelas pemberi pernyataan', 'text', true],
                'position' => ['Jabatan', 'text', true], 'date' => ['Tanggal pernyataan', 'date', true],
                'confirmed' => ['Saya menyatakan informasi dan dokumen yang disampaikan benar dan lengkap.', 'confirm', true],
            ],
            default => [],
        };
        if (self::origin($answers) === 'foreign') {
            if ($step === 3) {
                $fields['equipment_used'] = ['Menggunakan peralatan lifting / hoisting?', 'choice', true];
            }
            if ($step === 4) {
                $fields['blasting_used'] = ['Ada proses internal blasting?', 'choice', true];
                $fields['vessel_loading'] = ['Ada pekerjaan load in pipe from vessel?', 'choice', true];
            }
        }

        return $fields;
    }

    public static function documents(): array
    {
        return [
            'AKTA_PENDIRIAN' => ['Salinan Akta Pendirian Perusahaan', 1, 'always', false],
            'AKTA_PERUBAHAN' => ['Perubahan akta terakhir (jika ada)', 1, 'amendments', false],
            'IZIN_USAHA' => ['Perizinan SIUP/SUJK/UJK/SIUPAL yang masih berlaku', 1, 'always', true],
            'TDP' => ['Salinan Tanda Daftar Perusahaan (TDP)', 1, 'always', false],
            'DOMISILI' => ['Salinan Surat Keterangan Domisili', 1, 'always', false],
            'SERTIFIKAT_ASOSIASI' => ['Sertifikat asosiasi perusahaan/profesi yang masih berlaku', 1, 'always', true],
            'PERNYATAAN_HUKUM' => ['Pernyataan tidak dalam pengawasan pengadilan, tidak bangkrut, usaha tidak dihentikan, dan tidak menjalani sanksi pidana', 1, 'construction', false],
            'NPWP' => ['Salinan NPWP', 1, 'always', false],
            'PKP' => ['Salinan PKP', 1, 'pkp', false],
            'REFERENSI_BANK' => ['Surat referensi bank asli', 1, 'construction', false],
            'KTP' => ['KTP penanggung jawab', 1, 'always', false],
            'COMPANY_PROFILE' => ['Company Profile', 1, 'always', false],
            'DAFTAR_PENGALAMAN' => ['List of Experience / daftar pengalaman', 1, 'always', false],
            'KATALOG_PRODUK' => ['Catalogue Products / katalog produk', 1, 'always', false],
            'DAFTAR_REFERENSI' => ['Daftar referensi perusahaan', 1, 'always', false],
            'MANUAL_MUTU' => ['Manual / sistem manajemen mutu', 2, 'quality_system', false],
            'SERTIFIKAT_MUTU' => ['Sertifikat manajemen mutu', 2, 'quality_certified', true],
            'MANUAL_K3' => ['Kebijakan, manual, dan SOP K3', 3, 'ohs_system', false],
            'SERTIFIKAT_K3' => ['Sertifikat sistem K3', 3, 'ohs_certified', true],
            'KEBIJAKAN_KESEHATAN' => ['Kebijakan kesehatan', 3, 'health_policy', false],
            'KEBIJAKAN_ALKOHOL' => ['Kebijakan obat-obatan dan alkohol', 3, 'drug_policy', false],
            'PENILAIAN_RISIKO' => ['Prosedur penilaian risiko', 3, 'risk_assessment', false],
            'MANUAL_LINGKUNGAN' => ['Kebijakan, manual, dan SOP lingkungan', 3, 'environment_system', false],
            'SERTIFIKAT_LINGKUNGAN' => ['Sertifikat sistem lingkungan', 3, 'environment_certified', true],
            'KONTROL_PERALATAN' => ['Prosedur, bukti perawatan, dan surat izin peralatan (jika relevan)', 3, 'optional', false],
            'LISENSI_OPERATOR' => ['Lisensi operator K3', 3, 'operator_licensed', false],
            'PROSEDUR_COATING' => ['Instruksi kerja coating process (jika relevan)', 4, 'optional', false],
            'PROSEDUR_LOADING' => ['Instruksi kerja loading-unloading (jika relevan)', 4, 'optional', false],
            'JSA' => ['Job Safety Analysis / JSA (jika relevan)', 4, 'optional', false],
            'FOREIGN_DEED' => ['Deed of Incorporation & Latest Amendments', 1, 'always', false],
            'FOREIGN_LICENSE' => ['Business License', 1, 'always', false],
            'FOREIGN_REGISTRY' => ['Company Registry Certificate', 1, 'always', false],
            'FOREIGN_DOMICILE' => ['Company Domicile Certificate', 1, 'always', false],
            'HSE_POLICY' => ['HSE Policy', 3, 'ohs_system', false],
            'LIFTING_HOISTING' => ['Lifting & Hoisting Equipment', 3, 'equipment_used', false],
            'DAILY_INSPECTION' => ['Daily Inspection Form', 3, 'equipment_used', false],
            'EQUIPMENT_INSPECTION' => ['Equipment Certificate of Inspection', 3, 'equipment_used', false],
            'INTERNAL_BLASTING' => ['Internal Blasting Process (if any)', 4, 'blasting_used', false],
            'VESSEL_LOADING' => ['Load in Pipe From Vessel', 4, 'vessel_loading', false],
        ];
    }

    public static function origin(array $answers): string
    {
        return $answers[1]['company_origin'] ?? (mb_strtolower(trim($answers[1]['country'] ?? 'Indonesia')) === 'indonesia' ? 'indonesia' : 'foreign');
    }

    public static function basis(array $answers): string
    {
        return self::origin($answers) === 'indonesia' && ($answers[1]['document_basis'] ?? 'company') === 'npwp_ktp' ? 'npwp_ktp' : 'company';
    }

    public static function documentGroups(array $answers, array $context): array
    {
        if (self::basis($answers) === 'npwp_ktp') {
            return ['Dokumen pengganti Indonesia' => ['NPWP', 'KTP']];
        }
        $groups = self::origin($answers) === 'foreign' ? [
            'Legal Documents' => ['FOREIGN_DEED', 'FOREIGN_LICENSE', 'FOREIGN_REGISTRY', 'FOREIGN_DOMICILE'],
            'Quality Management Documents' => ['MANUAL_MUTU', 'SERTIFIKAT_MUTU'],
            'HSE / Equipment / Operator Documents' => ['HSE_POLICY', 'MANUAL_K3', 'KEBIJAKAN_ALKOHOL', 'PENILAIAN_RISIKO', 'LIFTING_HOISTING', 'DAILY_INSPECTION', 'EQUIPMENT_INSPECTION', 'LISENSI_OPERATOR', 'INTERNAL_BLASTING', 'VESSEL_LOADING'],
        ] : [
            'Legalitas perusahaan Indonesia' => self::INDONESIA_LEGAL,
        ];
        $groups[self::origin($answers) === 'foreign' ? 'Additional documents' : 'Dokumen tambahan'] = self::ADDITIONAL_DOCUMENTS;
        foreach ($groups as $title => $codes) {
            $groups[$title] = array_values(array_filter($codes, fn ($code) => self::ruleApplies(self::documents()[$code][2], $answers, $context)));
        }

        return array_filter($groups);
    }

    public static function requestedDocuments(array $answers, array $context): array
    {
        $codes = array_merge([], ...array_values(self::documentGroups($answers, $context)));

        return array_intersect_key(self::documents(), array_flip($codes));
    }

    public static function documentLabel(string $code, array $answers): string
    {
        if (self::basis($answers) === 'npwp_ktp' && $code === 'NPWP') {
            return 'NPWP';
        }
        if (self::origin($answers) === 'foreign') {
            return match ($code) {
                'MANUAL_MUTU' => 'Quality Manual', 'SERTIFIKAT_MUTU' => 'ISO 9001: 2008 Certificate', 'MANUAL_K3' => 'HSE Manual',
                'KEBIJAKAN_ALKOHOL' => 'Drug & Alcohol Policy', 'PENILAIAN_RISIKO' => 'Workplace Risk Assessment', 'LISENSI_OPERATOR' => 'Operator Certificate',
                default => self::documents()[$code][0],
            };
        }

        return self::documents()[$code][0];
    }

    public static function fieldGroups(int $step, array $answers): array
    {
        return match ($step) {
            1 => ['Identitas perusahaan' => ['name', 'previous_name'],
                'Alamat dan kontak perusahaan' => ['address', 'city', 'province', 'district', 'village', 'postcode', 'country', 'phone', 'fax', 'email', 'website'],
                'Pimpinan dan contact person' => ['ceo_name', 'ceo_email', 'ceo_phone', 'pic_name', 'pic_position', 'pic_email', 'pic_phone'],
                'Informasi pemasok dan legalitas' => ['supplied_before', 'supplied_description', 'has_amendments', 'non_pkp']],
            3 => ['Kesehatan dan keselamatan kerja' => ['ohs_system', 'ohs_standards', 'ohs_certified', 'health_policy', 'drug_policy', 'risk_assessment', 'ohs_remarks'],
                'Lingkungan' => ['environment_system', 'environment_standards', 'environment_certified', 'environment_offence', 'environment_details', 'environment_remarks'],
                'Peralatan dan operator' => ['equipment_control', 'operator_licensed', 'equipment_used']],
            default => [self::TITLES[$step] => array_keys(self::fields($step, $answers))],
        };
    }

    public static function fieldCondition(string $key): ?array
    {
        return match ($key) {
            'supplied_description' => ['supplied_before', 'yes'],
            'certifying_body', 'quality_expiry' => ['quality_certified', 'yes'],
            'certification_progress' => ['quality_certified', 'no'],
            'environment_details' => ['environment_offence', 'yes'],
            default => null,
        };
    }

    public static function required(string $code, array $answers, array $context): bool
    {
        return array_key_exists($code, self::requestedDocuments($answers, $context));
    }

    private static function ruleApplies(string $rule, array $answers, array $context): bool
    {
        return match ($rule) {
            'always' => true, 'optional' => false,
            'amendments' => ($answers[1]['has_amendments'] ?? '') === 'yes',
            'pkp' => ($answers[1]['non_pkp'] ?? '') !== 'yes',
            'construction' => ($context['is_construction'] ?? false) && ($context['amount_minor'] ?? 0) > 10000000000 && ($context['duration_months'] ?? 0) > 3,
            default => ($answers[in_array($rule, ['quality_system', 'quality_certified']) ? 2 : (in_array($rule, ['blasting_used', 'vessel_loading']) ? 4 : 3)][$rule] ?? '') === 'yes',
        };
    }
}
