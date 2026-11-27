<?php

namespace Tests\Feature;

use App\Filament\Pages\ExpenseReports;
use App\Filament\Pages\FinanceRequests;
use App\Filament\Pages\PaymentRequests;
use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\Reimbursements;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\SubmissionReport;
use App\Filament\Pages\TravelApprovalRules;
use App\Filament\Pages\TravelLpj;
use App\Filament\Pages\TravelSpd;
use App\Livewire\FinanceRequestApprovalSettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceRequest;
use App\Models\ModuleAvailability;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scm\Procurement;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Finance\ApcaForms;
use App\Services\Finance\ApcaPurchaseRequests;
use App\Services\Finance\KpmogForms;
use App\Services\Finance\RequestApprovals;
use App\Services\Finance\Requests;
use App\Services\UserAdditionalAccessService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FinanceRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_configured_company_route_controls_approval_and_keeps_previous_assignments(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_admin' => true]);
        $applicant = $this->applicant();
        [$apca, $manager, $finance, $head, $alternate] = User::factory()->count(5)->create(['is_admin' => false])->all();
        $master = TravelApprovalRules::getUrl(['dokumen' => 'reimbursement']);
        $this->actingAs($admin)->get($master)->assertOk()->assertSee('Urutan approval APCA')->assertSee('Payment Voucher (PV)');
        $this->get(ScmProcurement::getUrl(['bagian' => 'settings', 'dokumen' => 'reimbursement']))
            ->assertOk()->assertSee('Urutan approval APCA')->assertSee('Expense Report (ER)');
        Livewire::actingAs($admin)->test(FinanceRequestApprovalSettings::class, ['kind' => 'reimbursement'])
            ->set('reviewers', [$manager->id, $finance->id, $head->id])
            ->call('save')->assertHasNoErrors()->assertSet('version', 1);
        $policies = app(RequestApprovals::class);
        $policies->save($admin, 'KPMOG', 'payment', [$apca->id, $finance->id, $head->id], 0);
        $requests = app(Requests::class);
        $draft = $requests->save($applicant, $this->input($applicant, 'APCA', 'reimbursement'), null, 0);
        $submitted = $requests->submit($applicant, $draft->id, $draft->version);
        $this->assertSame([$manager->id, $finance->id, $head->id], $submitted->approvals()->pluck('user_id')->all());
        $this->assertCount(1, $manager->notifications);
        $this->assertSame(0, $requests->visible($apca)->count());

        $policies->save($admin, 'APCA', 'reimbursement', [$alternate->id, $finance->id, $head->id], 1);
        Livewire::actingAs($finance)->test(Reimbursements::class)->call('openRequest', $draft->id)
            ->assertDontSee('Catatan (wajib saat mengembalikan)')->call('decideRequest', true)->assertForbidden();
        Livewire::actingAs($manager)->test(Reimbursements::class)->call('openRequest', $draft->id)
            ->call('decideRequest', true)->assertHasNoErrors()->assertSee($finance->name);
        Livewire::actingAs($finance)->test(Reimbursements::class)->call('openRequest', $draft->id)
            ->call('decideRequest', false)->assertHasErrors(['note'])
            ->set('note', 'Tambahkan bukti transfer.')->call('decideRequest', false)->assertHasNoErrors();
        $this->assertSame('returned', $draft->fresh()->status);
        $retry = $requests->submit($applicant, $draft->id, $draft->fresh()->version);
        $this->assertSame($alternate->id, $retry->approvals()->where('round', 2)->first()->user_id);
        foreach ([$alternate, $finance, $head] as $reviewer) {
            $retry = $requests->decide($reviewer, $retry->id, $retry->version, true, '');
        }
        $this->assertSame('approved', $retry->status);
        $this->actingAs($finance)->get('/panel/finance/payment-requests?pengajuan='.$retry->id)->assertRedirect(Reimbursements::getUrl(['pengajuan' => $retry->id]));
        $this->assertDatabaseHas('finance_request_approvals', ['finance_request_id' => $retry->id, 'round' => 1,
            'status' => 'returned', 'note' => 'Tambahkan bukti transfer.']);
    }

    public function test_payment_form_links_to_visible_pr_and_protects_print_and_receipts(): void
    {
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $other = $this->applicant();
        $pr = $this->purchaseRequest($applicant);
        $url = PaymentRequests::getUrl(['pr' => $pr->id]);
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($applicant)->get(PurchaseRequests::getUrl(['permintaan' => $pr->id]))
            ->assertOk()->assertSee('Buat Payment Request');
        $this->get($url)->assertOk()->assertSee('Biaya kunjungan')->assertSee('Parkir');
        $input = $this->input($applicant, 'APCA', 'payment', $pr);

        $component = Livewire::actingAs($applicant)->test(PaymentRequests::class)
            ->call('newRequest')->call('saveRequest')->assertHasErrors(['input.procurement_id'])
            ->set('input', $input)->call('saveRequest')->assertHasNoErrors()->assertSee('Nomor invoice');
        $record = FinanceRequest::sole();
        $this->assertSame($pr->id, $record->procurement_id);
        $this->assertNull($record->travel_document_id);
        $this->get($url)->assertNotFound();
        $this->assertSame(1550050, $record->total_minor);
        $this->assertSame(1, $record->data['usd_total']);
        $component->call('submitRequest')->assertHasErrors(['request']);
        $this->assertSame($record->version, $record->fresh()->version);
        $component->set('attachment', UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n"))
            ->call('uploadAttachment')->assertHasNoErrors()->assertSee('receipt.pdf');
        $file = $record->files()->sole();
        Storage::disk('scm')->assertExists($file->path);
        $print = route('finance.requests.print', $record->id);
        $receipt = route('finance.requests.file', $file);
        $this->actingAs($other)->get($print)->assertNotFound();
        $this->get($receipt)->assertNotFound();
        $this->actingAs($applicant)->get($print)->assertOk()->assertSee('img/brands/apca.png')->assertSee('INV-42')->assertSee($pr->number);
        $this->get($receipt)->assertOk()->assertDownload('receipt.pdf');
        $this->get(PaymentRequests::getUrl(['pengajuan' => $record->id]))->assertOk()->assertSee($record->reference());
        $this->get(FinanceRequests::getUrl(['pengajuan' => $record->id]))->assertRedirect(PaymentRequests::getUrl(['pengajuan' => $record->id]));
    }

    public function test_combined_master_keeps_travel_and_finance_settings_permissions_separate(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $travelManager = User::factory()->create(['is_admin' => false]);
        $travelManager->directPermissions()->attach(Permission::where('code', 'travel.configure')->sole());
        $financeManager = User::factory()->create(['is_admin' => false]);
        $financeManager->directPermissions()->attach(Permission::firstOrCreate(['code' => 'scm.configure'], [
            'name' => 'Pengaturan SCM', 'module' => 'SCM', 'is_active' => true,
        ]));
        $master = TravelApprovalRules::getUrl();

        $this->actingAs($travelManager)->get($master)->assertOk()->assertSee('travel-rule-signer-search');
        $this->get(TravelApprovalRules::getUrl(['dokumen' => 'payment']))->assertForbidden();
        $this->actingAs($financeManager)->get($master)->assertOk()->assertSee('Urutan approval APCA')->assertDontSee('travel-rule-signer-search');
        Livewire::actingAs($financeManager)->test(TravelApprovalRules::class)->call('newRule')->assertForbidden();
    }

    public function test_apca_documents_share_a_pr_and_receive_separate_approvals(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $other = $this->applicant();
        $admin = User::factory()->create(['is_admin' => true]);
        [$reviewer, $reimbursementReviewer, $finance, $head] = User::factory()->count(4)->create(['is_admin' => false])->all();
        app(RequestApprovals::class)->save($admin, 'APCA', 'payment', [$reviewer->id], 0);
        app(RequestApprovals::class)->save($admin, 'APCA', 'reimbursement', [$reimbursementReviewer->id, $finance->id, $head->id], 0);
        $pr = $this->purchaseRequest($applicant, 'approval');
        $input = $this->input($applicant, 'APCA', 'payment', $pr);
        $url = Reimbursements::getUrl(['pr' => $pr->id]);
        $this->actingAs($applicant)->get($url)->assertNotFound();
        $paymentPage = Livewire::actingAs($applicant)->test(PaymentRequests::class)->call('newRequest')->set('input', $input)
            ->call('saveRequest')->assertHasNoErrors()->call('submitRequest')->assertHasErrors(['request'])
            ->assertSee('Tunggu PR sumber disetujui');
        $payment = FinanceRequest::sole();
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($applicant)->get(PurchaseRequests::getUrl(['permintaan' => $pr->id]))
            ->assertOk()->assertSee('Tambahkan Form Reimbursement');
        $this->get($url)->assertOk()->assertSee('Biaya kunjungan')->assertSee('Parkir');
        $pr->update(['submitted_at' => now(), 'status' => 'submitted']);
        $paymentPage->call('submitRequest')->assertHasNoErrors();

        $reimbursementPage = Livewire::actingAs($applicant)->test(Reimbursements::class)->call('newRequest')
            ->set('input.procurement_id', (string) $pr->id)
            ->assertSet('input.description', $pr->title)->assertSet('input.payment_voucher_id', (string) $payment->id)
            ->set('input.method', 'cash')
            ->call('saveRequest')->assertHasNoErrors();
        $reimbursementPage->call('submitRequest')->assertHasErrors(['request'])->assertSee('Tunggu Payment Request terkait disetujui');

        $requests = app(Requests::class);
        $payment->refresh();
        $this->assertSame([$reviewer->id], $payment->approvals()->pluck('user_id')->all());
        $requests->decide($reviewer, $payment->id, $payment->version, true, '');
        $reimbursementPage->call('submitRequest')->assertHasNoErrors();
        $reimbursement = FinanceRequest::where('kind', 'reimbursement')->sole();
        $this->assertSame($pr->id, $reimbursement->procurement_id);
        $this->assertSame($payment->id, $reimbursement->payment_voucher_id);
        $this->assertSame($payment->total_minor, $reimbursement->total_minor);
        $this->assertSame([$reimbursementReviewer->id, $finance->id, $head->id], $reimbursement->approvals()->pluck('user_id')->all());
        $this->get(route('finance.requests.print', $reimbursement->id))->assertOk()->assertSee('Payment Request Reference')->assertSee($payment->reference());
        $this->get($url)->assertNotFound();
    }

    public function test_kpmog_voucher_to_expense_report_preserves_advance_and_requires_evidence(): void
    {
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $admin = User::factory()->create(['is_admin' => true]);
        $reviewers = User::factory()->count(3)->create(['is_admin' => false]);
        $policies = app(RequestApprovals::class);
        $policies->save($admin, 'KPMOG', 'payment', $reviewers->pluck('id')->all(), 0);
        $policies->save($admin, 'KPMOG', 'expense_report', $reviewers->pluck('id')->all(), 0);
        $requests = app(Requests::class);

        $component = Livewire::actingAs($applicant)->test(FinanceRequests::class)->call('newRequest')
            ->set('input', [...$this->input($applicant, 'KPMOG', 'payment'), 'paid_to' => 'Penerima QA', 'voucher_amount' => '100000'])
            ->call('saveRequest')->assertHasNoErrors()
            ->assertSet('attachmentKind', 'invoice');
        $component->call('submitRequest')->assertHasErrors(['request'])->assertSee('wajib memiliki lampiran Invoice')
            ->set('attachmentKind', 'support')
            ->set('attachment', UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n"))
            ->call('uploadAttachment')->assertHasNoErrors()->call('submitRequest')->assertHasErrors(['request'])
            ->set('attachmentKind', 'invoice')
            ->set('attachment', UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n"))
            ->call('uploadAttachment')->assertHasNoErrors()->call('submitRequest')->assertHasNoErrors();
        $voucher = FinanceRequest::sole();
        $this->assertSame('submitted', $voucher->status);
        $this->assertSame(['support', 'invoice'], $voucher->files()->orderBy('id')->pluck('kind')->all());
        $this->assertSame(['Acknowledged', 'Crosschecked', 'Approved'], $voucher->approvals->pluck('label')->all());
        $this->actingAs($applicant)->get(route('finance.requests.print', $voucher->id))
            ->assertOk()->assertSee('Penerima QA')->assertSee('Says:')->assertSee('Seratus ribu rupiah');
        $download = $this->get(route('finance.requests.excel', $voucher->id))->assertOk()->assertDownload('pay-'.$voucher->id.'.xlsx');
        $downloaded = tempnam(sys_get_temp_dir(), 'workdesk-pv-');
        try {
            file_put_contents($downloaded, $download->streamedContent());
            $sheet = IOFactory::createReader('Xlsx')->load($downloaded)->getActiveSheet();
            $this->assertSame('B1:AD45', $sheet->getPageSetup()->getPrintArea());
            $this->assertArrayHasKey('D8:M9', $sheet->getMergeCells());
            $this->assertCount(1, $sheet->getDrawingCollection());
            $this->assertSame('Penerima QA', $sheet->getCell('H11')->getValue());
            $this->assertEquals(100000, $sheet->getCell('H19')->getValue());
        } finally {
            unlink($downloaded);
        }

        $newEr = ExpenseReports::getUrl(['pv' => $voucher->id]);
        $this->get($newEr)->assertNotFound();
        foreach ($reviewers as $reviewer) {
            $voucher = $requests->decide($reviewer, $voucher->id, $voucher->version, true, '');
        }
        $this->get(FinanceRequests::urlFor($voucher))->assertOk()->assertSee('Buat ER Penyelesaian');
        $this->get($newEr)->assertOk()->assertSee('KPM_OG-FRM-082a')->assertSee('IDR 100.000,00');

        $input = [...$this->input($applicant, 'KPMOG', 'expense_report'), 'report_category' => 'Transportasi',
            'payment_voucher_id' => $voucher->id];
        $report = Livewire::actingAs($applicant)->test(ExpenseReports::class)->call('newRequest')
            ->set('input', $input)->call('saveRequest')->assertHasNoErrors()
            ->call('submitRequest')->assertHasErrors(['request'])->assertSee('Lampirkan bukti pengeluaran')
            ->set('attachment', UploadedFile::fake()->createWithContent('struk.pdf', "%PDF-1.4\n"))
            ->call('uploadAttachment')->assertHasNoErrors()->call('submitRequest')->assertHasNoErrors();
        $er = FinanceRequest::findOrFail($report->get('selected'));
        $this->assertSame(10000000, $er->data['advance_minor']);
        $this->assertSame(8449950, $er->data['balance_to_vendor_minor']);
        $this->assertSame('submitted', $er->status);
        $this->get($newEr)->assertNotFound();
        $this->get(route('finance.requests.print', $er->id))->assertOk()->assertSee('Balance to Vendor')->assertSee('IDR 84.499,50');
        $this->get(route('finance.requests.excel', $er->id))->assertDownload('er-'.$er->id.'.xlsx');
        try {
            $requests->save($applicant, $input, null, 0);
            $this->fail('PV cannot be used for a second ER.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('input.payment_voucher_id', $error->errors());
        }
        $standalone = $requests->save($applicant, [...$input, 'payment_voucher_id' => ''], null, 0);
        $this->assertNull($standalone->payment_voucher_id);
        $this->assertSame(0, $standalone->data['advance_minor']);
        $this->assertSame(-1550050, $standalone->data['balance_to_vendor_minor']);

        Livewire::actingAs($applicant)->test(Reimbursements::class)->call('newRequest')
            ->set('input', $this->input($applicant, 'KPMOG', 'reimbursement'))->call('saveRequest')
            ->assertHasErrors(['input.company'])->assertSee('Ajukan reimbursement KPMOG melalui ER atau LPJ.');
        $this->actingAs($applicant)->get(Reimbursements::getUrl())->assertOk()
            ->assertSee(TravelLpj::getUrl(['reimbursement' => 1]))->assertSee(ExpenseReports::getUrl());
        $this->assertSame(0, FinanceRequest::where('kind', 'reimbursement')->count());
    }

    public function test_er_workbook_extends_original_rows_and_keeps_all_amounts_and_text(): void
    {
        $applicant = $this->applicant();
        $input = [...$this->input($applicant, 'KPMOG', 'expense_report'), 'report_category' => 'Transportasi',
            'lines' => array_fill(0, 12, ['date' => '2026-11-25', 'description' => '=SUM(1,1)', 'amount' => '1000'])];
        $report = app(Requests::class)->save($applicant, $input, null, 0);
        $sheet = app(KpmogForms::class)->workbook($report)->getActiveSheet();
        $this->assertSame('B2:J47', $sheet->getPageSetup()->getPrintArea());
        $this->assertSame('KPM_OG-FRM-082a', $sheet->getCell('I2')->getValue());
        $this->assertArrayHasKey('B7:J8', $sheet->getMergeCells());
        $this->assertSame('=SUM(1,1)', $sheet->getCell('E21')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('E21')->getDataType());
        $this->assertEquals(12, $sheet->getCell('C32')->getValue());
        $this->assertEquals(12000, $sheet->getCell('I33')->getCalculatedValue());
        $this->assertEquals(-12000, $sheet->getCell('I35')->getCalculatedValue());
        $this->actingAs($this->applicant())->get(route('finance.requests.excel', $report->id))->assertNotFound();
    }

    public function test_apca_has_its_own_forms_and_uses_the_apca_excel_masters(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $requests = app(Requests::class);
        $payment = $requests->save($applicant, [...$this->input($applicant, 'APCA', 'payment'), 'invoice_reference' => 'APCA-QA-42',
            'method' => 'transfer', 'bank' => 'Bank QA', 'account_name' => 'Penerima QA', 'account_number' => '001122'], null, 0);
        $voucher = $requests->save($applicant, [...$this->input($applicant, 'KPMOG', 'payment'), 'description' => 'Voucher KPMOG terpisah',
            'paid_to' => 'Penerima QA', 'voucher_amount' => '400000'], null, 0);
        $reimbursement = $requests->save($applicant, [...$this->input($applicant, 'APCA', 'reimbursement'),
            'lines' => array_fill(0, 18, ['date' => '2026-11-26', 'description' => '=SUM(1,2)', 'amount' => '15500'])], null, 0);

        $this->actingAs($applicant)->get(PaymentRequests::getUrl())->assertOk()->assertSee('Buat Payment Request')
            ->assertSee($payment->reference())->assertDontSee('Voucher KPMOG terpisah');
        $this->get(FinanceRequests::getUrl())->assertOk()->assertSee('Voucher KPMOG terpisah')->assertDontSee($payment->reference());
        $this->get(route('finance.requests.print', $payment->id))->assertOk()->assertSee('PAYMENT REQUEST')->assertSee('img/brands/apca.png');
        $response = $this->get(route('finance.requests.excel', $payment->id))->assertOk()->assertDownload('apca-qa-42.xlsx');
        $file = tempnam(sys_get_temp_dir(), 'workdesk-apca-');
        try {
            file_put_contents($file, $response->streamedContent());
            $sheet = IOFactory::createReader('Xlsx')->load($file)->getSheet(0);
            $this->assertSame('PAYMENT REQUEST', trim($sheet->getCell('B1')->getValue()));
            $this->assertSame('INV-42', $sheet->getCell('D12')->getValue());
            $this->assertSame('APCA-QA-42', $sheet->getCell('K8')->getValue());
            $this->assertSame('001122', $sheet->getCell('I37')->getValue());
            $this->assertEquals(15500.50, $sheet->getCell('H29')->getCalculatedValue());
            $this->assertEquals(1, $sheet->getCell('J29')->getCalculatedValue());
        } finally {
            unlink($file);
        }

        $sheet = app(ApcaForms::class)->workbook($reimbursement)->getSheet(0);
        $this->assertSame('A1:I41', $sheet->getPageSetup()->getPrintArea());
        $this->assertSame('FORM REIMBURSEMENT', trim($sheet->getCell('B1')->getValue()));
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('=SUM(1,2)', $sheet->getCell('C12')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C12')->getDataType());
        $this->assertEquals(18 * 15500, $sheet->getCell('F30')->getCalculatedValue());
        $this->assertEquals(18, $sheet->getCell('H30')->getCalculatedValue());
        $this->get(route('finance.requests.excel', $reimbursement->id))->assertDownload('rmb-'.$reimbursement->id.'.xlsx');
        $this->actingAs($this->applicant())->get(route('finance.requests.excel', $payment->id))->assertNotFound();
    }

    public function test_submission_reports_cover_each_document_and_export_only_accessible_filtered_rows(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $other = $this->applicant();
        $applicant->directPermissions()->attach(Permission::where('code', 'travel.spd.create')->sole());
        $pr = $this->purchaseRequest($applicant);
        $this->purchaseRequest($other)->update(['title' => 'PR di luar akses']);
        $sr = $this->purchaseRequest($applicant);
        $sr->update(['kind' => 'SR', 'number' => 'SR-REPORT-QA']);

        $requests = app(Requests::class);
        $payment = $requests->save($applicant, $this->input($applicant, 'APCA', 'payment', $pr), null, 0);
        $voucher = $requests->save($applicant, [...$this->input($applicant, 'KPMOG', 'payment'), 'paid_to' => 'Vendor QA', 'voucher_amount' => '12000'], null, 0);
        $er = $requests->save($applicant, [...$this->input($applicant, 'KPMOG', 'expense_report'), 'report_category' => 'Transportasi'], null, 0);
        $reimbursement = $requests->save($applicant, [...$this->input($applicant, 'APCA', 'reimbursement'), 'description' => '=5+7'], null, 0);
        $travelFields = ['company' => 'APCA', 'issued_on' => '2026-11-26', 'applied_on' => '2026-11-26', 'created_by' => $applicant->id,
            'status' => 'draft', 'department_id' => $applicant->employee->department_id, 'employee_id' => $applicant->employee->id,
            'data' => ['purpose' => 'Kunjungan QA'], 'employee_snapshot' => ['name' => $applicant->name]];
        $spd = TravelDocument::create([...$travelFields, 'kind' => 'spd', 'number' => 'SPD-REPORT-QA', 'number_key' => 'SPD-REPORT-QA',
            'employee_snapshot' => ['name' => 'Karyawan Perjalanan QA'],
            'data' => [...$travelFields['data'], 'requester_snapshot' => ['name' => 'Pemohon Perjalanan QA']]]);
        $lpj = TravelDocument::create([...$travelFields, 'kind' => 'lpj', 'number' => 'LPJ-REPORT-QA', 'number_key' => 'LPJ-REPORT-QA', 'spd_id' => $spd->id]);

        $this->actingAs($applicant);
        foreach (['pr' => $pr->number, 'sr' => $sr->number, 'pv' => $voucher->reference(), 'payreq' => $payment->reference(),
            'spd' => $spd->number, 'lpj' => $lpj->number, 'er' => $er->reference(), 'reimbursement' => $reimbursement->reference()] as $type => $number) {
            $this->get(SubmissionReport::getUrl(['type' => $type]))->assertOk()->assertSee($number)->assertDontSee('PR di luar akses');
            $export = $this->get(route('reports.submissions.download', ['type' => $type]))
                ->assertOk()->assertDownload('laporan-'.$type.'.csv')->streamedContent();
            $lines = explode("\n", trim($export));
            $this->assertCount(2, $lines, "Jumlah baris laporan {$type}");
            $this->assertSame($number, str_getcsv($lines[1], ',', '"', '')[0], "Nomor dokumen laporan {$type}");
        }
        $this->get(SubmissionReport::getUrl(['type' => 'spd']))->assertOk()
            ->assertSee('Pemohon Perjalanan QA')->assertDontSee('Karyawan Perjalanan QA');
        $spdCsv = $this->get(route('reports.submissions.download', ['type' => 'spd']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Pemohon Perjalanan QA', $spdCsv);
        $this->assertStringNotContainsString('Karyawan Perjalanan QA', $spdCsv);
        $this->get(SubmissionReport::getUrl(['type' => 'payreq', 'from' => '2026-12-01']))->assertOk()->assertDontSee($payment->reference());
        $this->get(SubmissionReport::getUrl(['type' => 'payreq', 'from' => '2026-12-01', 'until' => '2026-11-30']))
            ->assertOk()->assertSee('Tanggal akhir harus sama dengan atau setelah tanggal awal.');
        $csv = $this->get(route('reports.submissions.download', ['type' => 'reimbursement', 'company' => 'APCA', 'status' => 'draft']))
            ->assertOk()->assertDownload('laporan-reimbursement.csv')->streamedContent();
        $this->assertStringContainsString($reimbursement->reference(), $csv);
        $this->assertStringContainsString("'=5+7", $csv);
        $this->assertStringNotContainsString($payment->reference(), $csv);
        $this->actingAs($other)->get(SubmissionReport::getUrl(['type' => 'spd']))->assertForbidden();
        $otherCsv = $this->get(route('reports.submissions.download', ['type' => 'payreq']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($payment->reference(), $otherCsv);
    }

    public function test_khafafi_prepares_finance_documents_and_sources_keep_their_requester(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $khafafi = $this->applicant();
        $khafafi->update(['email' => 'riyankhafafi@kpmog.com', 'is_admin' => true]);
        $requester = $this->applicant();
        $other = $this->applicant();
        $requests = app(Requests::class);
        $voucherInput = [...$this->input($requester, 'KPMOG', 'payment'), 'requester_id' => $requester->id,
            'paid_to' => 'Vendor QA', 'voucher_amount' => '12000'];
        Livewire::actingAs($other)->test(FinanceRequests::class)->call('newRequest')
            ->set('input', $voucherInput)->call('saveRequest')->assertForbidden();

        Livewire::actingAs($khafafi)->test(FinanceRequests::class)->call('newRequest')->assertSee('Pemohon')
            ->set('input.requester_id', (string) $requester->id)->assertSet('input.department_id', (string) $requester->employee->department_id)
            ->set('input', $voucherInput)->call('saveRequest')->assertHasNoErrors()->assertSee($requester->name);
        $voucher = FinanceRequest::sole();
        $this->assertSame($khafafi->id, $voucher->created_by);
        $this->assertSame($requester->id, $voucher->requesterId());
        $this->assertSame(1, $requester->notifications()->count());
        $this->actingAs($requester)->get(FinanceRequests::urlFor($voucher))->assertOk()->assertSee('Kirim untuk approval');
        $this->actingAs($other)->get(FinanceRequests::urlFor($voucher))->assertNotFound();

        $voucher->update(['status' => 'approved']);
        $er = $requests->save($khafafi, [...$this->input($requester, 'KPMOG', 'expense_report'), 'payment_voucher_id' => $voucher->id,
            'report_category' => 'Transportasi', 'requester_id' => $other->id], null, 0);
        $this->assertSame($requester->id, $er->requesterId());
        $this->assertSame($voucher->id, $er->payment_voucher_id);
        $this->assertSame($requester->name, $er->data['applicant']);

        $pr = $this->purchaseRequest($requester);
        $payment = $requests->save($khafafi, $this->input($requester, 'APCA', 'payment', $pr), null, 0);
        $this->assertSame($requester->id, $payment->requesterId());
        $reimbursement = $requests->save($khafafi, [...$this->input($requester, 'APCA', 'reimbursement'), 'procurement_id' => $pr->id], null, 0);
        $this->assertSame($requester->id, $reimbursement->requesterId());
        $this->assertSame($payment->id, $reimbursement->payment_voucher_id);
        $this->actingAs($requester)->get(SubmissionReport::getUrl(['type' => 'payreq']))->assertOk()->assertSee($payment->reference());

        $this->actingAs($khafafi)->get(FinanceRequests::urlFor($payment))->assertOk()->assertDontSee('Kirim untuk approval');
        try {
            $requests->submit($khafafi, $payment->id, $payment->version);
            $this->fail('Akun penyusun tidak boleh mengirim draft atas nama pemohon.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Pemohon harus membuka', $error->errors()['request'][0]);
        }
        app(RequestApprovals::class)->save($khafafi, 'APCA', 'payment', [$khafafi->id], 0);
        $filed = $requests->submit($requester, $payment->id, $payment->version);
        $this->assertSame([$khafafi->id], $filed->approvals()->pluck('user_id')->all());
    }

    public function test_finance_document_access_and_report_export_can_be_enabled_individually(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $actor = $this->applicant();
        $requests = app(Requests::class);
        $payment = $requests->save($actor, $this->input($actor, 'APCA', 'payment'), null, 0);
        $voucher = $requests->save($actor, [...$this->input($actor, 'KPMOG', 'payment'), 'paid_to' => 'Vendor QA', 'voucher_amount' => '12000'], null, 0);
        $voucher->update(['status' => 'approved']);
        $access = app(UserAdditionalAccessService::class);
        $access->sync($actor, ['finance-pv', 'report-submissions', 'scm-requests']);
        $this->actingAs($actor)->get(FinanceRequests::getUrl())->assertOk()->assertSee('Buat PV');
        $this->get(PaymentRequests::getUrl())->assertForbidden();
        $this->get(ExpenseReports::getUrl())->assertForbidden();
        $this->get(Reimbursements::getUrl())->assertForbidden();
        $this->get(route('finance.requests.print', $payment->id))->assertNotFound();
        $this->get(SubmissionReport::getUrl(['type' => 'pv']))->assertOk()->assertDontSee('Unduh CSV');
        $this->get(route('reports.submissions.download', ['type' => 'pv']))->assertForbidden();
        $this->get(SubmissionReport::getUrl(['type' => 'payreq']))->assertForbidden();

        $access->sync($actor, ['finance-er', 'finance-reimbursement', 'scm-requests', 'report-submissions', 'report-submissions-export']);
        $this->get(ExpenseReports::getUrl(['pv' => $voucher->id]))->assertOk()->assertSee('IDR 12.000,00');
        $pr = $payment->procurement;
        $this->get(PurchaseRequests::getUrl(['permintaan' => $pr->id]))->assertOk()->assertSee('Tambahkan Form Reimbursement');
        $this->get(Reimbursements::getUrl(['pr' => $pr->id]))->assertOk()->assertSee('Parkir');
        $this->get(route('reports.submissions.download', ['type' => 'reimbursement']))->assertOk()->assertDownload('laporan-reimbursement.csv');
        $this->get(route('finance.requests.excel', $voucher->id))->assertNotFound();
        $this->assertSame(['finance-er', 'finance-reimbursement'], $access->menuStateFor($actor)['finance']);

        $access->sync($actor, ['finance-er', 'report-submissions-export']);
        $this->get(SubmissionReport::getUrl())->assertForbidden();
        $this->get(route('reports.submissions.download', ['type' => 'er']))->assertForbidden();
    }

    public function test_existing_finance_grants_are_migrated_for_accounts_and_roles(): void
    {
        $permission = Permission::where('code', 'finance.requests.create')->sole();
        $account = User::factory()->create(['is_admin' => false]);
        $roleAccount = User::factory()->create(['is_admin' => false]);
        $role = Role::create(['code' => 'finance-legacy-test', 'name' => 'Finance legacy test', 'is_active' => true]);
        $account->directPermissions()->attach($permission);
        $role->permissions()->attach($permission);
        $roleAccount->roles()->attach($role);
        $migration = require database_path('migrations/2026_11_26_000007_split_finance_and_submission_report_permissions.php');
        $migration->up();

        foreach ([$account, $roleAccount] as $user) {
            $this->assertTrue(Requests::canCreate($user, 'payment', 'KPMOG'));
            $this->assertTrue(Requests::canCreate($user, 'payment', 'APCA'));
            $this->assertTrue(Requests::canCreate($user, 'expense_report'));
            $this->assertTrue(Requests::canCreate($user, 'reimbursement'));
            $this->assertTrue($user->hasPermission('report.submissions.export'));
        }
    }

    public function test_disabling_source_modules_blocks_documents_downloads_and_reports(): void
    {
        Storage::fake('local');
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $applicant = $this->applicant();
        $applicant->directPermissions()->attach(Permission::where('code', 'travel.spd.create')->sole());
        $requests = app(Requests::class);
        $payment = $requests->save($applicant, $this->input($applicant, 'APCA', 'payment'), null, 0);
        $financeFile = $requests->attach($applicant, $payment->id, $payment->version,
            UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n"))->files()->sole();
        $spd = TravelDocument::create(['company' => 'APCA', 'kind' => 'spd', 'number' => 'SPD-ACCESS-QA', 'number_key' => 'SPD-ACCESS-QA',
            'issued_on' => '2026-11-26', 'applied_on' => '2026-11-26', 'created_by' => $applicant->id, 'status' => 'draft',
            'department_id' => $applicant->employee->department_id, 'employee_id' => $applicant->employee->id,
            'data' => ['purpose' => 'Kunjungan QA'], 'employee_snapshot' => ['name' => $applicant->name]]);
        $path = 'travel/2026/11/00000000-0000-4000-8000-000000000001.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n");
        $travelFile = $spd->files()->create(['path' => $path, 'original_name' => 'surat.pdf', 'mime' => 'application/pdf',
            'size' => strlen("%PDF-1.4\n"), 'uploaded_by' => $applicant->id]);

        $this->actingAs($applicant)->get(route('travel.print', $spd->id))->assertOk();
        $this->get(route('travel.file', $travelFile))->assertDownload('surat.pdf');
        ModuleAvailability::create(['module_key' => 'perjalanan-dinas', 'label' => 'Perjalanan Dinas', 'is_enabled' => false]);
        $this->get(TravelSpd::getUrl(['dokumen' => $spd->id]))->assertForbidden();
        $this->get(route('travel.print', $spd->id))->assertForbidden();
        $this->get(route('travel.file', $travelFile))->assertForbidden();
        $this->get(SubmissionReport::getUrl(['type' => 'spd']))->assertForbidden();
        $this->get(route('reports.submissions.download', ['type' => 'spd']))->assertForbidden();

        $this->get(route('finance.requests.file', $financeFile))->assertDownload('invoice.pdf');
        ModuleAvailability::create(['module_key' => 'finance', 'label' => 'Finance', 'is_enabled' => false]);
        $this->get(PaymentRequests::getUrl(['pengajuan' => $payment->id]))->assertForbidden();
        $this->get(route('finance.requests.print', $payment->id))->assertForbidden();
        $this->get(route('finance.requests.excel', $payment->id))->assertForbidden();
        $this->get(route('finance.requests.file', $financeFile))->assertForbidden();
        $this->get(route('reports.submissions.download', ['type' => 'payreq']))->assertForbidden();

        $this->get(SubmissionReport::getUrl(['type' => 'pr']))->assertOk();
        ModuleAvailability::create(['module_key' => 'reports', 'label' => 'Reports', 'is_enabled' => false]);
        $this->get(SubmissionReport::getUrl(['type' => 'pr']))->assertForbidden();
        $this->get(route('reports.submissions.download', ['type' => 'pr']))->assertForbidden();
    }

    private function applicant(): User
    {
        $department = Department::create(['code' => fake()->unique()->bothify('FIN-####'), 'name' => 'Departemen uji']);
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'name' => $user->name, 'department_id' => $department->id, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', ['finance.pv.create', 'finance.payreq.create', 'finance.er.create',
            'finance.reimbursement.create', 'travel.lpj.create', 'report.submissions', 'report.submissions.export'])->pluck('id'));
        $user->directPermissions()->attach(Permission::firstOrCreate(['code' => 'scm.request'], [
            'name' => 'Permintaan SCM', 'module' => 'SCM', 'is_active' => true,
        ]));

        return $user;
    }

    private function input(User $actor, string $company, string $kind, ?Procurement $pr = null): array
    {
        $input = [
            'kind' => $kind, 'company' => $company, 'department_id' => $actor->employee->department_id, 'issued_on' => '2026-11-26',
            'description' => 'Biaya kunjungan', 'exchange_rate' => '15500', 'method' => 'cash', 'travel_document_id' => '',
            'lines' => [['date' => '2026-11-25', 'description' => 'Parkir', 'amount' => '15500,50', 'invoice_number' => 'INV-42', 'due_on' => '2026-11-30']],
        ];
        if ($company === 'APCA' && $kind === 'payment') {
            $fields = app(ApcaPurchaseRequests::class)->fields($pr ?? $this->purchaseRequest($actor));
            $input = [...$input, ...$fields, 'lines' => [[...$input['lines'][0], ...$fields['lines'][0],
                'amount' => '15500,50', 'invoice_number' => 'INV-42', 'due_on' => '2026-11-30']]];
        }

        return $input;
    }

    private function purchaseRequest(User $actor, string $status = 'submitted'): Procurement
    {
        $source = Procurement::create(['number' => 'PR-APCA-QA-'.Procurement::count(), 'company' => 'APCA', 'kind' => 'PR',
            'need_type' => 'HO', 'date' => '2026-11-25', 'requester_id' => $actor->id, 'department_id' => $actor->employee->department_id,
            'title' => 'Biaya kunjungan', 'reason' => 'Kunjungan operasional', 'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : null]);
        $source->items()->create(['position' => 1, 'name' => 'Parkir', 'specification' => 'Parkir kendaraan', 'quantity_milli' => 1000, 'unit' => 'Unit']);

        return $source;
    }
}
