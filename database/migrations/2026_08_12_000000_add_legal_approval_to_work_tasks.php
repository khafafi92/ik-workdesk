<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->string('approval_status')->nullable()->after('status')->index();
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->after('approval_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });

        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'Approve Legal Tasks',
            'code' => 'legal-tasks.approve',
            'module' => 'Work Logs',
            'description' => 'Approve Legal work logs before they are released to the Legal department.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('roles')->insertOrIgnore([
            'name' => 'Chief Business Officer',
            'code' => 'cbo',
            'description' => 'Approve work logs addressed to the Legal department.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->where('code', 'cbo')->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'tickets.view',
                'worklogs.view',
                'legal-tasks.approve',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'approval_status',
                'approved_by_user_id',
                'approved_at',
            ]);
        });

        $roleId = DB::table('roles')->where('code', 'cbo')->value('id');
        $permissionId = DB::table('permissions')
            ->where('code', 'legal-tasks.approve')
            ->value('id');

        if ($roleId) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
