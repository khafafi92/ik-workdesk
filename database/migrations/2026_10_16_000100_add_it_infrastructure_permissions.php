<?php

use App\Enums\ItPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (ItPermission::cases() as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission->value], [
                'name' => $permission->label(),
                'module' => 'Infrastruktur IT',
                'description' => $permission->label(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', array_column(ItPermission::cases(), 'value'))->pluck('id');
        DB::table('permission_user')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
