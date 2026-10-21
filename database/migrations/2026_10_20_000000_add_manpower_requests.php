<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->boolean('requires_manpower')->default(false);
        });
        Schema::table('tickets', function (Blueprint $table): void {
            $table->json('manpower_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('manpower_details'));
        Schema::table('ticket_categories', fn (Blueprint $table) => $table->dropColumn('requires_manpower'));
    }
};
