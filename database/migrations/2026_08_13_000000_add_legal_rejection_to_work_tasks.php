<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->foreignId('rejected_by_user_id')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropForeign(['rejected_by_user_id']);
            $table->dropColumn([
                'rejection_reason',
                'rejected_by_user_id',
                'rejected_at',
            ]);
        });
    }
};
