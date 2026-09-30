<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $levelCodes = [
            'system-admin',
            'administrator',
            'admin',
            'department-manager',
            'supervisor',
            'general-affairs',
            'attendance-operator',
            'cbo',
            'requester',
        ];
        $roleIds = DB::table('roles')
            ->whereIn('code', $levelCodes)
            ->pluck('id', 'code');
        $now = now();

        DB::table('users')
            ->select('id', 'access_level')
            ->orderBy('id')
            ->each(function (object $user) use ($roleIds, $now): void {
                $level = $roleIds->has($user->access_level)
                    ? $user->access_level
                    : 'requester';
                $roleId = $roleIds->get($level);

                if (! $roleId) {
                    return;
                }

                DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->whereIn('role_id', $roleIds->values())
                    ->delete();

                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            });
    }

    public function down(): void
    {
        // A normalized hierarchy cannot safely restore prior multi-role assignments.
    }
};
