<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_daily_monitorings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('managing_department_id')->constrained('departments')->restrictOnDelete();
            $table->string('monitoring_type', 20);
            $table->string('source_name');
            $table->string('location')->nullable();
            $table->dateTime('checked_at');
            $table->date('backup_date')->nullable();
            $table->json('checks');
            $table->text('notes')->nullable();
            $table->unsignedInteger('attention_count')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->string('inspector_name');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['managing_department_id', 'checked_at']);
            $table->index(['monitoring_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_daily_monitorings');
    }
};
