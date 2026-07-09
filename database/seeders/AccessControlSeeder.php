<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Permissions
            |--------------------------------------------------------------------------
            */

            $permissions = [
                [
                    'name' => 'Manage Users',
                    'code' => 'users.manage',
                    'module' => 'User Management',
                    'description' => 'Create, edit, and delete user accounts.',
                ],
                [
                    'name' => 'Manage Roles',
                    'code' => 'roles.manage',
                    'module' => 'User Management',
                    'description' => 'Create roles and assign permissions.',
                ],
                [
                    'name' => 'Manage Master Data',
                    'code' => 'master-data.manage',
                    'module' => 'Master Data',
                    'description' => 'Manage departments, employees, categories, and locations.',
                ],

                [
                    'name' => 'View Attendance',
                    'code' => 'attendance.view',
                    'module' => 'Attendance',
                    'description' => 'View attendance reports.',
                ],
                [
                    'name' => 'Upload Attendance',
                    'code' => 'attendance.upload',
                    'module' => 'Attendance',
                    'description' => 'Upload attendance periods and source files.',
                ],
                [
                    'name' => 'Manage Attendance',
                    'code' => 'attendance.manage',
                    'module' => 'Attendance',
                    'description' => 'Process, edit, and manage attendance data.',
                ],

                [
                    'name' => 'Create Service Desk',
                    'code' => 'tickets.create',
                    'module' => 'Service Desk',
                    'description' => 'Create a new service desk request.',
                ],
                [
                    'name' => 'View Service Desk',
                    'code' => 'tickets.view',
                    'module' => 'Service Desk',
                    'description' => 'View accessible service desk requests.',
                ],
                [
                    'name' => 'Manage Service Desk',
                    'code' => 'tickets.manage',
                    'module' => 'Service Desk',
                    'description' => 'Edit and manage accessible service desk requests.',
                ],

                [
                    'name' => 'View Work Logs',
                    'code' => 'worklogs.view',
                    'module' => 'Work Logs',
                    'description' => 'View work logs for accessible departments.',
                ],
                [
                    'name' => 'Manage Work Logs',
                    'code' => 'worklogs.manage',
                    'module' => 'Work Logs',
                    'description' => 'Edit work logs for accessible departments.',
                ],

                [
                    'name' => 'View Findings',
                    'code' => 'findings.view',
                    'module' => 'Due Diligence',
                    'description' => 'View due diligence findings.',
                ],
                [
                    'name' => 'Manage Findings',
                    'code' => 'findings.manage',
                    'module' => 'Due Diligence',
                    'description' => 'Create, edit, resolve, and delete findings.',
                ],
                [
                    'name' => 'Respond to Findings',
                    'code' => 'findings.respond',
                    'module' => 'Due Diligence',
                    'description' => 'Submit responses to due diligence findings.',
                ],

                [
                    'name' => 'Create Comments',
                    'code' => 'comments.create',
                    'module' => 'Comments',
                    'description' => 'Create comments and updates.',
                ],

                [
                    'name' => 'View Reminders',
                    'code' => 'reminders.view',
                    'module' => 'Reminders',
                    'description' => 'View reminders.',
                ],
                [
                    'name' => 'Manage Reminders',
                    'code' => 'reminders.manage',
                    'module' => 'Reminders',
                    'description' => 'Create, edit, and delete reminders.',
                ],
            ];

            foreach ($permissions as $permissionData) {
                Permission::updateOrCreate(
                    [
                        'code' => $permissionData['code'],
                    ],
                    [
                        ...$permissionData,
                        'is_active' => true,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            $roles = [
                [
                    'name' => 'System Administrator',
                    'code' => 'system-admin',
                    'description' => 'Full access to every module.',
                    'permissions' => ['*'],
                ],
                [
                    'name' => 'User Manager',
                    'code' => 'user-manager',
                    'description' => 'Manage user accounts and application roles.',
                    'permissions' => [
                        'users.manage',
                        'roles.manage',
                    ],
                ],
                [
                    'name' => 'Attendance Operator',
                    'code' => 'attendance-operator',
                    'description' => 'View, upload, and manage attendance.',
                    'permissions' => [
                        'attendance.view',
                        'attendance.upload',
                        'attendance.manage',
                    ],
                ],
                [
                    'name' => 'Attendance Viewer',
                    'code' => 'attendance-viewer',
                    'description' => 'View attendance reports only.',
                    'permissions' => [
                        'attendance.view',
                    ],
                ],
                [
                    'name' => 'Department Reviewer',
                    'code' => 'department-reviewer',
                    'description' => 'Manage work logs and findings for selected departments.',
                    'permissions' => [
                        'tickets.view',
                        'tickets.manage',
                        'worklogs.view',
                        'worklogs.manage',
                        'findings.view',
                        'findings.manage',
                        'comments.create',
                        'reminders.view',
                    ],
                ],
                [
                    'name' => 'Manager',
                    'code' => 'department-manager',
                    'description' => 'Monitor service desk, work logs, and findings for selected departments.',
                    'permissions' => [
                        'tickets.create',
                        'tickets.view',
                        'worklogs.view',
                        'findings.view',
                        'comments.create',
                        'reminders.view',
                    ],
                ],
                [
                    'name' => 'Requester',
                    'code' => 'requester',
                    'description' => 'Create requests and respond to findings.',
                    'permissions' => [
                        'tickets.create',
                        'tickets.view',
                        'worklogs.view',
                        'findings.respond',
                        'comments.create',
                        'reminders.view',
                    ],
                ],
                [
                    'name' => 'General User',
                    'code' => 'general-user',
                    'description' => 'Standard application user.',
                    'permissions' => [
                        'tickets.create',
                        'tickets.view',
                        'worklogs.view',
                        'findings.respond',
                        'comments.create',
                        'reminders.view',
                    ],
                ],
            ];

            foreach ($roles as $roleData) {
                $permissionCodes = $roleData['permissions'];

                unset($roleData['permissions']);

                $role = Role::updateOrCreate(
                    [
                        'code' => $roleData['code'],
                    ],
                    [
                        ...$roleData,
                        'is_active' => true,
                    ]
                );

                $permissionIds = $permissionCodes === ['*']
                    ? Permission::query()->pluck('id')
                    : Permission::query()
                        ->whereIn('code', $permissionCodes)
                        ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
