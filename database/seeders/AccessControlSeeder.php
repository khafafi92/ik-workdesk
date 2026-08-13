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
                    'name' => 'Approve Legal Tasks',
                    'code' => 'legal-tasks.approve',
                    'module' => 'Work Logs',
                    'description' => 'Approve Legal work logs before they are released to the Legal department.',
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
                [
                    'name' => 'View Meeting Bookings',
                    'code' => 'meeting-bookings.view',
                    'module' => 'Meeting Room',
                    'description' => 'View the meeting room calendar and own bookings.',
                ],
                [
                    'name' => 'Create Meeting Bookings',
                    'code' => 'meeting-bookings.create',
                    'module' => 'Meeting Room',
                    'description' => 'Create meeting room bookings.',
                ],
                [
                    'name' => 'Cancel Own Meeting Bookings',
                    'code' => 'meeting-bookings.cancel-own',
                    'module' => 'Meeting Room',
                    'description' => 'Cancel own future meeting room bookings.',
                ],
                [
                    'name' => 'Manage Meeting Bookings',
                    'code' => 'meeting-bookings.manage',
                    'module' => 'Meeting Room',
                    'description' => 'View, edit, and cancel every meeting room booking.',
                ],
                [
                    'name' => 'Manage Meeting Rooms',
                    'code' => 'meeting-rooms.manage',
                    'module' => 'Meeting Room',
                    'description' => 'Create, edit, activate, and deactivate meeting rooms.',
                ],
                [
                    'name' => 'View Vehicle Bookings',
                    'code' => 'vehicle-bookings.view',
                    'module' => 'Vehicle Booking',
                    'description' => 'View vehicle calendar and own bookings.',
                ],
                [
                    'name' => 'Create Vehicle Bookings',
                    'code' => 'vehicle-bookings.create',
                    'module' => 'Vehicle Booking',
                    'description' => 'Create vehicle bookings.',
                ],
                [
                    'name' => 'Cancel Own Vehicle Bookings',
                    'code' => 'vehicle-bookings.cancel-own',
                    'module' => 'Vehicle Booking',
                    'description' => 'Cancel own future vehicle bookings.',
                ],
                [
                    'name' => 'Manage Vehicle Bookings',
                    'code' => 'vehicle-bookings.manage',
                    'module' => 'Vehicle Booking',
                    'description' => 'Manage every vehicle booking.',
                ],
                [
                    'name' => 'Manage Vehicles',
                    'code' => 'vehicles.manage',
                    'module' => 'Vehicle Booking',
                    'description' => 'Manage vehicle master data.',
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
                    'name' => 'Supervisor',
                    'code' => 'supervisor',
                    'description' => 'Supervise work logs and findings for selected departments.',
                    'permissions' => [
                        'tickets.view',
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
                    'name' => 'Chief Business Officer',
                    'code' => 'cbo',
                    'description' => 'Approve work logs addressed to the Legal department.',
                    'permissions' => [
                        'tickets.view',
                        'worklogs.view',
                        'legal-tasks.approve',
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
