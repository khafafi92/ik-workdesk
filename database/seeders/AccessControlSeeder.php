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
                    'name' => 'View Reports',
                    'code' => 'report.view',
                    'module' => 'Reports',
                    'description' => 'View operational reports within permitted department scope.',
                ],
                [
                    'name' => 'Export Reports',
                    'code' => 'report.export',
                    'module' => 'Reports',
                    'description' => 'Export operational reports within permitted department scope.',
                ],
                [
                    'name' => 'View LTRO Management',
                    'code' => 'ltro.view',
                    'module' => 'LTRO Management',
                    'description' => 'View LTRO monitoring and reports.',
                ],
                [
                    'name' => 'Manage LTRO Management',
                    'code' => 'ltro.manage',
                    'module' => 'LTRO Management',
                    'description' => 'Create, edit, import, and delete LTRO data.',
                ],
                [
                    'name' => 'Request ATK',
                    'code' => 'atk.request',
                    'module' => 'ATK',
                    'description' => 'Create ATK requests, confirm received items, and record usage.',
                ],
                [
                    'name' => 'Manage ATK',
                    'code' => 'atk.manage',
                    'module' => 'ATK',
                    'description' => 'Manage ATK warehouse stock, requests, and department stock.',
                ],
                [
                    'name' => 'View ATK Reports',
                    'code' => 'atk.report',
                    'module' => 'ATK',
                    'description' => 'View ATK requirement and usage reports.',
                ],
                [
                    'name' => 'View Outgoing Letters',
                    'code' => 'letters.view',
                    'module' => 'Surat',
                    'description' => 'View outgoing-letter register.',
                ],
                [
                    'name' => 'Create Outgoing Letters',
                    'code' => 'letters.create',
                    'module' => 'Surat',
                    'description' => 'Create and edit own draft outgoing letters.',
                ],
                [
                    'name' => 'Issue Outgoing Letters',
                    'code' => 'letters.issue',
                    'module' => 'Surat',
                    'description' => 'Issue outgoing letters and reserve document numbers.',
                ],
                [
                    'name' => 'Manage Letter Masters',
                    'code' => 'letters.manage',
                    'module' => 'Surat',
                    'description' => 'Manage document types and numbering templates.',
                ],
                [
                    'name' => 'Input Legacy Letter Numbers',
                    'code' => 'letters.legacy-import',
                    'module' => 'Surat',
                    'description' => 'Record existing issued document numbers without regeneration.',
                ],
                [
                    'name' => 'Access Outgoing Letters Menu',
                    'code' => 'letters.outgoing',
                    'module' => 'Surat',
                    'description' => 'Open the outgoing-letter menu.',
                ],
                [
                    'name' => 'Access Letter Profiles Menu',
                    'code' => 'letters.profiles',
                    'module' => 'Surat',
                    'description' => 'Open the letter-profiles menu.',
                ],
                [
                    'name' => 'Access Document Types Menu',
                    'code' => 'letters.document-types',
                    'module' => 'Surat',
                    'description' => 'Open the document-types menu.',
                ],
                [
                    'name' => 'Access Numbering Templates Menu',
                    'code' => 'letters.numbering-templates',
                    'module' => 'Surat',
                    'description' => 'Open the numbering-templates menu.',
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
                    'name' => 'Manage Request Categories',
                    'code' => 'ticket-categories.manage',
                    'module' => 'Service Desk',
                    'description' => 'Manage request categories.',
                ],
                [
                    'name' => 'Manage Daily Activities',
                    'code' => 'daily-activities.manage',
                    'module' => 'Daily Reports',
                    'description' => 'Access daily activities.',
                ],
                [
                    'name' => 'Manage Task Categories',
                    'code' => 'task-categories.manage',
                    'module' => 'Daily Reports',
                    'description' => 'Manage task categories.',
                ],
                [
                    'name' => 'Manage Subject Categories',
                    'code' => 'master.subject-categories.manage',
                    'module' => 'Master Data',
                    'description' => 'Manage Subject Categories.',
                ],
                [
                    'name' => 'Manage Permit & KBLI',
                    'code' => 'master.permit-kbli.manage',
                    'module' => 'Master Data',
                    'description' => 'Manage Permit & KBLI.',
                ],
                [
                    'name' => 'Manage Projects',
                    'code' => 'master.projects.manage',
                    'module' => 'Master Data',
                    'description' => 'Manage Projects.',
                ],
                [
                    'name' => 'Manage Activity Categories',
                    'code' => 'master.activity-categories.manage',
                    'module' => 'Master Data',
                    'description' => 'Manage Activity Categories.',
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
                    'name' => 'Sys Administrator',
                    'code' => 'system-admin',
                    'description' => 'Full access to every module.',
                    'permissions' => ['*'],
                ],
                [
                    'name' => 'Administrator',
                    'code' => 'administrator',
                    'description' => 'Manage user accounts and assign menu access, without Sys Administrator authority.',
                    'permissions' => [
                        'users.manage',
                        'roles.manage',
                    ],
                ],
                [
                    'name' => 'Admin',
                    'code' => 'admin',
                    'description' => 'Operational admin. Module access is granted from the user menu checklist.',
                    'permissions' => [],
                ],
                [
                    'name' => 'Attendance Operator',
                    'code' => 'attendance-operator',
                    'description' => 'Attendance workflow scope. Menu access is assigned per user.',
                    'permissions' => [
                        'attendance.view',
                        'attendance.upload',
                        'attendance.manage',
                    ],
                ],
                [
                    'name' => 'Supervisor',
                    'code' => 'supervisor',
                    'description' => 'Department supervision and workflow scope. Menu access is assigned per user.',
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
                    'description' => 'Department management and workflow scope. Menu access is assigned per user.',
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
                    'description' => 'Own-request workflow scope. Menu access is assigned per user.',
                    'permissions' => [
                        'tickets.create',
                        'tickets.view',
                    ],
                ],
                [
                    'name' => 'General Affairs',
                    'code' => 'general-affairs',
                    'description' => 'Operational workflow scope. Menu access is assigned per user.',
                    'permissions' => [
                        'atk.request',
                        'atk.manage',
                        'atk.report',
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
                $menuPermissionCodes = [
                    'master-data.manage', 'report.view', 'report.export', 'ltro.view', 'ltro.manage',
                    'atk.request', 'atk.manage', 'atk.report', 'attendance.view', 'attendance.upload', 'attendance.manage',
                    'tickets.create', 'tickets.view', 'tickets.manage', 'worklogs.view', 'worklogs.manage',
                    'letters.view', 'letters.create', 'letters.issue', 'letters.manage', 'letters.legacy-import',
                    'letters.outgoing', 'letters.profiles', 'letters.document-types', 'letters.numbering-templates',
                    'reminders.view', 'reminders.manage', 'meeting-bookings.view', 'meeting-bookings.create',
                    'meeting-bookings.cancel-own', 'meeting-bookings.manage', 'meeting-rooms.manage',
                    'vehicle-bookings.view', 'vehicle-bookings.create', 'vehicle-bookings.cancel-own',
                    'vehicle-bookings.manage', 'vehicles.manage', 'ticket-categories.manage',
                    'daily-activities.manage', 'task-categories.manage',
                ];
                $permissionCodes = $roleData['permissions'] === ['*']
                    ? ['*']
                    : array_values(array_diff($roleData['permissions'], $menuPermissionCodes));

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
