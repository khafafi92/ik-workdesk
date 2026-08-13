<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->boolean('requires_permit')
                ->default(false)
                ->after('workflow_type');
        });

        $legalDepartmentId = DB::table('departments')
            ->whereRaw('lower(name) = ?', ['legal'])
            ->value('id');

        if ($legalDepartmentId) {
            DB::table('ticket_categories')->updateOrInsert(
                ['code' => 'LG-TENDER'],
                [
                    'handler_department_id' => $legalDepartmentId,
                    'name' => 'Kebutuhan Tender',
                    'workflow_type' => 'single',
                    'requires_permit' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('ticket_categories')
            ->where('code', 'LG-TENDER')
            ->delete();

        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->dropColumn('requires_permit');
        });
    }
};
