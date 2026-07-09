<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_category_departments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->boolean('is_default')
                ->default(true);

            $table->unsignedSmallInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['ticket_category_id', 'department_id'],
                'ticket_category_department_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_category_departments');
    }
};