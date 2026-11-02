<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmRequestNumbers;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use App\Services\Scm\ProjectNumberingMaster;
use App\Services\Scm\RequestNumbering;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RequestNumberingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Department $department;

    private ProjectNumbering $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 2));
        $this->department = Department::create(['code' => 'NUM', 'name' => 'Nomor uji']);
        $this->staff = $this->user('scm.request');
        $this->master = ProjectNumbering::where('code', 'COMP-19E001')->sole();
        $this->actingAs($this->staff);
    }

    private function user(string $permission): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'name' => $user->name, 'department_id' => $this->department->id, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::where('code', $permission)->sole()->id);

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['company' => 'KPMOG', 'kind' => 'PR', 'need_type' => 'PROJECT', 'project_numbering_id' => $this->master->id,
            'date' => today()->toDateString(), 'department_id' => $this->department->id, 'title' => 'Penggantian valve LTRO', 'reason' => 'Valve bocor',
            'items' => [['name' => 'Valve', 'specification' => '2 inch', 'quantity' => '1', 'unit' => 'unit']]], $extra);
    }

    public function test_project_counters_are_separate_from_ho_kind_project_and_year_and_continue_across_months(): void
    {
        $numbers = app(RequestNumbering::class);
        $this->assertSame('001/KPMOG-OFC/PR/XI/2026', $numbers->reserve($this->staff, 'PR', 'KPMOG')->number);
        $this->assertSame('001/COMP-19E001/PR/XI/2026', $numbers->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $this->master->id)->number);
        $this->assertSame('002/COMP-19E001/PR/XI/2026', $numbers->reserve($this->staff, 'PR', 'APCA', 'PROJECT', $this->master->id)->number);
        $this->assertSame('001/COMP-19E001/SR/XI/2026', $numbers->reserve($this->staff, 'SR', 'KPMOG', 'PROJECT', $this->master->id)->number);
        $other = ProjectNumbering::create(['code' => 'OTHER-1', 'name' => 'Project lain']);
        $this->assertSame('001/OTHER-1/PR/XI/2026', $numbers->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $other->id)->number);
        $this->assertSame('002/KPMOG-OFC/PR/XI/2026', $numbers->reserve($this->staff, 'PR', 'KPMOG')->number);
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->assertSame('003/COMP-19E001/PR/XII/2026', $numbers->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $this->master->id)->number);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('001/COMP-19E001/PR/I/2027', $numbers->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $this->master->id)->number);
    }

    public function test_project_requires_an_active_master_and_never_falls_back_to_ho(): void
    {
        Livewire::test(PurchaseRequests::class)->set('newRequestNeedType', 'PROJECT')->call('newDraft', 'KPMOG')->assertHasErrors('project_numbering_id');
        $this->master->update(['is_active' => false]);
        Livewire::test(PurchaseRequests::class)->set('newRequestNeedType', 'PROJECT')->set('newProjectNumberingId', (string) $this->master->id)
            ->call('newDraft', 'KPMOG')->assertHasErrors('project_numbering_id');
        $this->assertSame(0, RequestNumber::count());
        $this->assertSame(0, DB::table('scm_number_sequences')->count());
    }

    public function test_number_is_kept_on_save_and_edit_and_can_only_be_consumed_once(): void
    {
        $number = app(RequestNumbering::class)->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $this->master->id);
        $flow = app(Workflow::class);
        $request = $flow->saveDraft($this->staff, $this->data(), numberId: $number->id);
        $request = $flow->saveDraft($this->staff, $this->data(['reason' => 'Keterangan terbaru']), $request->id, $request->version);
        $this->assertSame($number->number, $request->number);
        $this->assertSame($request->id, $number->fresh()->request_id);
        $this->assertSame(1, DB::table('scm_project_number_sequences')->value('last_number'));
        $this->expectException(ValidationException::class);
        $flow->saveDraft($this->staff, $this->data(), numberId: $number->id);
    }

    public function test_reserved_ho_number_cannot_be_consumed_for_project(): void
    {
        $number = app(RequestNumbering::class)->reserve($this->staff, 'PR', 'KPMOG');
        $this->expectException(ValidationException::class);
        app(Workflow::class)->saveDraft($this->staff, $this->data(), numberId: $number->id);
    }

    public function test_failed_draft_rolls_back_project_counter_and_reservation(): void
    {
        try {
            app(Workflow::class)->saveDraft($this->staff, $this->data(['items' => [['name' => 'Valve', 'specification' => '2 inch', 'quantity' => '0', 'unit' => 'unit']]]));
            $this->fail('Zero quantity must fail.');
        } catch (ValidationException) {
            $this->assertSame(0, RequestNumber::count());
            $this->assertSame(0, DB::table('scm_project_number_sequences')->count());
        }
        $this->assertSame('001/COMP-19E001/PR/XI/2026', app(Workflow::class)->saveDraft($this->staff, $this->data())->number);
    }

    public function test_history_shows_authorized_descriptions_and_filters_reserved_numbers(): void
    {
        $request = app(Workflow::class)->saveDraft($this->staff, $this->data());
        $other = $this->user('scm.request');
        app(Workflow::class)->saveDraft($other, $this->data(['title' => 'Permintaan rahasia pengguna lain']));
        app(RequestNumbering::class)->reserve($this->staff, 'SR', 'KPMOG');
        Livewire::test(ScmRequestNumbers::class)->assertSee($request->number)->assertSee('Valve bocor')->assertDontSee('Permintaan rahasia pengguna lain')
            ->assertDontSee('Master kode Project')->set('usage', 'reserved')->assertSee('001/KPMOG-OFC/SR/XI/2026')->assertDontSee('Valve bocor')
            ->call('clearFilters')->set('search', 'Valve bocor')->assertSee($request->number)->set('needType', 'HO')->assertDontSee($request->number);
        $processor = $this->user('scm.process');
        Livewire::actingAs($processor)->test(ScmRequestNumbers::class)->assertDontSee('Valve bocor');
        $request->update(['status' => 'sourcing']);
        Livewire::actingAs($processor)->test(ScmRequestNumbers::class)->assertSee('Valve bocor')->assertDontSee('Permintaan rahasia pengguna lain');
        $outside = Department::create(['code' => 'NO-NUM', 'name' => 'Divisi lain']);
        $request->update(['department_id' => $outside->id]);
        Livewire::actingAs($processor)->test(ScmRequestNumbers::class)->assertDontSee('Valve bocor');
    }

    public function test_master_is_restricted_and_issued_codes_cannot_be_changed(): void
    {
        Livewire::test(ScmRequestNumbers::class)->call('editMaster', $this->master->id)->assertForbidden();
        Livewire::test(ScmRequestNumbers::class)->set('master.code', 'UNAUTHORIZED')->set('master.name', 'Uji')->call('saveMaster')->assertForbidden();
        $configurator = $this->user('scm.configure');
        $page = Livewire::actingAs($configurator)->test(ScmRequestNumbers::class)->assertSee('Master kode Project')
            ->set('master.format', 'legacy')->set('master.code', 'new-1')->set('master.name', 'Project baru')->call('saveMaster')->assertHasNoErrors()->assertSee('NEW-1');
        app(RequestNumbering::class)->reserve($this->staff, 'PR', 'KPMOG', 'PROJECT', $this->master->id);
        $page->call('editMaster', $this->master->id)->set('master.code', 'REPLACED')->call('saveMaster')->assertHasErrors('code')
            ->set('master.code', 'COMP-19E001')->set('master.is_active', false)->call('saveMaster')->assertHasNoErrors();
        $this->assertFalse($this->master->fresh()->is_active);
    }

    public function test_migration_registers_legacy_project_numbers_without_renumbering_them(): void
    {
        $migration = require database_path('migrations/2026_11_02_100000_add_scm_project_numbering.php');
        $migration->down();
        $request = Procurement::create(['number' => '043/KPMOG-OFC/PR/X/2026', 'kind' => 'PR', 'company' => 'KPMOG', 'need_type' => 'PROJECT',
            'date' => today()->toDateString(), 'department_id' => $this->department->id, 'requester_id' => $this->staff->id,
            'title' => 'Valve lama', 'reason' => 'Valve bocor', 'status' => 'draft']);
        $before = $request->fresh()->only(['number', 'status', 'version', 'updated_at']);
        $migration->up();
        $this->assertEquals($before, $request->fresh()->only(array_keys($before)));
        $this->assertSame('PROJECT', RequestNumber::where('request_id', $request->id)->sole()->need_type);
        Livewire::test(ScmRequestNumbers::class)->assertSee($request->number)->assertSee('Valve bocor');
    }

    public function test_master_rejects_ho_prefix_and_stale_edits(): void
    {
        $actor = $this->user('scm.configure');
        $page = Livewire::actingAs($actor)->test(ScmRequestNumbers::class)->set('master.format', 'legacy')->set('master.code', 'APCA-HO')->set('master.name', 'Uji')
            ->call('saveMaster')->assertHasErrors('code')->call('editMaster', $this->master->id);
        app(ProjectNumberingMaster::class)->save($actor, [...$this->master->only(['code', 'name', 'is_active', 'version']), 'name' => 'LTRO revisi'], $this->master->id);
        $page->set('master.name', 'Edit kedaluwarsa')->call('saveMaster')->assertHasErrors('scm');
        $this->assertSame('LTRO revisi', $this->master->fresh()->name);
    }
}
