<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('position_level', 32)->nullable()->index();
            $table->foreignId('supervisor_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supervisor_employee_id');
            $table->dropColumn('position_level');
        });
    }
};
