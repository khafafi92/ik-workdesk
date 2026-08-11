<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('code', 'user-manager')
            ->update([
                'name' => 'Account Administrator',
                'description' => 'Manage user accounts and assign preset roles.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('code', 'user-manager')
            ->update([
                'name' => 'User Manager',
                'description' => 'Manage user accounts and assign preset roles.',
                'updated_at' => now(),
            ]);
    }
};
