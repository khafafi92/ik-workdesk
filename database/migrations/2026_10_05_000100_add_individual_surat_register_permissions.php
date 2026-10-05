<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'letters.hr-kpmog' => ['Access HR KPMOG Letters Menu', 'Open the HR KPMOG letter register.'],
            'letters.hr-apca' => ['Access HR APCA Letters Menu', 'Open the HR APCA letter register.'],
            'letters.kpmog-project-bd' => ['Access KPMOG Project and BD Letters Menu', 'Open the KPMOG Project and BD letter register.'],
            'letters.ate-general' => ['Access APCA General Letters Menu', 'Open the APCA general letter register.'],
        ];

        foreach ($permissions as $code => [$name, $description]) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'module' => 'Surat',
                'description' => $description,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $legacyId = DB::table('permissions')->where('code', 'letters.outgoing')->value('id');

        if (! $legacyId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_keys($permissions))
            ->pluck('id');
        $userIds = DB::table('permission_user')->where('permission_id', $legacyId)->pluck('user_id');

        foreach ($userIds as $userId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_user')->updateOrInsert(
                    ['user_id' => $userId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'letters.hr-kpmog',
                'letters.hr-apca',
                'letters.kpmog-project-bd',
                'letters.ate-general',
            ])
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
