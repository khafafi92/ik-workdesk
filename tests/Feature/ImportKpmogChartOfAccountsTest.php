<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportKpmogChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_hierarchy_and_refuses_to_overwrite_existing_accounts(): void
    {
        $user = User::factory()->create();
        $user->directPermissions()->attach(Permission::where('code', 'finance.coa.manage')->sole());
        $this->artisan('finance:import-kpmog-coa-2026', ['--actor' => $user->id])->assertSuccessful();
        $this->assertSame(431, FinanceAccount::where('company', 'KPMOG')->count());
        $root = FinanceAccount::where('code', '1000000')->sole();
        $child = FinanceAccount::where('code', '1000001')->sole();
        $this->assertSame($root->id, $child->parent_id);
        $this->assertSame('Kas/Bank', $child->account_type);
        $leaf = FinanceAccount::where('code', '1401001')->sole();
        $this->assertNotNull($leaf->parent?->parent_id);
        $this->assertDatabaseHas('scm_audits', ['entity_id' => $child->id, 'entity_type' => $child->getMorphClass(), 'actor_id' => $user->id, 'action' => 'impor_coa']);
        $this->artisan('finance:import-kpmog-coa-2026', ['--actor' => $user->id])->assertFailed();
        $this->assertSame(431, FinanceAccount::count());
    }

    public function test_import_requires_authorized_actor(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->artisan('finance:import-kpmog-coa-2026', ['--actor' => $user->id])->assertFailed();
        $this->assertSame(0, FinanceAccount::count());
    }
}
