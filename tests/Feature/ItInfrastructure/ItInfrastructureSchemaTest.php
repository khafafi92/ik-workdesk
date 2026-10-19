<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceType;
use App\Enums\ItPermission;
use App\Models\ItChecklistItem;
use App\Models\ItChecklistTemplate;
use App\Models\ItDevice;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ItInfrastructureChecklistSeeder;
use Database\Seeders\ItInfrastructurePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ItInfrastructureTestCase;

class ItInfrastructureSchemaTest extends ItInfrastructureTestCase
{
    public function test_schema_has_only_module_and_allowlisted_parent_tables_without_ticket_integration(): void
    {
        $tables = collect(Schema::getTables())->pluck('name');
        $this->assertCount(8, $tables->filter(fn (string $name): bool => str_starts_with($name, 'it_')));
        $this->assertFalse(Schema::hasTable('tickets'));
        $this->assertFalse(Schema::hasTable('ltro_assets'));
        $this->assertFalse(Schema::hasTable('atk_items'));
        $this->assertFalse(Schema::hasTable('outgoing_letters'));
        $this->assertFalse(Schema::hasColumn('it_inspection_follow_ups', 'ticket_id'));
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->assertGreaterThan(0, DB::selectOne("SELECT COUNT(*) AS total FROM pg_constraint WHERE contype = 'f' AND connamespace = 'public'::regnamespace")->total);
        } else {
            $this->assertSame(1, DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        }
    }

