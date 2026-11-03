<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_cbes', function (Blueprint $table): void {
            $table->text('comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_cbes', function (Blueprint $table): void {
            $table->dropColumn('comment');
        });
    }
};
