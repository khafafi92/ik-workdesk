<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class MenuAccessPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'master.departments.manage' => ['Kelola Departments', 'Master Data'],
            'master.employees.manage' => ['Kelola Employees', 'Master Data'],
            'master.work-locations.manage' => ['Kelola Work Locations', 'Attendance Report'],
        ] as $code => [$name, $module]) {
            Permission::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'module' => $module, 'description' => $name, 'is_active' => true,
            ]);
        }
    }
}
