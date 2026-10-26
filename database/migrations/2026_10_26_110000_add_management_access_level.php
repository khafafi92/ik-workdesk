<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            DB::table('roles')->insertOrIgnore([
                'code' => 'management',
                'name' => 'Management',
                'description' => 'Shared access level for CBO and COO. Menus and Legal approval are assigned per user.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $managementId = DB::table('roles')->where('code', 'management')->value('id');
            $legacy = DB::table('roles')->where('code', 'cbo')->first();
            $userIds = DB::table('users')->where('access_level', 'cbo')->pluck('id');

            if ($legacy) {
                $userIds = $userIds->merge(DB::table('role_user')->where('role_id', $legacy->id)->pluck('user_id'))->unique();
                $permissionIds = $legacy->is_active
                    ? DB::table('permission_role')->where('role_id', $legacy->id)->pluck('permission_id')
                    : collect();

                foreach (DB::table('role_user')->where('role_id', $legacy->id)->pluck('user_id') as $userId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_user')->insertOrIgnore([
                            'user_id' => $userId, 'permission_id' => $permissionId,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }

                DB::table('role_user')->where('role_id', $legacy->id)->delete();
            }

            foreach ($userIds as $userId) {
                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $userId, 'role_id' => $managementId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('users')->where('id', $userId)
                    ->where(fn ($query) => $query->whereNull('access_level')->orWhereIn('access_level', ['cbo', 'requester']))
                    ->update(['access_level' => 'management', 'updated_at' => $now]);
            }
        });
    }

    public function down(): void
    {
        // Reattaching CBO would restore Legal access that administrators may have revoked.
    }
};
