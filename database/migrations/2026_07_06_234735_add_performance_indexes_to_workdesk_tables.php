<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tickets / Service Requests
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('tickets')) {
            if (Schema::hasColumn('tickets', 'employee_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_tickets_employee
                    ON tickets (employee_id)'
                );
            }

            if (
                Schema::hasColumn('tickets', 'requester_department_id')
                && Schema::hasColumn('tickets', 'status')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_tickets_requester_status
                    ON tickets (requester_department_id, status)'
                );
            }

            if (
                Schema::hasColumn('tickets', 'handler_department_id')
                && Schema::hasColumn('tickets', 'status')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_tickets_handler_status
                    ON tickets (handler_department_id, status)'
                );
            }

            if (
                Schema::hasColumn('tickets', 'status')
                && Schema::hasColumn('tickets', 'created_at')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_tickets_status_created
                    ON tickets (status, created_at DESC)'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Work Logs
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('work_tasks')) {
            if (Schema::hasColumn('work_tasks', 'ticket_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_work_tasks_ticket
                    ON work_tasks (ticket_id)'
                );
            }

            if (
                Schema::hasColumn('work_tasks', 'department_id')
                && Schema::hasColumn('work_tasks', 'status')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_work_tasks_department_status
                    ON work_tasks (department_id, status)'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Ticket Assignments
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('ticket_assignments')) {
            if (Schema::hasColumn('ticket_assignments', 'ticket_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_ticket_assignments_ticket
                    ON ticket_assignments (ticket_id)'
                );
            }

            if (
                Schema::hasColumn('ticket_assignments', 'department_id')
                && Schema::hasColumn('ticket_assignments', 'ticket_id')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS idx_ticket_assignments_department_ticket
                    ON ticket_assignments (department_id, ticket_id)'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Findings
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('work_task_findings')
            && Schema::hasColumn('work_task_findings', 'work_task_id')
            && Schema::hasColumn('work_task_findings', 'status')
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_findings_work_task_status
                ON work_task_findings (work_task_id, status)'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ticket Comments
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('ticket_comments')
            && Schema::hasColumn('ticket_comments', 'ticket_id')
            && Schema::hasColumn('ticket_comments', 'created_at')
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket_created
                ON ticket_comments (ticket_id, created_at DESC)'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Role & Permission Pivots
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('role_user')
            && Schema::hasColumn('role_user', 'user_id')
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_role_user_user
                ON role_user (user_id)'
            );
        }

        if (
            Schema::hasTable('permission_role')
            && Schema::hasColumn('permission_role', 'role_id')
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_permission_role_role
                ON permission_role (role_id)'
            );
        }
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS idx_tickets_employee'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_tickets_requester_status'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_tickets_handler_status'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_tickets_status_created'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_work_tasks_ticket'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_work_tasks_department_status'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_ticket_assignments_ticket'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_ticket_assignments_department_ticket'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_findings_work_task_status'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_ticket_comments_ticket_created'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_role_user_user'
        );

        DB::statement(
            'DROP INDEX IF EXISTS idx_permission_role_role'
        );
    }
};
