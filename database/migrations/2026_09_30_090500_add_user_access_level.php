<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('access_level')->nullable()->index();
        });

        $levels = [
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

        DB::table('users')->where('is_admin', true)->update([
            'access_level' => 'system-admin',
        ]);

        foreach ($levels as $level) {
            DB::table('users')
                ->whereNull('access_level')
                ->whereIn('id', function ($query) use ($level): void {
                    $query->select('role_user.user_id')
                        ->from('role_user')
                        ->join('roles', 'roles.id', '=', 'role_user.role_id')
                        ->where('roles.code', $level);
                })
                ->update(['access_level' => $level]);
        }

        DB::table('users')->whereNull('access_level')->update([
            'access_level' => 'requester',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['access_level']);
            $table->dropColumn('access_level');
        });
    }
};
