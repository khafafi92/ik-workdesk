<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['it_devices', 'it_inspections'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('location_name', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['it_devices', 'it_inspections'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('location_name');
            });
        }
    }
};
