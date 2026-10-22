<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ServiceRequests;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Audit;
use App\Models\Scm\Category;
use App\Models\Scm\DocumentType;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
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
        return $this->flow->saveDraft($this->requester, ['kind' => $kind, 'date' => today()->toDateString(),
            'department_id' => $this->department->id,
            'title' => 'Pengadaan uji '.$kind, 'reason' => 'Pengujian kebutuhan operasional', 'items' => [
                ['name' => 'Item satu', 'specification' => 'Spesifikasi satu', 'quantity' => '1,5', 'unit' => 'unit'],
                ['name' => 'Item dua', 'specification' => 'Spesifikasi dua', 'quantity' => '2', 'unit' => 'paket'],
            ]]);
    }

    private function sourcing(string $kind = 'PR'): Procurement
    {
        $r = $this->draft($kind);
        $r = $this->flow->submit($this->requester, $r->id, $r->version);

        return $this->flow->receive($this->scm, $r->id, $r->version, $this->category->id);
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
    public function test_livewire_empty_optional_fields_are_stored_as_null(string $kind): void
    {
        $this->actingAs($this->requester);
        Livewire::test($kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class)
            ->call('newDraft')
            ->set('draft.title', 'Draft dengan tanggal opsional kosong')
            ->set('draft.reason', 'Regresi formulir browser SCM')
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
        $this->assertEquals($this->category->id, $request->fresh()->category_id);
    }

    public function test_request_menus_separate_kinds_and_owners_from_the_scm_queue(): void
    {
        $pr = $this->draft('PR');
        $sr = $this->draft('SR');
        $other = $this->user('scm.request');
        $foreign = $this->flow->saveDraft($other, ['kind' => 'PR', 'date' => today()->toDateString(),
            'department_id' => $this->department->id, 'title' => 'Permintaan user lain', 'reason' => 'Kebutuhan user lain',
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

    public function test_scm_assigns_an_active_category_only_when_receiving_a_submitted_request(): void
    {
        $request = $this->draft();
        $this->assertNull($request->category_id);
        $request = $this->flow->submit($this->requester, $request->id, $request->version);
        $this->rejects(fn () => $this->flow->receive($this->scm, $request->id, $request->version));
        $this->category->update(['is_active' => false]);
        $this->rejects(fn () => $this->flow->receive($this->scm, $request->id, $request->version, $this->category->id));
        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertNull($request->fresh()->category_id);
        $this->category->update(['is_active' => true]);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('open', $request->id)
            ->set('receivingCategoryId', (string) $this->category->id)->call('receiveRequest')->assertHasNoErrors();
        $this->assertSame('sourcing', $request->fresh()->status);
        $this->assertEquals($this->category->id, $request->fresh()->category_id);
        $audit = Audit::where('request_id', $request->id)->where('action', 'perubahan_proses')->latest('id')->firstOrFail();
        $this->assertNull($audit->changes['before']['category_id']);
        $this->assertEquals($this->category->id, $audit->changes['after']['category_id']);
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
        $this->get(route('scm.print', $r->cbe))->assertForbidden();
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

    public function test_ownership_explicit_permissions_and_department_scope_are_enforced(): void
    {
        $r = $this->draft();
        $other = $this->user('scm.request');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertFalse(Access::manages($admin));
        $this->assertFalse(Access::views($other, $r));
        $this->actingAs($other)->get('/panel/scm/pengadaan?permintaan='.$r->id)->assertForbidden();
        $this->actingAs($admin)->get('/panel/scm/pengadaan')->assertForbidden();
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
        $this->scm->employee->update(['department_id' => Department::create(['code' => 'OTHER', 'name' => 'Lain', 'is_active' => true])->id]);
        $this->scm->unsetRelation('employee');
        $this->assertFalse(Access::manages($this->scm, $r));
    }

    public function test_illegal_transitions_duplicate_vendors_and_stale_versions_are_rejected(): void
    {
        $r = $this->draft();
        $this->rejects(fn () => $this->flow->submit($this->requester, $r->id, $r->version + 1));
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
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

    public function test_livewire_pages_and_requester_payload_hide_commercial_data(): void
    {
        $r = $this->finalized();
        $this->requester->directPermissions()->attach(Permission::where('code', 'scm.process')->firstOrFail());
        $this->requester->unsetRelation('directPermissions');
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->call('open', $r->id)
            ->assertSet('cbeForm', [])->assertSet('legal', [])->assertSee($r->number)->assertDontSee('Harga akhir:');
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'queue')->assertSee($r->number)
            ->call('open', $r->id)->call('showTab', 'cbe')->assertSee('CBE final terkunci');
        $page->call('switchSection', 'vendors')->assertSee('Versi dokumen vendor');
        Livewire::actingAs($this->user('scm.configure', true))->test(ScmProcurement::class)->call('switchSection', 'settings')->assertSee('Pengaturan master SCM');
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
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
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
}
