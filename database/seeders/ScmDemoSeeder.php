<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Category;
use App\Models\Scm\DocumentType;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\User;
use App\Services\Scm\Decimal;
use App\Services\Scm\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class ScmDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data demo SCM hanya untuk pengembangan lokal.');
        }
        $this->call(ScmSeeder::class);
        $department = Department::firstOrCreate(['code' => 'SCM-DEMO'], ['name' => 'SCM Demo Fiktif', 'is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'DEMO: Barang / jasa fiktif']);
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        Requirement::firstOrCreate(['category_id' => $category->id, 'document_type_id' => $type->id], ['is_required' => true]);
        $actors = [];
        foreach (['pemohon' => ['scm.request'], 'petugas' => ['scm.process', 'scm.configure', 'scm.request']] as $name => $permissions) {
            $user = User::firstOrCreate(['email' => 'scm.'.$name.'@example.test'], ['name' => 'SCM Demo '.ucfirst($name), 'password' => Hash::make('SCM-Demo-2026!'), 'is_admin' => false]);
            Employee::firstOrCreate(['user_id' => $user->id], ['department_id' => $department->id, 'name' => $user->name, 'is_active' => true]);
            $user->directPermissions()->syncWithoutDetaching(Permission::whereIn('code', $permissions)->pluck('id'));
            $actors[$name] = $user->fresh();
        }
        $flow = app(Workflow::class);
        $scm = $actors['petugas'];
        $vendors = [];
        for ($i = 1; $i <= 3; $i++) {
            $vendor = Vendor::where('code', 'SCM-DEMO-V'.$i)->first();
            $vendors[] = $vendor ?? $flow->saveVendor($scm, ['code' => 'SCM-DEMO-V'.$i, 'name' => 'Vendor Demo Fiktif '.$i, 'provider_type' => 'keduanya', 'is_active' => true, 'notes' => 'Data demo. Bukan perusahaan nyata.']);
        }
        foreach (['PR', 'SR'] as $kind) {
            if (Procurement::where('requester_id', $actors['pemohon']->id)->where('title', 'DEMO '.$kind.' sampai legalitas lengkap')->exists()) {
                continue;
            }
            $r = $flow->saveDraft($actors['pemohon'], ['kind' => $kind, 'date' => today()->toDateString(), 'department_id' => $department->id,
                'title' => 'DEMO '.$kind.' sampai legalitas lengkap', 'reason' => 'Contoh fiktif untuk pengembangan, bukan kebutuhan pengadaan nyata.',
                'items' => [['name' => $kind === 'PR' ? 'Barang demo' : 'Jasa demo', 'specification' => 'Spesifikasi fiktif untuk latihan', 'quantity' => '2', 'unit' => $kind === 'PR' ? 'unit' : 'paket'],
                    ['name' => 'Item pendukung demo', 'specification' => 'Cakupan tambahan fiktif', 'quantity' => '1', 'unit' => 'paket']]]);
            $r = $flow->submit($actors['pemohon'], $r->id, $r->version);
            $r = $flow->receive($scm, $r->id, $r->version, $category->id);
            foreach ($vendors as $vendor) {
                $r = $flow->addCandidate($scm, $r->id, $r->version, ['vendor_id' => $vendor->id, 'requested_at' => today()->toDateString(), 'followup_status' => 'diminta']);
            }
            foreach ($r->candidates as $index => $candidate) {
                $r = $flow->saveQuotation($scm, $r->id, $r->version, ['candidate_id' => $candidate->id, 'number' => 'DEMO-Q-'.$candidate->id, 'date' => today()->toDateString(), 'currency' => 'IDR',
                    'lead_time' => ($index + 3).' hari', 'payment_terms' => 'Ketentuan demo fiktif', 'extra' => '0', 'lines' => $r->items->map(fn ($item) => ['request_item_id' => $item->id, 'description' => $item->specification,
                        'quantity' => Decimal::input($item->quantity_milli, 3), 'unit' => $item->unit, 'price' => (string) (100000 + $index * 10000), 'discount' => '0', 'tax' => '0'])->all()]);
            }
            $ids = Quotation::whereHas('candidate', fn ($q) => $q->where('request_id', $r->id))->pluck('id')->all();
            $winner = $vendors[$kind === 'PR' ? 0 : 1];
            $r = $flow->saveCbe($scm, $r->id, $r->version, ['date' => today()->toDateString(), 'quotation_ids' => $ids, 'selected_vendor_id' => $winner->id,
                'selection_reason' => $kind === 'PR' ? 'DEMO: total terendah dengan spesifikasi setara.' : 'DEMO: vendor kedua dipilih berdasarkan kesesuaian syarat, bukan semata total terendah.'], true);
            $r = $flow->startLegal($scm, $r->id, $r->version);
            $doc = VendorDocument::where('vendor_id', $winner->id)->where('document_type_id', $type->id)->first();
            if (! $doc) {
                $file = tempnam(sys_get_temp_dir(), 'scm-demo-');
                try {
                    file_put_contents($file, $this->demoPdf());
                    $doc = $flow->saveDocument($scm, ['vendor_id' => $winner->id, 'document_type_id' => $type->id, 'number' => 'DEMO-0000'.$winner->id, 'title' => 'DEMO NIB fiktif - bukan dokumen legalitas'], new UploadedFile($file, 'DEMO-NIB.pdf', 'application/pdf', null, true));
                } finally {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            $r = $flow->updateLegal($scm, $r->id, $r->version, $r->legalCase->items->first()->id, ['status' => 'diterima', 'vendor_document_id' => $doc->id]);
            $flow->complete($scm, $r->id, $r->version);
        }
    }

    private function demoPdf(): string
    {
        $stream = 'BT /F1 18 Tf 40 760 Td (DEMO - DOKUMEN FIKTIF) Tj 0 -30 Td /F1 12 Tf (Bukan dokumen legalitas. Hanya untuk pengembangan IK WorkDesk.) Tj ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
