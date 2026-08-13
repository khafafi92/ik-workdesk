<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->text('permit_result_notes')->nullable();
            $table->json('permit_result_attachments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'permit_result_notes',
                'permit_result_attachments',
            ]);
        });
    }
};
