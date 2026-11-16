<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_lpj_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['company', 'year']);
        });
    }

    public function down(): void
    {
        if (DB::table('travel_lpj_number_sequences')->where('last_number', '>', 0)->exists()) {
            throw new RuntimeException('Urutan nomor LPJ yang sudah diterbitkan harus dipertahankan.');
        }
        Schema::dropIfExists('travel_lpj_number_sequences');
    }
};
