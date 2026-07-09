<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | User ↔ Role
        |--------------------------------------------------------------------------
        */

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'role_id',
                'user_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Role ↔ Permission
        |--------------------------------------------------------------------------
        */

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'permission_id',
                'role_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | User ↔ Accessible Department
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'department_user_accesses',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('department_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique([
                    'user_id',
                    'department_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user_accesses');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};