<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmRequestNumbers;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\ProjectNumbering;
use App\Models\User;
use App\Services\Scm\RequestNumbering;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCodeMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $department = Department::create(['code' => 'CODE-TEST', 'name' => 'Project code test']);
        $this->actor = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $this->actor->id, 'name' => $this->actor->name, 'department_id' => $department->id, 'is_active' => true]);
        $this->actor->directPermissions()->attach(Permission::whereIn('code', ['scm.request', 'scm.configure'])->pluck('id'));
        $this->actingAs($this->actor);
    }

    public function test_standard_master_formats_previews_saves_and_reopens_the_components(): void
    {
        $page = Livewire::test(ScmRequestNumbers::class)->assertSet('master.format', 'standard')
            ->assertSee('Lengkapi komponen kode Project.')->set('master.project_prefix', 'ubad')->set('master.awarded_year', 2023)
            ->set('master.work_code', 'SPC')->set('master.running_number', '001')->assertSee('UBAD/23/SPC/001')
            ->set('master.code', 'IGNORED-CLIENT-CODE')->set('master.name', 'Ubadari')->call('saveMaster')->assertHasNoErrors();
        $master = ProjectNumbering::where('code', 'UBAD/23/SPC/001')->sole();
        $page->call('editMaster', $master->id)->assertSet('master.format', 'standard')->assertSet('master.project_prefix', 'UBAD')
            ->assertSet('master.awarded_year', 2023)->assertSet('master.work_code', 'SPC')->assertSet('master.running_number', '001')
            ->set('master.name', 'Ubadari revisi')->call('saveMaster')->assertHasNoErrors();
        $this->assertSame('Ubadari revisi', $master->fresh()->name);
        foreach (['PR', 'SR'] as $kind) {
            $number = app(RequestNumbering::class)->reserve($this->actor, $kind, 'KPMOG', 'PROJECT', $master->id);
            $this->assertStringStartsWith('001/UBAD/23/SPC/001/'.$kind.'/', $number->number);
        }
        $request = app(Workflow::class)->saveDraft($this->actor, ['kind' => 'PR', 'company' => 'KPMOG', 'need_type' => 'PROJECT',
            'project_numbering_id' => $master->id, 'department_id' => $this->actor->employee->department_id, 'date' => today()->toDateString(),
            'items' => [['name' => 'Valve', 'specification' => 'Pengujian kode Project', 'quantity' => '1', 'unit' => 'unit']]]);
        $this->assertStringStartsWith('002/UBAD/23/SPC/001/PR/', $request->number);
        $this->assertSame($master->id, $request->project_numbering_id);
        $page->call('editMaster', $master->id)->set('master.running_number', 2)->call('saveMaster')->assertHasErrors('code');
        $this->assertSame('UBAD/23/SPC/001', $master->fresh()->code);
    }

    public function test_master_validates_components_and_duplicate_code_without_writing_invalid_data(): void
    {
        $page = Livewire::test(ScmRequestNumbers::class)->set('master.name', 'Uji')->call('saveMaster')
            ->assertHasErrors(['project_prefix', 'work_code', 'running_number'])->set('master.project_prefix', 'TEST')
            ->set('master.work_code', 'SC')->set('master.running_number', 1)->call('saveMaster')->assertHasNoErrors();
        $page->set('master.project_prefix', 'TEST')->set('master.work_code', 'SC')->set('master.running_number', 1)
            ->set('master.name', 'Duplikat')->call('saveMaster')->assertHasErrors('code');
        $this->assertSame(2, ProjectNumbering::count());
        $page->call('newMaster')->assertSet('master.format', 'standard')->assertSet('master.project_prefix', '')->assertSet('master.running_number', '');
        $ltro = ProjectNumbering::where('code', 'COMP-19E001')->sole();
        $page->call('editMaster', $ltro->id)->assertSet('master.format', 'legacy')->assertSet('master.code', 'COMP-19E001')
            ->set('master.name', 'LTRO 1B')->call('saveMaster')->assertHasNoErrors();
        $this->assertSame('COMP-19E001', $ltro->fresh()->code);
    }
}
