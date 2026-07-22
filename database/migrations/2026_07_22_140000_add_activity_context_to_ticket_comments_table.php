<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->foreignId('work_task_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->after('work_task_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('activity_type')
                ->default('message')
                ->after('user_id');
            $table->json('metadata')
                ->nullable()
                ->after('attachments');
            $table->index([
                'ticket_id',
                'work_task_id',
                'created_at',
            ], 'ticket_activity_context_index');
        });

        DB::table('ticket_comments')
            ->orderBy('id')
            ->eachById(function (object $activity): void {
                $departmentId = DB::table('employees')
                    ->where('user_id', $activity->user_id)
                    ->value('department_id');

                $workTaskId = $departmentId
                    ? DB::table('work_tasks')
                        ->where('ticket_id', $activity->ticket_id)
                        ->where('department_id', $departmentId)
                        ->value('id')
                    : null;

                DB::table('ticket_comments')
                    ->where('id', $activity->id)
                    ->update([
                        'department_id' => $departmentId,
                        'work_task_id' => $workTaskId,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->dropIndex('ticket_activity_context_index');
            $table->dropConstrainedForeignId('work_task_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['activity_type', 'metadata']);
        });
    }
};