    public function test_catalog_is_complete_distinct_idempotent_and_does_not_reactivate_templates(): void
    {
        $this->assertSame(count(ItDeviceType::cases()), ItChecklistTemplate::query()->count());
        $count = ItChecklistItem::query()->count();
        $codes = [];
        foreach (ItDeviceType::cases() as $type) {
            $template = ItChecklistTemplate::query()->where('device_type', $type->value)->firstOrFail();
            $this->assertSame($type->category(), $template->category);
            $this->assertGreaterThanOrEqual(12, $template->items()->count());
            $codes[$type->value] = $template->items()->pluck('code')->all();
            foreach ($template->items as $item) {
                $this->assertNotEmpty($item->instructions);
            }
        }
        $this->assertContains('firewall_policy', $codes['fortigate']);
        $this->assertContains('vpn_tunnel', $codes['fortigate']);
        $this->assertContains('ssid', $codes['access_point']);
        $this->assertContains('vlan', $codes['access_point']);
        $this->assertContains('port_errors', $codes['switch']);
        $this->assertContains('poe_budget', $codes['switch']);
        $this->assertContains('battery_health', $codes['laptop']);
        $this->assertNotContains('battery_health', $codes['pc']);
        $this->assertContains('restore_test', $codes['server']);
        $this->assertContains('database', $codes['server']);
        $this->assertContains('ups_runtime', $codes['server_room']);

        $template->update(['is_active' => false]);
        $this->inspection($this->device(ItDeviceType::Laptop));
        (new ItInfrastructureChecklistSeeder)->run();
        $this->assertSame($count, ItChecklistItem::query()->count());
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_permission_seed_is_idempotent_and_assigns_no_users_or_roles(): void
    {
        (new ItInfrastructurePermissionSeeder)->run();
        (new ItInfrastructurePermissionSeeder)->run();
        $this->assertSame(count(ItPermission::cases()), Permission::query()->where('module', 'Infrastruktur IT')->count());
        $this->assertSame(0, DB::table('permission_user')->count());
        $this->assertSame(0, DB::table('permission_role')->count());
        $this->assertFalse($this->inspector->hasPermission(ItPermission::DevicesManage->value));
    }

    public function test_server_checklist_revision_preserves_used_template_and_old_results(): void
    {
        $template = ItChecklistTemplate::query()->where('device_type', 'server')->sole();
        $template->update(['version' => 1]);
        $template->items()->where('sort_order', '>=', 2)->increment('sort_order');
        $template->items()->create(['code' => 'air_conditioner', 'section' => 'Lingkungan', 'label' => 'AC ruang', 'instructions' => 'Periksa operasi AC dan alarm pendinginan.', 'sort_order' => 2, 'value_type' => 'text']);
        $inspection = $this->inspection($this->device(ItDeviceType::Server));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        $results = $inspection->results()->get()->map->getRawOriginal()->all();

        (new ItInfrastructureChecklistSeeder)->run();
        (new ItInfrastructureChecklistSeeder)->run();

        $current = ItChecklistTemplate::query()->where('device_type', 'server')->where('is_active', true)->sole();
        $this->assertSame(2, $current->version);
        $this->assertSame(['room_temperature', 'ups_supply', 'power_supply'], $current->items()->where('section', 'Lingkungan')->pluck('code')->all());
        $this->assertSame(37, $current->items()->count());
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame(38, $template->items()->count());
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        $this->assertSame($results, $inspection->results()->get()->map->getRawOriginal()->all());
        $this->assertTrue(ItChecklistTemplate::query()->where('device_type', 'storage')->sole()->items()->where('code', 'air_conditioner')->exists());
    }

    public function test_computer_checklist_revision_preserves_old_drafts_and_final_results(): void
    {
        $removed = ['boot', 'performance', 'ram_usage', 'disk_health', 'network_access', 'access_security', 'windows_license', 'office_license'];
        $oldRecords = [];
        foreach ([ItDeviceType::Laptop, ItDeviceType::Pc] as $type) {
            $template = ItChecklistTemplate::query()->where('device_type', $type->value)->sole();
            $template->update(['version' => 1]);
            foreach ($removed as $index => $code) {
                $template->items()->create(['code' => $code, 'section' => 'Checklist versi lama', 'label' => $code, 'instructions' => 'Petunjuk historis', 'sort_order' => 100 + $index, 'value_type' => 'text']);
            }
            $inspection = $this->inspection($this->device($type));
            $this->fillRemainingResults($inspection);
            if ($type === ItDeviceType::Laptop) {
                $this->finalize($inspection);
            }
            $oldRecords[] = [$template, $inspection, $inspection->fresh()->getRawOriginal(), $inspection->results()->get()->map->getRawOriginal()->all()];
        }

        (new ItInfrastructureChecklistSeeder)->run();
        (new ItInfrastructureChecklistSeeder)->run();

        foreach ($oldRecords as [$old, $inspection, $before, $results]) {
            $current = ItChecklistTemplate::query()->where('device_type', $old->device_type->value)->where('is_active', true)->sole();
            $this->assertSame(2, $current->version);
            $this->assertSame(18, $current->items()->count());
            $this->assertSame(0, $current->items()->whereIn('code', $removed)->count());
            $this->assertFalse($old->fresh()->is_active);
            $this->assertSame(26, $old->items()->count());
            $this->assertSame($before, $inspection->fresh()->getRawOriginal());
            $this->assertSame($results, $inspection->results()->get()->map->getRawOriginal()->all());
        }
        $this->assertSame(14, ItChecklistTemplate::query()->count());
        $this->assertSame(12, ItChecklistTemplate::query()->where('is_active', true)->count());
    }

    public function test_existing_access_control_seeder_only_gives_it_role_permissions_to_system_admin(): void
    {
        (new AccessControlSeeder)->run();
        $this->assertSame(count(ItPermission::cases()), Role::query()->where('code', 'system-admin')->firstOrFail()->permissions()->where('module', 'Infrastruktur IT')->count());
        $this->assertSame(0, Role::query()->where('code', '!=', 'system-admin')->whereHas('permissions', fn ($query) => $query->where('module', 'Infrastruktur IT'))->count());
        $this->assertSame(0, DB::table('permission_user')->count());
    }

    public function test_it_permission_changes_preserve_existing_ticket_access(): void
    {
        $permission = Permission::query()->create(['code' => 'tickets.view', 'name' => 'View Service Desk', 'module' => 'Service Desk', 'is_active' => true]);
        $this->inspector->directPermissions()->attach($permission);
        $before = DB::table('permission_user')->get()->toArray();
        (new ItInfrastructurePermissionSeeder)->run();
        (require database_path('migrations/2026_10_16_000100_add_it_infrastructure_permissions.php'))->up();
        $this->assertEquals($before, DB::table('permission_user')->get()->toArray());
        $this->assertTrue($this->inspector->hasPermission('tickets.view'));
        $this->assertSame('View Service Desk', $permission->fresh()->name);
    }

    public function test_asset_number_is_unique_but_missing_numbers_and_repeated_serials_are_allowed(): void
    {
        $this->device(attributes: ['asset_number' => '', 'serial_number' => 'UNKNOWN']);
        $this->device(attributes: ['asset_number' => null, 'serial_number' => 'UNKNOWN']);
        $this->assertSame(2, ItDevice::query()->whereNull('asset_number')->count());
        $this->device(attributes: ['asset_number' => 'IT-001']);
        $this->expectException(QueryException::class);
        $this->device(attributes: ['asset_number' => 'IT-001']);
    }

    public function test_database_rejects_cross_template_result_even_without_model_events(): void
    {
        $inspection = $this->inspection();
        $otherItem = ItChecklistTemplate::query()->where('device_type', 'switch')->firstOrFail()->items()->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('it_inspection_results')->insert([
            'it_inspection_id' => $inspection->id,
            'it_checklist_item_id' => $otherItem->id,
            'it_checklist_template_id' => $inspection->it_checklist_template_id,
            'item_snapshot' => '{}',
        ]);
    }

    public function test_database_rejects_follow_up_linked_to_another_inspection(): void
    {
        $result = $this->inspectionResult($this->inspection());
        $other = $this->inspection();
        $this->expectException(QueryException::class);
        DB::table('it_inspection_follow_ups')->insert([
            'it_inspection_id' => $other->id,
            'it_inspection_result_id' => $result->id,
            'action_plan' => 'Tindakan',
            'assigned_to' => $this->inspector->id,
            'created_by' => $this->inspector->id,
        ]);
    }
}
