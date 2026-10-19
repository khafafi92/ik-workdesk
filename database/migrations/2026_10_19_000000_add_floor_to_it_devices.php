<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_devices', function (Blueprint $table): void {
            $table->string('floor', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('it_devices', function (Blueprint $table): void {
            $table->dropColumn('floor');
        });
    }
};
