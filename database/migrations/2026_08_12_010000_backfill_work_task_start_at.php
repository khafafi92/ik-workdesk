<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('work_tasks')
            ->whereNull('start_at')
            ->whereNotNull('ticket_id')
            ->orderBy('id')
            ->chunkById(200, function ($workTasks): void {
                $tickets = DB::table('tickets')
                    ->whereIn('id', $workTasks->pluck('ticket_id')->unique())
                    ->get(['id', 'reported_at', 'created_at'])
                    ->keyBy('id');

                foreach ($workTasks as $workTask) {
                    $ticket = $tickets->get($workTask->ticket_id);

                    if (! $ticket) {
                        continue;
                    }

                    DB::table('work_tasks')
                        ->where('id', $workTask->id)
                        ->update([
                            'start_at' => $ticket->reported_at
                                ?? $ticket->created_at
                                ?? now(),
                            'updated_at' => $workTask->updated_at,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Nilai Start At tidak dikosongkan saat rollback agar data operasional aman.
    }
};
