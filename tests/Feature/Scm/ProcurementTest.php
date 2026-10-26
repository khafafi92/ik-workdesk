<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmApprovals;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ServiceRequests;
use App\Filament\Pages\SignatureProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Attachment;
use App\Models\Scm\Audit;
use App\Models\Scm\Category;
use App\Models\Scm\DocumentType;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\RequestNumber;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Approvals;
use App\Services\Scm\Decimal;
use App\Services\Scm\RequestNumbering;
use App\Services\Scm\Signatures;
use App\Services\Scm\Validation;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmDemoSeeder;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $scm;

    private Category $category;

    private Department $department;

    private Workflow $flow;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $this->department = Department::create(['code' => 'SCM-TEST', 'name' => 'Departemen uji SCM', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Kategori uji']);
        $this->requester = $this->user('scm.request');
        $this->scm = $this->user('scm.process');
        $this->flow = app(Workflow::class);
        app(Signatures::class)->upload($this->requester, $this->signatureFile());
        foreach (['APCA', 'KPMOG'] as $company) {
            ApprovalRoute::create(['company' => $company, 'department_id' => $this->department->id,
                'acknowledger_id' => $this->user('scm.request')->id, 'cbo_id' => $this->user('scm.request')->id, 'cbo3_id' => $this->user('scm.request')->id]);
        }
    }

    private function user(string $permission, bool $admin = false): User
    {
        $user = User::factory()->create(['is_admin' => $admin]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $user->name, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::where('code', $permission)->firstOrFail());

        return $user->fresh();
    }

    private function draft(string $kind = 'PR'): Procurement
    {
        $request = $this->flow->saveDraft($this->requester, $this->draftData($kind));

        return app(Signatures::class)->sign($this->requester, $request->id, $request->version);
    }

    private function signatureFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('DEMO-BUKAN-TTD.png', file_get_contents(database_path('fixtures/scm-demo-signature.png')));
    }

    private function draftData(string $kind = 'PR'): array
    {
        return ['kind' => $kind, 'date' => today()->toDateString(), 'estimated_total' => '1000',
            'department_id' => $this->department->id,
            'title' => 'Pengadaan uji '.$kind, 'reason' => 'Pengujian kebutuhan operasional', 'items' => [
                ['name' => 'Item satu', 'specification' => 'Spesifikasi satu', 'quantity' => '1,5', 'unit' => 'unit'],
                ['name' => 'Item dua', 'specification' => 'Spesifikasi dua', 'quantity' => '2', 'unit' => 'paket'],
            ]];
    }

    private function sourcing(string $kind = 'PR'): Procurement
    {
        $r = $this->draft($kind);
        $r = $this->submitApproved($r);

        return $this->flow->receive($this->scm, $r->id, $r->version, $this->category->id);
    }

    private function approve(Procurement $request): Procurement
    {
        $approvals = app(Approvals::class);
        while ($step = $approvals->next($request)) {
            if (! app(Signatures::class)->current($step->user)) {
                app(Signatures::class)->upload($step->user, $this->signatureFile());
            }
            $request = $approvals->decide($step->user, $request->id, $request->version, $step->id, $step->is_required ? 'sign' : 'skip');
        }

        return $request;
    }

    private function submitApproved(Procurement $request): Procurement
    {
        return $this->approve($this->flow->submit($this->requester, $request->id, $request->version));
    }

    private function vendors(Procurement $r): Procurement
    {
        for ($i = 1; $i <= 3; $i++) {
            $vendor = $this->flow->saveVendor($this->scm, ['code' => 'V'.$i, 'name' => 'Vendor fiktif '.$i, 'provider_type' => 'keduanya', 'is_active' => true]);
            $r = $this->flow->addCandidate($this->scm, $r->id, $r->version, ['vendor_id' => $vendor->id, 'followup_status' => 'diminta']);
        }

        return $r;
    }

    private function quoteData(Procurement $r, int $candidateId, string $price = '100,01'): array
    {
        return ['candidate_id' => $candidateId, 'number' => 'QUOTE-'.$candidateId, 'date' => today()->toDateString(),
            'currency' => 'IDR', 'lead_time' => '7 hari', 'payment_terms' => '30 hari setelah penagihan', 'extra' => '10,25',
            'lines' => $r->items->map(fn ($item) => ['request_item_id' => $item->id, 'description' => $item->specification,
                'quantity' => Decimal::input($item->quantity_milli, 3), 'unit' => $item->unit, 'price' => $price, 'discount' => '0,01', 'tax' => '1,05'])->all()];
    }

    private function quoted(string $kind = 'PR'): Procurement
    {
        $r = $this->vendors($this->sourcing($kind));
        foreach ($r->candidates as $candidate) {
            $r = $this->flow->saveQuotation($this->scm, $r->id, $r->version, $this->quoteData($r, $candidate->id));
        }

        return $r;
    }

    private function cbeData(Procurement $r): array
    {
        $quotes = Quotation::whereHas('candidate', fn ($q) => $q->where('request_id', $r->id))->get();

        return ['date' => today()->toDateString(), 'quotation_ids' => $quotes->pluck('id')->all(),
            'selected_vendor_id' => $r->candidates->first()->vendor_id, 'selection_reason' => 'Harga dan spesifikasi sesuai kebutuhan operasional.'];
    }

    private function finalized(string $kind = 'PR'): Procurement
    {
        $r = $this->quoted($kind);

        return $this->flow->saveCbe($this->scm, $r->id, $r->version, $this->cbeData($r), true);
    }

    private function pdf(string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
    }

    private function acceptLegalDocument(Procurement $request, DocumentType $type, ?string $expiresAt = null): Procurement
    {
        $document = $this->flow->saveDocument($this->scm, ['vendor_id' => $request->legalCase->vendor_id,
            'document_type_id' => $type->id, 'number' => '0000123', 'title' => 'Dokumen uji '.$type->code,
            'issued_at' => '2026-01-01', 'expires_at' => $expiresAt], $this->pdf());
        $item = $request->legalCase->items()->where('document_type_id', $type->id)->firstOrFail();

        return $this->flow->updateLegal($this->scm, $request->id, $request->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $document->id]);
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Transisi tidak sah seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public static function kinds(): array
    {
        return [['PR'], ['SR']];
    }

    #[DataProvider('kinds')]
    public function test_request_needs_only_sheet_fields_and_an_explicit_signature_before_submission(string $kind): void
    {
        $page = Livewire::actingAs($this->requester)->test($kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class)
            ->call('newDraft', 'KPMOG')->assertDontSee('Keterangan kebutuhan')->assertDontSee('Judul kebutuhan')->assertDontSee('Alasan kebutuhan')
            ->set('draft.items.0.name', 'Material formulir')->set('draft.items.0.specification', 'Spesifikasi material')
            ->set('draft.estimated_total', '5000000')
            ->set('draft.items.0.unit', 'unit')->call('saveDraft')->assertHasNoErrors();
        $request = Procurement::sole();
        $this->assertSame('Material formulir', $request->title);
        $this->assertSame('', $request->reason);
        $this->assertNull($request->prepared_signed_at);
        $page->call('submitRequest')->assertHasErrors('scm');
        $this->assertSame('draft', $request->fresh()->status);
        $page->call('signPrepared')->assertHasNoErrors()->assertSee('ditandatangani')->call('submitRequest')->assertHasNoErrors();
        $request->refresh();
        $this->assertSame('approval', $request->status);
        $this->assertSame($this->requester->name, $request->prepared_signed_name);
        $this->assertNotNull($request->prepared_signed_at);
        $this->assertSame(1, Audit::where('request_id', $request->id)->where('action', 'tanda_tangan_prepared')->where('actor_id', $this->requester->id)->count());
    }

    public function test_signature_is_invalidated_by_draft_and_attachment_changes_but_not_profile_replacement(): void
    {
        $request = $this->draft();
        $original = $request->prepared_signature_id;
        $data = $this->draftData();
        unset($data['title'], $data['reason']);
        $data['items'][0]['name'] = 'Material diperbarui';
        $request = $this->flow->saveDraft($this->requester, $data, $request->id, $request->version);
        $this->assertSame('Pengadaan uji PR', $request->title);
        $this->assertSame('Pengujian kebutuhan operasional', $request->reason);
        $this->assertNull($request->prepared_signature_id);
        $this->rejects(fn () => $this->flow->submit($this->requester, $request->id, $request->version));
        $signatures = app(Signatures::class);
        $request = $signatures->sign($this->requester, $request->id, $request->version);
        $request = $this->flow->attach($this->requester, $request->id, $request->version, 'request', 0, $this->pdf());
        $this->assertNull($request->prepared_signed_at);
        $request = $signatures->sign($this->requester, $request->id, $request->version);
        $replacement = $signatures->upload($this->requester, $this->signatureFile());
        $this->assertNotSame($original, $replacement->id);
        $this->assertSame($original, $request->fresh()->prepared_signature_id);
        $this->actingAs($this->requester)->get(route('scm.signature-prepared', $request))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->flow->submit($this->requester, $request->id, $request->version);
    }

    public function test_signature_profile_and_document_images_enforce_owner_scope_and_file_validation(): void
    {
        $signatures = app(Signatures::class);
        $other = $this->user('scm.request');
        $request = $this->flow->saveDraft($other, $this->draftData());
        $this->rejects(fn () => $signatures->sign($other, $request->id, $request->version));
        $this->rejects(fn () => $signatures->upload($other, $this->pdf('pretend.png')));
        $profile = $signatures->current($this->requester);
        $this->actingAs($other)->get(route('scm.signature-profile', $profile))->assertForbidden();
        $this->actingAs($this->requester)->get(route('scm.signature-profile', $profile))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $request = $this->draft();
        $this->actingAs($other)->get(route('scm.signature-prepared', $request))->assertForbidden();
        $this->actingAs($this->scm)->get(route('scm.signature-prepared', $request))->assertForbidden();
        Livewire::actingAs($other)->test(PurchaseRequests::class)->set('selected', $request->id)->call('signPrepared')->assertNotFound();
        $this->rejects(fn () => $signatures->sign($this->requester, $request->id, $request->version - 1));
        $request = $this->submitApproved($request);
        $this->actingAs($this->scm)->get(route('scm.signature-prepared', $request))->assertOk();
        $this->rejects(fn () => $signatures->sign($this->requester, $request->id, $request->version));
        Livewire::actingAs($other)->test(SignatureProfile::class)
            ->assertSee('Belum ada gambar tanda tangan')->call('saveSignature')->assertHasErrors('signatureFile')
            ->set('signatureFile', $this->signatureFile())->call('saveSignature')->assertHasNoErrors()->assertSee('Ganti TTD profil');
        $this->assertNotNull($signatures->current($other));
        Livewire::actingAs($this->scm)->test(SignatureProfile::class)->assertForbidden();
    }

    #[DataProvider('kinds')]
    public function test_livewire_empty_optional_fields_are_stored_as_null(string $kind): void
    {
        $this->actingAs($this->requester);
        Livewire::test($kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class)
            ->call('newDraft')
            ->set('draft.title', 'Draft dengan tanggal opsional kosong')
            ->set('draft.reason', 'Regresi formulir browser SCM')
            ->set('draft.estimated_total', '1000')
            ->set('draft.items.0.name', 'Barang uji')
            ->set('draft.items.0.specification', 'Spesifikasi uji')
            ->set('draft.items.0.unit', 'unit')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $request = Procurement::sole();
        $this->assertSame($kind, $request->kind);
        $this->assertNull($request->getRawOriginal('needed_at'));
        $this->assertNull($request->getRawOriginal('location'));
        $this->assertNull($request->category_id);
        $this->assertNull($request->items->sole()->getRawOriginal('notes'));

        $this->assertSame(['date' => null, 'id' => null, 'nested' => ['notes' => null, 'zero' => '0', 'active' => false]],
            Validation::make(
                ['date' => '', 'id' => '', 'nested' => ['notes' => '', 'zero' => '0', 'active' => false]],
                ['date' => 'nullable|date', 'id' => 'nullable|integer', 'nested' => 'array'],
            )->validate());
    }

    public function test_editing_a_draft_preserves_its_historical_location_without_a_location_field(): void
    {
        $request = $this->draft();
        $request->update(['location' => 'Gudang lama', 'category_id' => $this->category->id]);
        $this->actingAs($this->requester);

        Livewire::test(PurchaseRequests::class)
            ->call('open', $request->id)
            ->call('editDraft')
            ->assertSet('draft.location', null)
            ->assertSet('draft.category_id', null)
            ->set('draft.category_id', 999999)
            ->set('draft.title', 'Kebutuhan diperbarui')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertSame('Gudang lama', $request->fresh()->location);
        $this->assertSame('Kebutuhan diperbarui', $request->fresh()->title);
        $this->assertSame($request->number, $request->fresh()->number);
        $this->assertEquals($this->category->id, $request->fresh()->category_id);
    }

    public function test_request_menus_separate_kinds_and_owners_from_the_scm_queue(): void
    {
        $pr = $this->draft('PR');
        $sr = $this->draft('SR');
        $other = $this->user('scm.request');
        $foreign = $this->flow->saveDraft($other, ['kind' => 'PR', 'date' => today()->toDateString(),
            'department_id' => $this->department->id, 'estimated_total' => '1000', 'title' => 'Permintaan user lain', 'reason' => 'Kebutuhan user lain',
            'items' => [['name' => 'Barang', 'specification' => 'Spesifikasi', 'quantity' => '1', 'unit' => 'unit']]]);

        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)
            ->assertSee($pr->number)->assertDontSee($sr->number)->assertDontSee($foreign->number)
            ->assertDontSee('Antrean SCM');
        Livewire::actingAs($this->requester)->test(ServiceRequests::class)
            ->assertSee($sr->number)->assertDontSee($pr->number);
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('open', $sr->id)->assertForbidden();
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('open', $foreign->id)->assertForbidden();
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->assertDontSee($pr->number)->assertDontSee($sr->number)->assertDontSee('Buat PR');
        $this->actingAs($this->requester)->get('/panel/permintaan/pr')->assertOk();
        $this->get('/panel/permintaan/sr')->assertOk();
        $this->get('/panel/scm/pengadaan')->assertForbidden();
        $this->actingAs($this->scm)->get('/panel/permintaan/pr')->assertForbidden();
    }

    public function test_request_kind_cannot_be_changed_through_a_different_menu(): void
    {
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('newDraft')
            ->set('draft.kind', 'SR')->call('saveDraft')->assertForbidden();
        $this->assertSame(0, Procurement::count());
        Livewire::actingAs($this->requester)->test(ServiceRequests::class)->call('newDraft')
            ->assertSet('draft.kind', 'SR')->call('switchSection', 'queue')->assertForbidden();
    }

    public function test_demo_seeder_assigns_categories_during_scm_receipt_and_remains_idempotent(): void
    {
        $this->seed(ScmDemoSeeder::class);
        $requests = Procurement::where('title', 'like', 'DEMO % sampai legalitas lengkap')->get();
        $this->assertCount(2, $requests);
        $this->assertEqualsCanonicalizing(['PR', 'SR'], $requests->pluck('kind')->all());
        $this->assertTrue($requests->every(fn ($request) => $request->status === 'completed' && $request->category_id !== null));
        $this->seed(ScmDemoSeeder::class);
        $this->assertSame(2, Procurement::where('title', 'like', 'DEMO % sampai legalitas lengkap')->count());
    }

    public function test_scm_receives_a_submitted_request_without_any_category_choice(): void
    {
        $request = $this->draft();
        $this->assertNull($request->category_id);
        $request = $this->submitApproved($request);
        $this->category->update(['is_active' => false]);
        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertNull($request->fresh()->category_id);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('open', $request->id)
            ->assertDontSee('Kategori pengadaan')->call('receiveRequest')->assertHasNoErrors()->assertSee('Diterima SCM');
        $this->assertSame('sourcing', $request->fresh()->status);
        $this->assertNull($request->fresh()->category_id);
        $this->assertSame($request->number, $request->fresh()->number);
        $audit = Audit::where('request_id', $request->id)->where('action', 'perubahan_proses')->latest('id')->firstOrFail();
        $this->assertNull($audit->changes['before']['category_id']);
        $this->assertNull($audit->changes['after']['category_id']);
    }

    public function test_request_numbers_start_at_one_and_continue_across_months_with_a_new_year_series(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->assertSame('001/APCA-HO/PR/X/2026', $this->draft()->number);
        $this->assertSame('002/APCA-HO/PR/X/2026', $this->draft()->number);
        $this->assertSame('001/APCA-HO/SR/X/2026', $this->draft('SR')->number);
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertSame('003/APCA-HO/PR/XI/2026', $this->draft()->number);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('001/APCA-HO/PR/I/2027', $this->draft()->number);
        $this->travelBack();
    }

    public function test_reserved_number_is_not_reused_and_cannot_create_two_requests(): void
    {
        $numbering = app(RequestNumbering::class);
        $unused = $numbering->reserve($this->requester, 'PR');
        $number = $numbering->reserve($this->requester, 'PR');
        $this->assertNotSame($unused->number, $number->number);
        $request = $this->flow->saveDraft($this->requester, $this->draftData(), numberId: $number->id);
        $this->assertSame($number->number, $request->number);
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, $this->draftData(), numberId: $number->id));
        $this->assertSame(1, Procurement::count());
        $this->assertNull($unused->fresh()->request_id);
    }

    public function test_company_number_series_are_independent_and_preserve_existing_apca_numbers(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23));
        $apca = $this->draft();
        $this->assertSame('APCA', $apca->company);
        $this->assertSame('001/APCA-HO/PR/X/2026', $apca->number);
        foreach (['PR', 'SR'] as $kind) {
            $first = $this->flow->saveDraft($this->requester, [...$this->draftData($kind), 'company' => 'KPMOG']);
            $second = $this->flow->saveDraft($this->requester, [...$this->draftData($kind), 'company' => 'KPMOG']);
            $this->assertSame('001/KPMOG-OFC/'.$kind.'/X/2026', $first->number);
            $this->assertSame('002/KPMOG-OFC/'.$kind.'/X/2026', $second->number);
            $this->assertSame('KPMOG', $first->company);
            $this->assertSame('KPMOG', RequestNumber::where('request_id', $first->id)->sole()->company);
        }
        $this->assertSame('002/APCA-HO/PR/X/2026', $this->draft()->number);
        $this->assertSame('001/APCA-HO/SR/X/2026', $this->draft('SR')->number);
        $this->travelTo(now()->setDate(2026, 11, 1));
        $next = app(RequestNumbering::class)->reserve($this->requester, 'PR', 'KPMOG');
        $this->assertSame('003/KPMOG-OFC/PR/XI/2026', $next->number);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('001/KPMOG-OFC/PR/I/2027', app(RequestNumbering::class)->reserve($this->requester, 'PR', 'KPMOG')->number);
        $this->assertSame('001/APCA-HO/PR/X/2026', $apca->fresh()->number);
        $this->travelBack();
    }

    #[DataProvider('kinds')]
    public function test_company_choice_opens_the_correct_form_and_survives_edit_send_and_scm_receipt(string $kind): void
    {
        $pageClass = $kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class;
        $page = Livewire::actingAs($this->requester)->test($pageClass)
            ->call('chooseCompany')->assertSee('Buat '.$kind.' untuk perusahaan')->assertSee('KPMOG')->assertSee('APCA');
        $this->assertSame(0, RequestNumber::count());
        $page->call('switchSection', 'mine')->assertSet('choosingCompany', false);
        $page->call('chooseCompany')->call('newDraft', 'KPMOG')->assertHasNoErrors()
            ->assertSet('draft.company', 'KPMOG')->assertSee('Brand')->assertSee('Item')->assertSee('Requested by')
            ->assertDontSee('Delivery Point')->assertDontSee('Model/PN')->assertDontSee('Prepared by')->assertDontSee('KPM_OG-FRM-003')
            ->assertSee('img/brands/kpmog-request.jpg', false)->assertDontSee('img/brands/apca.png', false);
        $number = $page->get('requestNumber');
        $data = [...$this->draftData($kind), 'company' => 'KPMOG', 'customer_po' => 'PO-DEMO', 'job_order' => 'JO-DEMO'];
        $data['items'][0] += ['brand' => 'DEMO Brand', 'item_reference' => 'ITEM-DEMO-123'];
        $page->set('draft', $data)->call('saveDraft')->assertHasNoErrors();
        $request = Procurement::sole();
        $this->assertSame('DEMO Brand', $request->items->first()->brand);
        $this->assertSame('ITEM-DEMO-123', $request->items->first()->item_reference);
        $page->call('editDraft')->assertSet('draft.company', 'KPMOG')->assertSet('draft.items.0.brand', 'DEMO Brand')
            ->assertSet('draft.items.0.item_reference', 'ITEM-DEMO-123')
            ->call('signPrepared')->assertHasNoErrors()->call('submitRequest')->assertHasNoErrors();
        $this->approve($request->fresh());
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('open', $request->id)
            ->assertSee($number)->assertSee('img/brands/kpmog-request.jpg', false)->assertSee('DEMO Brand')
            ->call('receiveRequest')->assertHasNoErrors()->call('showTab', 'request')
            ->assertSee('ITEM-DEMO-123')->assertSee('img/brands/kpmog-request.jpg', false);
        $this->assertSame($number, $request->fresh()->number);
        $this->assertSame('KPMOG', $request->fresh()->company);
        $this->actingAs($this->user('scm.request'))->get($pageClass::getUrl(['permintaan' => $request->id]))->assertForbidden();

        $request = $this->vendors($request->fresh());
        foreach ($request->candidates as $candidate) {
            $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version, $this->quoteData($request, $candidate->id));
        }
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $this->cbeData($request), true);
        $this->assertSame('KPMOG', $request->cbe->snapshot['request']['company']);
        $this->actingAs($this->requester)->get(route('scm.print', $request->cbe))->assertOk()->assertSee('Perusahaan: KPMOG');
        $this->get(route('scm.selected-quotation', $request->cbe))->assertOk()->assertSee('Perusahaan: KPMOG');
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        $request = $this->flow->complete($this->scm, $request->id, $request->version);
        $this->assertSame('completed', $request->status);
        $this->assertSame('KPMOG', $request->company);

        $page = Livewire::actingAs($this->requester)->test($pageClass)->call('chooseCompany')->call('newDraft', 'APCA')
            ->assertSee('img/brands/apca.png', false)->assertDontSee('Delivery Point')->assertDontSee('Model/PN')->assertSee('Requested by');
        $this->assertStringContainsString('/APCA-HO/'.$kind.'/', $page->get('requestNumber'));
    }

    public function test_company_and_reserved_number_cannot_be_switched_or_forged(): void
    {
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, [...$this->draftData(), 'company' => 'OTHER']));
        $number = app(RequestNumbering::class)->reserve($this->requester, 'PR', 'KPMOG');
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, $this->draftData(), numberId: $number->id));
        $this->assertNull($number->fresh()->request_id);
        $request = $this->flow->saveDraft($this->requester, [...$this->draftData(), 'company' => 'KPMOG'], numberId: $number->id);
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, [...$this->draftData(), 'company' => 'APCA'], $request->id, $request->version));
        $this->assertSame('KPMOG', $request->fresh()->company);
        $this->assertSame($number->number, $request->fresh()->number);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('chooseCompany')->assertForbidden();
    }

    public function test_company_schema_upgrade_preserves_legacy_numbers_and_blocks_a_lossy_downgrade(): void
    {
        $request = $this->draft();
        $migration = require database_path('migrations/2026_10_23_110000_add_scm_request_companies.php');
        $migration->down();
        $migration->up();
        $this->assertSame('APCA', $request->fresh()->company);
        $this->assertSame($request->number, $request->fresh()->number);
        $this->assertStringStartsWith('002/APCA-HO/PR/', app(RequestNumbering::class)->reserve($this->requester, 'PR')->number);
        $this->assertStringStartsWith('001/KPMOG-OFC/PR/', app(RequestNumbering::class)->reserve($this->requester, 'PR', 'KPMOG')->number);
        try {
            $migration->down();
            $this->fail('Downgrade tidak boleh membuang identitas perusahaan KPMOG.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('tidak dapat dibatalkan', $error->getMessage());
        }
        $this->assertSame('APCA', $request->fresh()->company);
        $this->assertSame(1, RequestNumber::where('company', 'KPMOG')->count());
    }

    public function test_a_requester_cannot_claim_another_users_reserved_number(): void
    {
        $number = app(RequestNumbering::class)->reserve($this->requester, 'PR');
        try {
            $this->flow->saveDraft($this->user('scm.request'), $this->draftData(), numberId: $number->id);
            $this->fail('Nomor pemohon lain seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, Procurement::count());
        $this->assertNull($number->fresh()->request_id);
    }

    public function test_invalid_direct_send_keeps_the_reserved_number_available_for_correction(): void
    {
        $data = $this->draftData();
        $data['items'][0]['quantity'] = '0';
        $page = Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('newDraft');
        $number = $page->get('requestNumber');
        $page->set('draft', $data)->call('signPrepared')->assertHasErrors();
        $this->assertSame(0, Procurement::count());
        $this->assertNull(RequestNumber::sole()->request_id);
        $page->set('draft.items.0.quantity', '1')->call('signPrepared')->assertHasNoErrors()->call('submitRequest')->assertHasNoErrors();
        $this->approve(Procurement::sole());
        $this->assertSame($number, Procurement::sole()->number);
        $this->assertNotNull(Procurement::sole()->submitted_at);
    }

    #[DataProvider('kinds')]
    public function test_number_appears_in_the_new_form_and_remains_through_direct_send_and_receipt(string $kind): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $page = Livewire::actingAs($this->requester)->test($kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class)
            ->call('newDraft')->assertSet('requestNumber', '001/APCA-HO/'.$kind.'/X/2026')
            ->assertSee('001/APCA-HO/'.$kind.'/X/2026')->assertDontSee('Kategori pengadaan');
        $this->assertSame(0, Procurement::count());
        $number = $page->get('requestNumber');
        $page->set('draft', $this->draftData($kind))->call('signPrepared')->assertHasNoErrors()->call('submitRequest')->assertHasNoErrors()
            ->assertSee('Menunggu persetujuan');
        $request = $this->approve(Procurement::sole());
        $this->assertSame($number, $request->number);
        $this->assertSame('Draft', Workflow::STATUSES[$request->status]);
        $this->assertNotNull($request->submitted_at);
        $this->assertEquals($request->id, RequestNumber::sole()->request_id);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('open', $request->id)
            ->call('receiveRequest')->assertHasNoErrors()->assertSee('Diterima SCM');
        $this->assertSame($number, $request->fresh()->number);
        $this->assertNotNull($request->fresh()->received_at);
        $this->travelBack();
    }

    public function test_pending_and_unsent_drafts_share_one_draft_filter_without_exposing_unsent_drafts_to_scm(): void
    {
        $unsent = $this->draft();
        $pending = $this->draft();
        $this->submitApproved($pending);
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->set('statusFilter', 'draft')
            ->assertSee($unsent->number)->assertSee($pending->number)
            ->assertViewHas('summary', fn ($summary) => $summary['draft'] === 2)
            ->assertViewHas('statusOptions', fn ($options) => count(array_keys($options, 'Draft')) === 1);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->set('statusFilter', 'draft')
            ->assertSee($pending->number)->assertDontSee($unsent->number)
            ->assertViewHas('summary', fn ($summary) => $summary['draft'] === 1);
    }

    #[DataProvider('kinds')]
    public function test_pr_and_sr_complete_with_multiple_items_private_files_and_audit(string $kind): void
    {
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        Requirement::create(['category_id' => $this->category->id, 'document_type_id' => $type->id, 'is_required' => true]);
        $r = $this->finalized($kind);
        $this->assertSame('selected', $r->status);
        $snapshot = $r->cbe->snapshot;
        $vendor = Vendor::findOrFail($r->cbe->selected_vendor_id);
        $this->flow->saveVendor($this->scm, [...$vendor->getAttributes(), 'name' => 'Profil berubah setelah final'], $vendor->id, $vendor->version);
        $this->assertSame($snapshot, $r->cbe->fresh()->snapshot);
        $r = $this->flow->startLegal($this->scm, $r->id, $r->version);
        $doc = $this->flow->saveDocument($this->scm, ['vendor_id' => $vendor->id, 'document_type_id' => $type->id, 'number' => '0000123', 'title' => 'NIB uji'], $this->pdf());
        $this->assertSame('0000123', $doc->number);
        $item = $r->legalCase->items->first();
        $this->rejects(fn () => $this->flow->complete($this->scm, $r->id, $r->version));
        $r = $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $doc->id]);
        $r = $this->flow->complete($this->scm, $r->id, $r->version);
        $this->assertSame('completed', $r->fresh()->status);
        $this->assertNotNull($r->completed_at);
        $this->assertSame($doc->id, $r->legalCase->items->first()->vendor_document_id);
        $this->assertTrue(Audit::where('request_id', $r->id)->where('action', 'finalisasi_cbe')->where('actor_id', $this->scm->id)->whereNotNull('created_at')->exists());
        $this->actingAs($this->scm)->get(route('scm.download', $doc->attachments->first()))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('scm.print', $r->cbe))->assertOk()->assertSee('Commercial Bid Evaluation');
        $this->actingAs($this->requester)->get(route('scm.download', $doc->attachments->first()))->assertForbidden();
        $this->get(route('scm.print', $r->cbe))->assertOk();
        $this->rejects(fn () => $this->flow->changeRequirement($this->scm, $r->id, $r->version, $type->id, false, 'Tidak boleh mengubah historis'));
    }

    public function test_money_is_exact_and_server_ignores_submitted_total(): void
    {
        $r = $this->vendors($this->sourcing());
        $data = $this->quoteData($r, $r->candidates->first()->id);
        $data['total_minor'] = 1;
        $r = $this->flow->saveQuotation($this->scm, $r->id, $r->version, $data);
        $quote = $r->candidates->first()->quotations->first();
        $this->assertSame(36237, (int) $quote->total_minor);
        $this->assertSame(2, Decimal::gross(100, 20));
        $this->rejects(fn () => Decimal::parse(''));
        $this->rejects(fn () => Decimal::parse('-1'));
        $this->rejects(fn () => Decimal::parse(['100']));
        $data['lines'][0]['discount'] = '999999';
        $this->rejects(fn () => $this->flow->saveQuotation($this->scm, $r->id, $r->version, $data));
        $this->assertSame(1, Quotation::count());
    }

    public function test_ho_approval_uses_the_estimate_and_does_not_expose_cbe_or_vendor_files_to_approvers(): void
    {
        $request = $this->vendors($this->sourcing());
        foreach ($request->candidates as $candidate) {
            $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version, $this->quoteData($request, $candidate->id, '6000000'));
        }
        $quote = $request->candidates->first()->quotations->first();
        $request = $this->flow->attach($this->scm, $request->id, $request->version, 'quotation', $quote->id, $this->pdf());
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $this->cbeData($request), true);
        $this->assertGreaterThan(Approvals::LIMIT_MINOR, $request->cbe->final_minor);
        $this->assertLessThan(Approvals::LIMIT_MINOR, $request->estimated_total_minor);
        $route = app(Approvals::class)->route('APCA', $request->department_id);
        $this->assertSame($route->cbo_id, $request->approvals()->where('role', 'approved')->sole()->user_id);
        $approver = $request->approvals()->where('role', 'acknowledged')->sole()->user;
        $this->actingAs($approver)->get(ScmApprovals::getUrl(['permintaan' => $request->id]))->assertOk();
        $this->get(route('scm.signature-prepared', $request))->assertOk();
        $this->get(route('scm.print', $request->cbe))->assertForbidden();
        $this->get(route('scm.selected-quotation', $request->cbe))->assertForbidden();
        $this->get(route('scm.download', $quote->attachments->sole()))->assertForbidden();
        $this->actingAs($this->requester)->get(route('scm.download', $quote->attachments->sole()))->assertOk();
    }

    public function test_ownership_explicit_permissions_and_department_scope_are_enforced(): void
    {
        $r = $this->draft();
        $other = $this->user('scm.request');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertFalse(Access::manages($admin));
        $this->assertFalse(Access::views($other, $r));
        $this->actingAs($other)->get('/panel/scm/pengadaan?permintaan='.$r->id)->assertForbidden();
        $this->actingAs($admin)->get('/panel/scm/pengadaan')->assertForbidden();
        $r = $this->submitApproved($r);
        $this->scm->employee->update(['department_id' => Department::create(['code' => 'OTHER', 'name' => 'Lain', 'is_active' => true])->id]);
        $this->scm->unsetRelation('employee');
        $this->assertFalse(Access::manages($this->scm, $r));
    }

    public function test_illegal_transitions_duplicate_vendors_and_stale_versions_are_rejected(): void
    {
        $r = $this->draft();
        $this->rejects(fn () => $this->flow->submit($this->requester, $r->id, $r->version + 1));
        $r = $this->submitApproved($r);
        $this->rejects(fn () => $this->flow->startLegal($this->scm, $r->id, $r->version));
        $r = $this->flow->receive($this->scm, $r->id, $r->version, $this->category->id);
        $r = $this->vendors($r);
        $this->rejects(fn () => $this->flow->addCandidate($this->scm, $r->id, $r->version, ['vendor_id' => $r->candidates->first()->vendor_id, 'followup_status' => 'diminta']));
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $r->id, $r->version, ['date' => today()->toDateString(), 'quotation_ids' => [], 'selected_vendor_id' => null], true));
    }

    public function test_incomparable_currency_quantity_and_winner_are_rejected(): void
    {
        $r = $this->quoted();
        $cbe = $this->cbeData($r);
        $quote = Quotation::firstOrFail();
        $quote->update(['currency' => 'USD']);
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $r->id, $r->version, $cbe, true));
        $quote->update(['currency' => 'IDR']);
        $line = $quote->lines->first();
        $qty = $line->quantity_milli;
        $line->update(['quantity_milli' => $qty + 1]);
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $r->id, $r->version, $cbe, true));
        $line->update(['quantity_milli' => $qty]);
        $cbe['selected_vendor_id'] = 99999;
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $r->id, $r->version, $cbe, true));
    }

    public function test_cbe_accepts_more_than_three_vendors_and_a_winner_based_on_specifications(): void
    {
        $request = $this->quoted();
        $data = $this->cbeData($request);
        $data['quotation_ids'] = array_slice($data['quotation_ids'], 0, 2);
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true));
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data);

        $vendor = $this->flow->saveVendor($this->scm, ['code' => 'V4', 'name' => 'Vendor spesifikasi sesuai', 'provider_type' => 'barang', 'is_active' => true]);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'quotes')->assertSee('Tambah kandidat')
            ->set('candidate', ['vendor_id' => $vendor->id, 'requested_at' => today()->toDateString(), 'followup_status' => 'diminta'])
            ->call('addCandidate')->assertHasNoErrors();
        $request = $request->fresh();
        $this->assertSame('evaluation', $request->status);
        $candidate = $request->candidates()->where('vendor_id', $vendor->id)->sole();
        $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version, $this->quoteData($request, $candidate->id, '150'));
        $data = $this->cbeData($request);
        $data['selected_vendor_id'] = $vendor->id;
        $data['selection_reason'] = 'Spesifikasi sesuai kebutuhan meskipun harga bukan yang terendah.';
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true);

        $this->assertSame($vendor->id, $request->cbe->selected_vendor_id);
        $this->assertCount(4, $request->cbe->snapshot['quotes']);
        $this->assertSame($request->id, $request->cbe->request_id);
        $this->assertGreaterThan(min(array_column($request->cbe->snapshot['quotes'], 'total_minor')), $request->cbe->final_minor);
        $this->actingAs($this->scm)->get(route('scm.print', $request->cbe))->assertOk()
            ->assertSee($request->number)->assertSee($vendor->name)->assertSee($data['selection_reason']);
        $this->rejects(fn () => $this->flow->addCandidate($this->scm, $request->id, $request->version, ['vendor_id' => $vendor->id, 'followup_status' => 'diminta']));
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        $this->assertSame($vendor->id, $request->legalCase->vendor_id);
        $this->assertSame($request->cbe->id, $request->legalCase->cbe_id);
    }

    public function test_cbe_rejects_two_versions_from_one_vendor_even_with_three_distinct_vendors(): void
    {
        $request = $this->quoted();
        $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version, $this->quoteData($request, $request->candidates->first()->id, '99'));
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $request->id, $request->version, $this->cbeData($request), true));
        $this->assertDatabaseMissing('scm_cbes', ['request_id' => $request->id, 'status' => 'final']);
    }

    public function test_cbe_master_form_reconciles_costs_and_freezes_header_details(): void
    {
        $request = $this->quoted();
        $request->update(['location' => 'Proyek uji CBE', 'needed_at' => '2026-11-15', 'notes' => 'Komentar uji CBE']);
        $data = $this->cbeData($request);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'cbe')
            ->assertSee('Belum ada penawaran yang dipilih')
            ->set('cbeForm', $data)->call('saveCbe')->assertHasNoErrors()
            ->assertSee('SUMMARY OF BIDS')->assertSee('Proyek uji CBE')->assertSee('15/11/2026');

        $request = $request->fresh();
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true);
        $this->assertSame('Departemen uji SCM', $request->cbe->snapshot['request']['department_name']);
        $this->assertSame($this->scm->name, $request->cbe->snapshot['prepared_by_name']);
        $this->department->update(['name' => 'Departemen berubah']);
        $request->update(['location' => 'Proyek berubah', 'notes' => 'Komentar berubah']);

        $response = $this->actingAs($this->scm)->get(route('scm.print', $request->cbe))->assertOk()
            ->assertSee('Proyek uji CBE')->assertSee('Departemen uji SCM')->assertSee('Komentar uji CBE')
            ->assertSee('15/11/2026')->assertDontSee('Proyek berubah')->assertDontSee('Departemen berubah');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['Sub Total' => 'Rp 350,04', 'Discount' => 'Rp 0,02', 'Total' => 'Rp 360,27',
            'PPN 11%' => 'Rp 39,63', 'Grand Total' => 'Rp 399,90', 'Differences' => '-Rp 600,10'] as $label => $amount) {
            $cell = $xpath->query('//tr[th[normalize-space(.)="'.$label.'"]]/td[contains(@class,"scm-cbe-money")]')->item(0);
            $this->assertNotNull($cell, $label);
            $this->assertStringContainsString($amount, $cell->textContent, $label);
        }
        $this->assertSame(11, $xpath->query('//tr[@data-cbe-row="subtotal"]/*')->length);
        $this->assertSame(16, $xpath->query('//tr[contains(@class,"scm-cbe-item-row") or contains(@class,"scm-cbe-blank")]')->length);
        $this->assertSame(0, $xpath->query('//tr[@data-cbe-row="tax_status"]')->length);
        $this->assertSame(4, $xpath->query('//div[contains(@class,"scm-cbe-signature-space")]')->length);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'cbe')
            ->assertSee('Proyek uji CBE')->assertDontSee('Proyek berubah')->assertDontSee('Simpan draft CBE');
    }

    public function test_cbe_master_form_does_not_compare_foreign_currency_to_idr_budget(): void
    {
        $request = $this->vendors($this->sourcing());
        foreach ($request->candidates as $candidate) {
            $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version,
                [...$this->quoteData($request, $candidate->id), 'currency' => 'USD']);
        }
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $this->cbeData($request), true);
        $this->actingAs($this->scm)->get(route('scm.print', $request->cbe))->assertOk()
            ->assertSee('USD 399,90')->assertSee('Belum dikonversi')->assertSee('Belum dapat dibandingkan');

        $legacy = clone $request->cbe;
        $snapshot = $legacy->snapshot;
        unset($snapshot['request']['department_name'], $snapshot['request']['location'], $snapshot['request']['notes'],
            $snapshot['request']['needed_at'], $snapshot['prepared_by_name']);
        foreach ($snapshot['quotes'] as &$quote) {
            unset($quote['evaluation']);
        }
        unset($quote);
        $legacy->snapshot = $snapshot;
        $html = view('scm.cbe-print', ['cbe' => $legacy])->render();
        $this->assertStringContainsString('SUMMARY OF BIDS', $html);
        $this->assertStringContainsString('Belum dicatat', $html);
        $this->assertStringContainsString('USD 362,37', $html);
        $this->assertStringNotContainsString('PPN 11%', $html);
    }

    public function test_cbe_non_pkp_and_optional_discount_are_saved_per_quote_and_frozen(): void
    {
        $request = $this->quoted();
        $data = $this->cbeData($request);
        $first = $data['quotation_ids'][0];
        $second = $data['quotation_ids'][1];
        $data['commercial_terms'] = [$first => ['non_pkp' => true, 'discount' => '10'],
            $second => ['non_pkp' => false, 'discount' => '10']];
        $original = Quotation::with('lines')->findOrFail($first)->toArray();
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'cbe')->set('cbeForm', $data)
            ->assertSee('PPN 11%')->assertSee('Non PKP: PPN tidak aktif')
            ->call('saveCbe')->assertHasNoErrors()
            ->assertSet('cbeForm.commercial_terms.'.$first.'.non_pkp', true)
            ->assertSet('cbeForm.commercial_terms.'.$first.'.discount', '10,00');
        $page->call('finalizeCbe')->assertHasNoErrors()->assertDontSee('Simpan draft CBE');
        $request = $request->fresh();
        $this->assertSame(35027, $request->cbe->final_minor);
        $quotes = collect($request->cbe->snapshot['quotes']);
        $this->assertSame(0, $quotes->firstWhere('id', $first)['evaluation']['tax_minor']);
        $this->assertSame(3853, $quotes->firstWhere('id', $second)['evaluation']['tax_minor']);
        $this->assertSame(38880, $quotes->firstWhere('id', $second)['evaluation']['total_minor']);
        $this->assertSame($original, Quotation::with('lines')->findOrFail($first)->toArray());
        config(['scm.cbe_vat_percent' => 15]);
        $this->actingAs($this->scm)->get(route('scm.print', $request->cbe))->assertOk()
            ->assertSee('PPN 11%')->assertDontSee('PPN 15%')->assertSee('Non PKP')->assertSee('Rp 350,27');
        $this->get(route('scm.selected-quotation', $request->cbe))->assertOk()
            ->assertSee('Total setelah evaluasi CBE: IDR 350,27')->assertSee('Total penawaran: IDR 362,37');
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $request->id, $request->version, $data));
    }

    public function test_cbe_recalculates_ppn_when_non_pkp_is_unchecked_and_rejects_invalid_discounts(): void
    {
        $request = $this->quoted();
        $data = $this->cbeData($request);
        $quote = $data['quotation_ids'][0];
        $data['commercial_terms'] = [$quote => ['non_pkp' => true, 'discount' => '']];
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data);
        $this->assertSame('0,00', $request->cbe->commercial_terms[$quote]['discount']);
        foreach (['-1', '351', ['100'], 'abc'] as $discount) {
            $invalid = $data;
            $invalid['commercial_terms'][$quote]['discount'] = $discount;
            $this->rejects(fn () => $this->flow->saveCbe($this->scm, $request->id, $request->version, $invalid));
        }
        $data['commercial_terms'][$quote] = ['non_pkp' => false, 'discount' => ''];
        $data['vat_percent'] = 0;
        $data['final_minor'] = 1;
        $data['commercial_terms'][$quote]['tax_minor'] = 0;
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true));
        unset($data['commercial_terms'][$quote]['tax_minor']);
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true);
        $this->assertSame(39990, $request->cbe->final_minor);
        $this->assertSame(11, $request->cbe->vat_percent);
        $this->assertSame(3963, $request->cbe->snapshot['quotes'][0]['evaluation']['tax_minor']);
    }

    #[DataProvider('kinds')]
    public function test_vendor_without_sbu_or_nib_can_complete_with_both_npwp_and_ktp(string $kind): void
    {
        foreach (DocumentType::whereIn('code', ['SBU', 'NIB', 'NPWP'])->get() as $type) {
            Requirement::create(['category_id' => $this->category->id, 'document_type_id' => $type->id, 'is_required' => true]);
        }
        $request = $this->finalized($kind);
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        $sbu = DocumentType::where('code', 'SBU')->sole();
        $item = $request->legalCase->items()->where('document_type_id', $sbu->id)->sole();
        $request = $this->flow->updateLegal($this->scm, $request->id, $request->version, $item->id, ['status' => 'perlu_perbaikan', 'correction_notes' => 'Vendor tidak memiliki dokumen SBU.']);

        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'legal')
            ->set('alternativeDocumentReason', 'Vendor tidak memiliki SBU/NIB, menggunakan NPWP dan KTP.')
            ->call('useAlternativeDocuments')->assertHasNoErrors()
            ->assertSee('Pengganti SBU/NIB: NPWP dan KTP wajib diterima beserta file.');
        $request = $request->fresh();
        $this->assertSame('npwp_ktp', $request->legalCase->document_basis);
        $items = $request->legalCase->items->keyBy('document_type_id');
        foreach (DocumentType::whereIn('code', ['SBU', 'NIB'])->get() as $type) {
            $this->assertFalse($items[$type->id]->is_required);
            $this->assertTrue(Requirement::where('category_id', $this->category->id)->where('document_type_id', $type->id)->sole()->is_required);
        }
        $this->assertDatabaseHas('scm_audits', ['request_id' => $request->id, 'action' => 'pengganti_npwp_ktp', 'actor_id' => $this->scm->id]);
        $this->rejects(fn () => $this->flow->complete($this->scm, $request->id, $request->version));
        $request = $this->acceptLegalDocument($request, DocumentType::where('code', 'NPWP')->sole());
        $this->rejects(fn () => $this->flow->complete($this->scm, $request->id, $request->version));
        $request = $this->acceptLegalDocument($request, DocumentType::where('code', 'KTP')->sole());
        $request = $this->flow->complete($this->scm, $request->id, $request->version);
        $this->assertSame('completed', $request->status);
        $this->assertSame(2, VendorDocument::count());
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, 'Tidak boleh mengubah pengadaan yang sudah selesai.'));
    }

    public function test_alternative_document_selection_keeps_permission_version_and_active_type_guards(): void
    {
        $request = $this->finalized();
        $reason = 'Vendor tidak memiliki SBU/NIB sehingga memakai NPWP dan KTP.';
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, $reason));
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        try {
            $this->flow->useAlternativeDocuments($this->requester, $request->id, $request->version, $reason);
            $this->fail('Pemohon tidak boleh mengganti dokumen legalitas SCM.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, ''));
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version + 1, $reason));
        $ktp = DocumentType::where('code', 'KTP')->sole();
        $ktp->update(['is_active' => false]);
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, $reason));
        $this->assertSame('requirements', $request->fresh()->legalCase->document_basis);
        $this->assertSame(0, $request->legalCase->items()->count());
        $ktp->update(['is_active' => true]);
        $request = $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, $reason);
        $this->rejects(fn () => $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, $reason));
        $this->rejects(fn () => $this->flow->changeRequirement($this->scm, $request->id, $request->version, $ktp->id, false, 'Tidak boleh menghapus kewajiban dokumen pengganti.'));
        $this->rejects(fn () => $this->flow->changeRequirement($this->scm, $request->id, $request->version, DocumentType::where('code', 'SBU')->sole()->id, true, 'SBU sudah digantikan dengan NPWP dan KTP.'));
    }

    public function test_alternative_documents_still_require_readable_files_and_preserve_type_snapshots(): void
    {
        $request = $this->finalized();
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        $request = $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, 'Vendor menggunakan NPWP dan KTP sebagai pengganti SBU/NIB.');
        $npwp = DocumentType::where('code', 'NPWP')->sole();
        $ktp = DocumentType::where('code', 'KTP')->sole();
        $request = $this->acceptLegalDocument($request, $npwp);
        $request = $this->acceptLegalDocument($request, $ktp);
        $file = $request->legalCase->items()->where('document_type_id', $ktp->id)->sole()->document->attachments->sole();
        $content = Storage::disk('scm')->get($file->path);
        Storage::disk('scm')->delete($file->path);
        $this->rejects(fn () => $this->flow->complete($this->scm, $request->id, $request->version));
        Storage::disk('scm')->put($file->path, $content);
        $ktp->update(['code' => 'KTP-BARU', 'name' => 'Nama master berubah']);
        $this->rejects(fn () => $this->flow->changeRequirement($this->scm, $request->id, $request->version, $ktp->id, false, 'Penggantian nama tidak mengubah kewajiban dokumen.'));
        $request = $this->flow->complete($this->scm, $request->id, $request->version);
        $this->assertSame('completed', $request->status);
        $this->assertSame('KTP', $request->legalCase->items()->where('document_type_id', $ktp->id)->sole()->document_name);
    }

    public function test_alternative_documents_do_not_remove_other_required_documents(): void
    {
        $extra = DocumentType::create(['code' => 'LAIN', 'name' => 'Dokumen tambahan uji', 'is_active' => true, 'has_expiry' => false]);
        Requirement::create(['category_id' => $this->category->id, 'document_type_id' => $extra->id, 'is_required' => true]);
        $request = $this->finalized();
        $request = $this->flow->startLegal($this->scm, $request->id, $request->version);
        $request = $this->flow->useAlternativeDocuments($this->scm, $request->id, $request->version, 'Vendor tidak memiliki SBU/NIB dan menggantinya dengan NPWP serta KTP.');
        $request = $this->acceptLegalDocument($request, DocumentType::where('code', 'NPWP')->sole());
        $request = $this->acceptLegalDocument($request, DocumentType::where('code', 'KTP')->sole());
        $this->rejects(fn () => $this->flow->complete($this->scm, $request->id, $request->version));
        $this->assertTrue($request->legalCase->items()->where('document_type_id', $extra->id)->sole()->is_required);
        $request = $this->acceptLegalDocument($request, $extra);
        $this->assertSame('completed', $this->flow->complete($this->scm, $request->id, $request->version)->status);
    }

    #[DataProvider('configuredDocumentDates')]
    public function test_nib_upload_preserves_dates_after_enabling_expiry_in_settings(?string $expiresAt): void
    {
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        $configurator = $this->user('scm.configure');
        Livewire::actingAs($configurator)->test(ScmProcurement::class)
            ->call('editMaster', 'type', $type->id)
            ->set('master.has_expiry', true)->call('saveMaster')->assertHasNoErrors();
        $this->assertTrue($type->fresh()->has_expiry);
        $this->assertDatabaseHas('scm_audits', ['actor_id' => $configurator->id, 'entity_id' => $type->id, 'action' => 'pengaturan_master']);
        $vendor = $this->flow->saveVendor($this->scm, ['code' => 'NIB-TEST', 'name' => 'Vendor uji NIB', 'provider_type' => 'keduanya', 'is_active' => true]);

        $component = Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->set('document', ['vendor_id' => $vendor->id, 'document_type_id' => $type->id, 'number' => '0000123',
                'title' => 'NIB uji', 'issued_at' => '2026-01-01', 'expires_at' => $expiresAt, 'notes' => ''])
            ->set('documentFile', $this->pdf())
            ->call('saveDocument')
            ->assertHasNoErrors();

        $document = VendorDocument::sole();
        $this->assertSame($expiresAt, $document->expires_at?->toDateString());
        $this->assertSame('2026-01-01', $document->issued_at->toDateString());
        $this->assertSame('0000123', $document->number);
        Storage::disk('scm')->assertExists($document->attachments->sole()->path);
        $this->assertNull($component->get('documentFile'));
    }

    public static function configuredDocumentDates(): array
    {
        return ['empty' => [null], 'future' => ['2027-01-01']];
    }

    public function test_inactive_document_types_and_invalid_sbu_date_ranges_are_rejected(): void
    {
        $vendor = $this->flow->saveVendor($this->scm, ['code' => 'EXPIRY-TEST', 'name' => 'Vendor uji tanggal', 'provider_type' => 'keduanya', 'is_active' => true]);
        $nib = DocumentType::where('code', 'NIB')->firstOrFail();
        $metadata = ['vendor_id' => $vendor->id, 'document_type_id' => $nib->id, 'number' => '0000123',
            'title' => 'Dokumen uji', 'issued_at' => '2026-01-01', 'expires_at' => '2025-01-01'];

        $this->rejects(fn () => $this->flow->saveDocument($this->scm, [...$metadata, 'expires_at' => '2027-01-01'], $this->pdf()));
        $nib->update(['has_expiry' => true]);
        $this->rejects(fn () => $this->flow->saveDocument($this->scm, $metadata, $this->pdf()));
        $nib->update(['is_active' => false]);
        $this->rejects(fn () => $this->flow->saveDocument($this->scm, $metadata, $this->pdf()));
        $sbu = DocumentType::where('code', 'SBU')->firstOrFail();
        $this->rejects(fn () => $this->flow->saveDocument($this->scm, [...$metadata, 'document_type_id' => $sbu->id], $this->pdf()));
        $this->assertSame(0, VendorDocument::count());

        $document = $this->flow->saveDocument($this->scm, [...$metadata, 'document_type_id' => $sbu->id, 'expires_at' => '2027-01-01'], $this->pdf());
        $this->assertSame('2027-01-01', $document->expires_at->toDateString());
    }

    public function test_document_versions_reuse_expiry_and_requirement_snapshots(): void
    {
        $type = DocumentType::where('code', 'SBU')->firstOrFail();
        $requirement = Requirement::create(['category_id' => $this->category->id, 'document_type_id' => $type->id, 'is_required' => true]);
        $r = $this->finalized();
        $r = $this->flow->startLegal($this->scm, $r->id, $r->version);
        $requirement->update(['is_required' => false]);
        $this->assertTrue($r->legalCase->items->first()->is_required);
        $metadata = ['vendor_id' => $r->legalCase->vendor_id, 'document_type_id' => $type->id, 'number' => '0007', 'title' => 'SBU fiktif', 'issued_at' => '2025-01-01', 'expires_at' => today()->subDay()->toDateString()];
        $expired = $this->flow->saveDocument($this->scm, $metadata, $this->pdf());
        $item = $r->legalCase->items->first();
        $r = $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $expired->id]);
        $this->rejects(fn () => $this->flow->complete($this->scm, $r->id, $r->version));
        $valid = $this->flow->saveDocument($this->scm, [...$metadata, 'expires_at' => today()->addYear()->toDateString()], $this->pdf());
        $this->assertSame(2, $valid->version);
        Storage::disk('scm')->assertExists($expired->attachments->first()->path);
        $r = $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'perlu_perbaikan', 'vendor_document_id' => $valid->id, 'correction_notes' => 'Salinan perlu diperjelas']);
        $this->rejects(fn () => $this->flow->complete($this->scm, $r->id, $r->version));
        $r = $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $valid->id]);
        $r = $this->flow->complete($this->scm, $r->id, $r->version);
        $this->travel(2)->years();
        $this->assertSame('completed', $r->fresh()->status);
        $this->travelBack();
    }

    public function test_upload_signature_and_direct_download_ownership(): void
    {
        $r = $this->draft();
        $this->rejects(fn () => $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, UploadedFile::fake()->createWithContent('pretend.pdf', '<?php echo 1;')));
        $r = $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, $this->pdf('../name.pdf'));
        $file = $r->attachments->first();
        $this->assertStringNotContainsString('..', $file->path);
        $this->actingAs($this->user('scm.request'))->get(route('scm.download', $file))->assertForbidden();
        $this->actingAs($this->requester)->get(route('scm.download', $file))->assertOk();
        $file->update(['path' => '../../.env']);
        $this->get(route('scm.download', $file))->assertNotFound();
    }

    public function test_requester_sees_final_procurement_results_without_scm_editing_state(): void
    {
        $r = $this->finalized();
        $this->requester->directPermissions()->attach(Permission::where('code', 'scm.process')->firstOrFail());
        $this->requester->unsetRelation('directPermissions');
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('open', $r->id)
            ->assertSet('cbeForm', [])->assertSet('legal', [])->assertSee($r->number)
            ->assertSee('Hasil pengadaan')->assertSee('Penawaran harga vendor terpilih')->assertDontSee('Simpan draft CBE');
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'queue')->assertSee($r->number)
            ->call('open', $r->id)->call('showTab', 'cbe')->assertSee('CBE final terkunci');
        $page->call('switchSection', 'vendors')->assertSee('Versi dokumen vendor');
        Livewire::actingAs($this->user('scm.configure', true))->test(ScmProcurement::class)->call('switchSection', 'settings')->assertSee('Pengaturan master SCM');
    }

    #[DataProvider('kinds')]
    public function test_only_the_requester_and_authorized_scm_can_read_final_cbe_and_selected_quotation(string $kind): void
    {
        $request = $this->quoted($kind);
        $oldQuote = Quotation::whereHas('candidate', fn ($q) => $q->where('request_id', $request->id))->firstOrFail();
        $request = $this->flow->attach($this->scm, $request->id, $request->version, 'quotation', $oldQuote->id, $this->pdf('penawaran-versi-lama.pdf'));
        $request = $this->flow->saveQuotation($this->scm, $request->id, $request->version, $this->quoteData($request, $oldQuote->candidate_id, '120'));
        $selected = Quotation::where('candidate_id', $oldQuote->candidate_id)->latest('version')->firstOrFail();
        $loser = Quotation::where('candidate_id', '!=', $oldQuote->candidate_id)->firstOrFail();
        foreach ([$selected->id => 'penawaran-terpilih.pdf', $loser->id => 'penawaran-vendor-lain.pdf'] as $quoteId => $filename) {
            $request = $this->flow->attach($this->scm, $request->id, $request->version, 'quotation', $quoteId, $this->pdf($filename));
        }
        $data = $this->cbeData($request);
        $data['quotation_ids'] = array_values(array_diff($data['quotation_ids'], [$oldQuote->id]));
        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data);
        $request = $this->flow->attach($this->scm, $request->id, $request->version, 'cbe', $request->cbe->id, $this->pdf('lampiran-cbe.pdf'));
        $cbeFile = Attachment::where('cbe_id', $request->cbe->id)->sole();
        $selectedFile = $selected->attachments()->sole();
        $this->actingAs($this->requester)->get(route('scm.download', $selectedFile))->assertForbidden();
        $this->get(route('scm.download', $cbeFile))->assertForbidden();
        $this->get(route('scm.print', $request->cbe))->assertForbidden();
        $this->get(route('scm.selected-quotation', $request->cbe))->assertForbidden();
        $pageClass = $kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class;
        Livewire::actingAs($this->requester)->test($pageClass)->call('open', $request->id)->assertDontSee('Hasil pengadaan');

        $request = $this->flow->saveCbe($this->scm, $request->id, $request->version, $data, true);
        $document = $this->flow->saveDocument($this->scm, ['vendor_id' => $request->cbe->selected_vendor_id,
            'document_type_id' => DocumentType::where('code', 'KTP')->sole()->id, 'number' => '0000123', 'title' => 'KTP tetap privat'], $this->pdf('legalitas-privat.pdf'));
        foreach ([$this->requester, $this->scm] as $actor) {
            $this->actingAs($actor)->get(route('scm.download', $selectedFile))->assertOk();
            $this->get(route('scm.download', $cbeFile))->assertOk();
            $this->get(route('scm.print', $request->cbe))->assertOk()->assertSee($request->number);
            $this->get(route('scm.selected-quotation', $request->cbe))->assertOk()->assertSee($selected->number)
                ->assertSee($request->number)->assertDontSee($loser->number)->assertDontSee('KTP tetap privat');
        }
        Livewire::actingAs($this->requester)->test($pageClass)->call('open', $request->id)
            ->assertSee('penawaran-terpilih.pdf')->assertSee('lampiran-cbe.pdf')
            ->assertDontSee('penawaran-versi-lama.pdf')->assertDontSee('penawaran-vendor-lain.pdf')->assertDontSee('legalitas-privat.pdf')
            ->assertSet('cbeForm', [])->assertSet('legal', [])->call('showTab', 'legal')->assertForbidden();
        $this->actingAs($this->requester)->get(route('scm.download', $oldQuote->attachments()->sole()))->assertForbidden();
        $this->get(route('scm.download', $loser->attachments()->sole()))->assertForbidden();
        $this->get(route('scm.download', $document->attachments()->sole()))->assertForbidden();
        $other = $this->user('scm.request');
        $scmOutsideDepartment = $this->user('scm.process');
        $scmOutsideDepartment->employee->update(['department_id' => Department::create(['code' => 'OUTSIDE', 'name' => 'Departemen lain', 'is_active' => true])->id]);
        foreach ([$other, $scmOutsideDepartment] as $actor) {
            $this->actingAs($actor)->get(route('scm.print', $request->cbe))->assertForbidden();
            $this->get(route('scm.selected-quotation', $request->cbe))->assertForbidden();
            $this->get(route('scm.download', $selectedFile))->assertForbidden();
            $this->get(route('scm.download', $cbeFile))->assertForbidden();
        }
        $this->requester->employee->update(['is_active' => false]);
        $this->actingAs($this->requester)->get(route('scm.selected-quotation', $request->cbe))->assertRedirect(route('login'));
    }

    public function test_draft_cbe_and_revisions_keep_old_quotation_and_final_cbe_locked(): void
    {
        $r = $this->sourcing();
        $r = $this->flow->saveCbe($this->scm, $r->id, $r->version, ['date' => today()->toDateString(), 'quotation_ids' => []]);
        $this->assertSame('draft', $r->cbe->status);
        $r = $this->vendors($r);
        foreach ($r->candidates as $candidate) {
            $r = $this->flow->saveQuotation($this->scm, $r->id, $r->version, $this->quoteData($r, $candidate->id));
        }
        $old = Quotation::firstOrFail();
        $r = $this->flow->saveQuotation($this->scm, $r->id, $r->version, $this->quoteData($r, $old->candidate_id, '90,01'));
        $this->assertSame(1, $old->fresh()->version);
        $this->assertSame(2, Quotation::where('candidate_id', $old->candidate_id)->max('version'));
        $data = $this->cbeData($r);
        $data['quotation_ids'] = Quotation::where('id', '!=', $old->id)->pluck('id')->all();
        $r = $this->flow->saveCbe($this->scm, $r->id, $r->version, $data, true);
        $this->rejects(fn () => $this->flow->saveCbe($this->scm, $r->id, $r->version, $data, true));
        $this->rejects(fn () => $this->flow->saveQuotation($this->scm, $r->id, $r->version, $this->quoteData($r, $old->candidate_id)));
        $this->expectException(\LogicException::class);
        $r->cbe->update(['selection_reason' => 'Perubahan sesudah final']);
    }

    public function test_document_reuse_cross_vendor_rejection_and_missing_file(): void
    {
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        Requirement::create(['category_id' => $this->category->id, 'document_type_id' => $type->id, 'is_required' => true]);
        $r = $this->finalized();
        $r = $this->flow->startLegal($this->scm, $r->id, $r->version);
        $winner = $r->legalCase->vendor_id;
        $wrong = $this->flow->saveDocument($this->scm, ['vendor_id' => Vendor::where('id', '!=', $winner)->first()->id, 'document_type_id' => $type->id, 'number' => '0001', 'title' => 'NIB vendor lain'], $this->pdf());
        $item = $r->legalCase->items->first();
        $this->rejects(fn () => $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $wrong->id]));
        $doc = $this->flow->saveDocument($this->scm, ['vendor_id' => $winner, 'document_type_id' => $type->id, 'number' => '0002', 'title' => 'NIB digunakan ulang'], $this->pdf());
        $r = $this->flow->updateLegal($this->scm, $r->id, $r->version, $item->id, ['status' => 'diterima', 'vendor_document_id' => $doc->id]);
        $r = $this->flow->complete($this->scm, $r->id, $r->version);
        $second = $this->sourcing('SR');
        foreach (Vendor::all() as $vendor) {
            $second = $this->flow->addCandidate($this->scm, $second->id, $second->version, ['vendor_id' => $vendor->id, 'followup_status' => 'diminta']);
        }
        foreach ($second->candidates as $candidate) {
            $second = $this->flow->saveQuotation($this->scm, $second->id, $second->version, $this->quoteData($second, $candidate->id));
        }
        $data = $this->cbeData($second);
        $data['selected_vendor_id'] = $winner;
        $second = $this->flow->saveCbe($this->scm, $second->id, $second->version, $data, true);
        $second = $this->flow->startLegal($this->scm, $second->id, $second->version);
        $second = $this->flow->updateLegal($this->scm, $second->id, $second->version, $second->legalCase->items->first()->id, ['status' => 'diterima', 'vendor_document_id' => $doc->id]);
        Storage::disk('scm')->delete($doc->attachments->first()->path);
        $this->rejects(fn () => $this->flow->complete($this->scm, $second->id, $second->version));
        $this->assertSame($doc->id, $r->legalCase->items->first()->vendor_document_id);
        $this->assertSame(2, VendorDocument::count());
    }

    public function test_upload_extensions_size_and_download_authentication(): void
    {
        $r = $this->draft();
        $this->rejects(fn () => $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, $this->pdf('file.php')));
        config(['scm.upload_max_kb' => 1]);
        $this->rejects(fn () => $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, UploadedFile::fake()->createWithContent('large.pdf', "%PDF-1.4\n".str_repeat('a', 2048))));
        config(['scm.upload_max_kb' => 10240]);
        $r = $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, $this->pdf());
        $this->get(route('scm.download', $r->attachments->first()))->assertRedirect(route('login'));
        $r = app(Signatures::class)->sign($this->requester, $r->id, $r->version);
        $r = $this->submitApproved($r);
        $this->rejects(fn () => $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, $this->pdf()));
    }

    public function test_revoked_request_permission_also_revokes_own_downloads(): void
    {
        $r = $this->draft();
        $r = $this->flow->attach($this->requester, $r->id, $r->version, 'request', $r->id, $this->pdf());
        $this->requester->directPermissions()->detach();
        $actor = $this->requester->fresh();
        $this->assertFalse(Access::views($actor, $r));
        $this->assertSame(0, Access::scope(Procurement::query(), $actor)->count());
        $this->actingAs($actor)->get(route('scm.download', $r->attachments->first()))->assertForbidden();
    }

    public function test_settings_permission_is_separate_and_snapshot_edits_need_reason(): void
    {
        $configurator = $this->user('scm.configure', true);
        Livewire::actingAs($configurator)->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->set('master.name', 'Kategori baru fiktif')->call('saveMaster')->assertHasNoErrors();
        $this->assertDatabaseHas('scm_categories', ['name' => 'Kategori baru fiktif']);
        $this->assertFalse(Access::manages($configurator));
        $r = $this->finalized();
        $r = $this->flow->startLegal($this->scm, $r->id, $r->version);
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        $this->rejects(fn () => $this->flow->changeRequirement($this->scm, $r->id, $r->version, $type->id, true, ''));
        $r = $this->flow->changeRequirement($this->scm, $r->id, $r->version, $type->id, true, 'Dokumen diwajibkan untuk kategori pengadaan ini.');
        $this->assertTrue($r->legalCase->items->first()->is_required);
        $this->assertDatabaseHas('scm_audits', ['request_id' => $r->id, 'action' => 'ubah_persyaratan', 'actor_id' => $this->scm->id]);
    }

    public function test_attachment_form_stays_in_context_after_saving_cbe_and_switching_tabs(): void
    {
        $request = $this->quoted();
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $request->id)->call('showTab', 'cbe')
            ->set('cbeForm', $this->cbeData($request))->call('saveCbe')->assertHasNoErrors()
            ->assertSet('attachmentTarget', 'cbe')->assertSee('Tambahkan lampiran CBE');
        $cbe = $request->fresh()->cbe;
        $page->assertSet('attachmentTargetId', $cbe->id)
            ->set('attachmentFile', $this->pdf())->call('uploadAttachment')->assertHasNoErrors();
        $this->assertDatabaseHas('scm_attachments', ['cbe_id' => $cbe->id, 'quotation_id' => null]);
        $page->call('showTab', 'quotes')->assertSet('attachmentTarget', 'quotation')
            ->assertSet('attachmentTargetId', 0)->assertSee('Tambahkan lampiran quotation')
            ->call('showTab', 'history')->assertDontSee('Tambahkan lampiran')
            ->call('showTab', 'legal')->assertDontSee('Tambahkan lampiran')
            ->call('showTab', 'request')->assertDontSee('Tambahkan lampiran');
    }

    public function test_switching_vendor_clears_document_and_invitation_context(): void
    {
        $request = $this->vendors($this->sourcing());
        $vendors = $request->candidates->pluck('vendor');
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        foreach ($vendors->take(2) as $index => $vendor) {
            $this->flow->saveDocument($this->scm, ['vendor_id' => $vendor->id, 'document_type_id' => $type->id,
                'number' => 'NIB-'.$index, 'title' => 'Dokumen khusus vendor '.$index], $this->pdf());
        }
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'vendors')
            ->call('editVendor', $vendors[0]->id)->assertSee('Dokumen khusus vendor 0')->assertDontSee('Dokumen khusus vendor 1')
            ->set('document.number', 'JANGAN-TERBAWA')->set('documentFile', $this->pdf())
            ->set('portalInvitation.is_construction', true)->set('portalInvitation.amount', '200000000')
            ->call('editVendor', $vendors[1]->id)->assertSet('document.vendor_id', $vendors[1]->id)
            ->assertSet('document.number', '')->assertSet('documentFile', null)
            ->assertSet('portalInvitation.is_construction', false)->assertSet('portalInvitation.amount', '')
            ->assertSee('Dokumen khusus vendor 1')->assertDontSee('Dokumen khusus vendor 0')
            ->call('newVendor')->assertSet('document.vendor_id', '')->assertSet('documentVendorFilter', '');
    }

    public function test_master_kind_change_and_cancel_clear_previous_record_values(): void
    {
        $configurator = $this->user('scm.configure');
        $type = DocumentType::where('code', 'NIB')->firstOrFail();
        Livewire::actingAs($configurator)->test(ScmProcurement::class)
            ->call('editMaster', 'type', $type->id)->assertSet('master.code', 'NIB')
            ->set('master.kind', 'category')->assertSet('master.id', '')->assertSet('master.code', '')->assertSet('master.name', '')
            ->set('master.name', 'Kategori baru dari pemeriksaan')->call('saveMaster')->assertHasNoErrors()
            ->assertSet('master.name', '')->assertSet('master.id', '')
            ->call('editMaster', 'category', $this->category->id)->call('newMaster', 'category')
            ->assertSet('master.id', '')->assertSet('master.name', '');
        $this->assertDatabaseHas('scm_categories', ['name' => 'Kategori baru dari pemeriksaan']);
        $this->assertDatabaseHas('scm_document_types', ['id' => $type->id, 'code' => 'NIB']);
    }
}
