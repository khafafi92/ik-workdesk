<?php

namespace Database\Seeders;

use App\Enums\ItPermission;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class ItInfrastructurePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ItPermission::cases() as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission->value], [
                'name' => $permission->label(),
                'module' => 'Infrastruktur IT',
                'description' => $permission->label(),
                'is_active' => true,
            ]);
        }
    }
}
