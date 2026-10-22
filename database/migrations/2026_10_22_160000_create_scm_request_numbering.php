<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 2);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['kind', 'year']);
        });
        Schema::create('scm_request_numbers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('kind', 2);
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('scm_requests')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_request_numbers');
        Schema::dropIfExists('scm_number_sequences');
    }
};
