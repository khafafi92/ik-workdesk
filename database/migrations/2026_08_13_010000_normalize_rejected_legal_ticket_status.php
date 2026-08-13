<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->where('status', 'cancel')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('work_tasks')
                    ->whereColumn('work_tasks.ticket_id', 'tickets.id')
                    ->whereColumn('work_tasks.department_id', 'tickets.handler_department_id')
                    ->where('work_tasks.approval_status', 'rejected');
            })
            ->update(['status' => 'rejected']);
    }

    public function down(): void
    {
        // Status keputusan tidak dikembalikan menjadi Cancel saat rollback.
    }
};
