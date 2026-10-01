<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('requires_cbo_approval')
                ->default(false)
                ->after('is_active');
        });

        DB::table('departments')
            ->whereRaw('LOWER(code) = ?', ['legal'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%legal%'])
            ->update(['requires_cbo_approval' => true]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('requires_cbo_approval');
        });
    }
};
