<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_task_findings', function (Blueprint $table): void {
            $table->id();

            $table->string('finding_no', 30)
                ->unique();

            $table->foreignId('work_task_id')
                ->constrained('work_tasks')
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');

            $table->text('description');

            $table->string('risk_level', 20)
                ->default('medium');

            $table->text('recommendation')
                ->nullable();

            $table->string('status', 20)
                ->default('open');

            /*
            |--------------------------------------------------------------------------
            | File hasil temuan dari department reviewer
            |--------------------------------------------------------------------------
            */

            $table->json('attachments')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Respons dari requester/pembuat Due Diligence
            |--------------------------------------------------------------------------
            */

            $table->text('requester_response')
                ->nullable();

            $table->json('response_attachments')
                ->nullable();

            $table->foreignId('responded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('responded_at')
                ->nullable();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'work_task_id',
                'status',
            ]);

            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_findings');
    }
};