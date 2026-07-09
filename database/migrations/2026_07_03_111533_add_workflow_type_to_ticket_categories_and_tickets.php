<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->string('workflow_type', 30)
                ->default('single');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('workflow_type', 30)
                ->default('single');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('workflow_type');
        });

        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->dropColumn('workflow_type');
        });
    }
};