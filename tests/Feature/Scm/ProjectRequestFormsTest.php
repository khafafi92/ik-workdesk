<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ServiceRequests;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Models\WorkProject;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectRequestFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $this->department = Department::create(['code' => 'PROJECT-FORM', 'name' => 'Divisi Project Form']);
        $this->staff = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $this->staff->id, 'name' => $this->staff->name, 'department_id' => $this->department->id, 'is_active' => true]);
        $this->staff->directPermissions()->attach(Permission::where('code', 'scm.request')->firstOrFail());
        $this->actingAs($this->staff);
    }

    private function data(string $kind = 'PR', array $extra = []): array
    {
        return array_replace(['company' => 'KPMOG', 'kind' => $kind, 'need_type' => 'PROJECT', 'date' => today()->toDateString(),
            'department_id' => $this->department->id, 'items' => [['name' => 'Valve', 'size' => '2 inch', 'model_part_number' => 'PN-TEST',
                'specification' => 'Spesifikasi pengujian', 'quantity' => '2,5', 'unit' => 'unit']]], $extra);
    }

    private function renderSheet(Procurement $request): string
    {
        return view('filament.pages.scm.partials.request-sheet', ['record' => $request->load(['items', 'requester', 'department', 'approvals.user']), 'sheetEditing' => false])->render();
    }

    public static function requestPages(): array
    {
        return [[PurchaseRequests::class, 'PR'], [ServiceRequests::class, 'SR']];
    }

    #[DataProvider('requestPages')]
    public function test_new_request_selects_ho_or_project_before_company_and_project_name_can_wait(string $page, string $kind): void
    {
        Livewire::test($page)->call('chooseCompany')->assertSet('newRequestNeedType', '')
            ->assertSee('Jenis kebutuhan')->set('newRequestNeedType', 'PROJECT')->call('newDraft', 'KPMOG')
            ->assertSet('draft.need_type', 'PROJECT')->assertSee('Nama Project (opsional)')
            ->assertSee('PR/SR Project tetap dapat dibuat')->assertSee($kind === 'PR' ? 'Model/PN' : 'SERVICE REQUEST AND MATERIALS');
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data($kind));
        $this->assertNull($request->work_project_id);
        $this->assertSame('PROJECT', $request->need_type);
    }

    public function test_project_pr_uses_size_model_delivery_point_dates_and_revision_without_ho_brand_columns(): void
    {
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data(extra: ['delivery_point' => 'Lokasi project uji']));
        $html = $this->renderSheet($request);
        foreach (['Size', 'Model/PN', '2 inch', 'PN-TEST', 'Delivery Point', 'Lokasi project uji', 'Date:', 'KPM_OG-FRM-003,23 Apr 2024, Rev.3'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('>Brand<', $html);
        $this->assertStringNotContainsString('SERVICE REQUEST AND MATERIALS', $html);
        Livewire::test(PurchaseRequests::class)->call('open', $request->id)->call('editDraft')
            ->assertSet('draft.items.0.size', '2 inch')->assertSet('draft.items.0.model_part_number', 'PN-TEST')
            ->assertSet('draft.delivery_point', 'Lokasi project uji')->call('saveDraft')->assertHasNoErrors();
    }

    public function test_project_sr_saves_description_materials_and_vendor_instructions_and_edits_them(): void
    {
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data('SR', [
            'instruction_to_vendor' => 'Koordinasi sebelum datang', 'procedure_required' => "Prosedur pengujian\nCatat hasil",
            'items' => [['specification' => 'Perbaikan pompa', 'materials_needed' => "Seal\nPelumas", 'quantity' => '1', 'unit' => 'job']],
        ]));
        $this->assertSame('Perbaikan pompa', $request->items->sole()->name);
        $this->assertSame("Seal\nPelumas", $request->items->sole()->materials_needed);
        $html = $this->renderSheet($request);
        foreach (['SERVICE REQUEST AND MATERIALS', 'Materials Needed', 'Perbaikan pompa', 'Instruction to vendor', 'Koordinasi sebelum datang', 'Procedur required', 'Prosedur pengujian', 'KPM-FRM-046, 23 April 2024, Rev.2'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertLessThan(strpos($html, 'scm-project-sr-table'), strpos($html, 'scm-sheet-signature'));
        $this->assertStringNotContainsString('Date of request received:', $html);
        Livewire::test(ServiceRequests::class)->call('open', $request->id)->call('editDraft')
            ->assertSet('draft.items.0.materials_needed', "Seal\nPelumas")->assertSet('draft.instruction_to_vendor', 'Koordinasi sebelum datang')
            ->set('draft.procedure_required', 'Prosedur revisi')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('Prosedur revisi', $request->fresh()->procedure_required);
    }

    public function test_service_without_materials_keeps_materials_cell_empty_after_save_and_reopen(): void
    {
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data('SR', [
            'items' => [['specification' => 'Jasa inspeksi', 'quantity' => '1', 'unit' => 'job', 'materials_needed' => '']],
        ]));
        $this->assertSame('', $request->items->sole()->materials_needed);
        Livewire::test(ServiceRequests::class)->call('open', $request->id)->call('editDraft')->assertSet('draft.items.0.materials_needed', '');
        $doc = new \DOMDocument;
        @$doc->loadHTML($this->renderSheet($request));
        $xpath = new \DOMXPath($doc);
        $cell = $xpath->query('//td[contains(@class,"scm-project-materials")]')->item(0);
        $this->assertSame('Materials Needed', trim($cell->textContent));
    }

    public function test_existing_service_material_name_is_visible_when_new_materials_field_was_never_set(): void
    {
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data('SR'));
        $request->items()->update(['name' => 'Material dari draft lama', 'materials_needed' => null]);
        $this->assertStringContainsString('Material dari draft lama', $this->renderSheet($request->fresh()));
        Livewire::test(ServiceRequests::class)->call('open', $request->id)->call('editDraft')->assertSet('draft.items.0.materials_needed', 'Material dari draft lama');
    }

    public function test_optional_project_uses_existing_master_and_does_not_change_approval_scope(): void
    {
        $project = WorkProject::create(['code' => 'QA-1', 'name' => 'Project uji', 'department_id' => $this->department->id, 'status' => 'active']);
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data(extra: ['work_project_id' => $project->id]));
        $this->assertSame($project->id, $request->project->id);
        $this->assertSame('HO', $request->approval_scope);
        Livewire::test(PurchaseRequests::class)->call('open', $request->id)->assertSee('Nama Project: QA-1 - Project uji')
            ->call('editDraft')->assertSet('draft.work_project_id', $project->id);
        $ho = app(Workflow::class)->saveDraft($this->staff, $this->data(extra: ['need_type' => 'HO', 'work_project_id' => $project->id]));
        $this->assertNull($ho->work_project_id);
        $html = $this->renderSheet($ho);
        $this->assertStringContainsString('>Brand<', $html);
        $this->assertStringNotContainsString('scm-project-sheet', $html);
        $this->assertStringNotContainsString('KPM_OG-FRM-003', $html);
    }

    public function test_project_outside_department_access_cannot_be_assigned(): void
    {
        $other = Department::create(['code' => 'OTHER', 'name' => 'Divisi lain']);
        $project = WorkProject::create(['code' => 'QA-OTHER', 'name' => 'Tidak boleh diakses', 'department_id' => $other->id, 'status' => 'active']);
        $this->expectException(ValidationException::class);
        app(Workflow::class)->saveDraft($this->staff, $this->data(extra: ['work_project_id' => $project->id]));
    }

    public function test_inactive_project_cannot_be_selected_and_other_division_names_are_not_offered(): void
    {
        $project = WorkProject::create(['code' => 'QA-CLOSED', 'name' => 'Project selesai', 'status' => 'completed']);
        Livewire::test(PurchaseRequests::class)->set('newRequestNeedType', 'PROJECT')->call('newDraft', 'KPMOG')->assertDontSee('Project selesai');
        $this->expectException(ValidationException::class);
        app(Workflow::class)->saveDraft($this->staff, $this->data(extra: ['work_project_id' => $project->id]));
    }
}
