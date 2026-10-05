<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_permit_company', function (Blueprint $table): void {
            $table->foreignId('employee_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('permit_company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->primary(['employee_id', 'permit_company_id']);
            $table->index('permit_company_id');
        });

        DB::table('employees')
            ->whereNotNull('permit_company_id')
            ->get(['id', 'permit_company_id'])
            ->each(function (object $employee): void {
                DB::table('employee_permit_company')->insert([
                    'employee_id' => $employee->id,
                    'permit_company_id' => $employee->permit_company_id,
                ]);
            });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('permit_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('permit_company_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('employee_permit_company')
            ->orderBy('employee_id')
            ->orderBy('permit_company_id')
            ->get(['employee_id', 'permit_company_id'])
            ->unique('employee_id')
            ->each(function (object $assignment): void {
                DB::table('employees')
                    ->where('id', $assignment->employee_id)
                    ->update(['permit_company_id' => $assignment->permit_company_id]);
            });

        Schema::dropIfExists('employee_permit_company');
    }
};
