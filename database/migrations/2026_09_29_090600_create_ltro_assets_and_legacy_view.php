<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ltro_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('ltro_unit_id')->constrained('ltro_units')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'ltro_unit_id']);
        });

        DB::statement('CREATE VIEW assets AS
            SELECT id, name AS asset_name, ltro_unit_id AS unit_id, is_active, created_at, updated_at
            FROM ltro_assets');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS assets');
        Schema::dropIfExists('ltro_assets');
    }
};
