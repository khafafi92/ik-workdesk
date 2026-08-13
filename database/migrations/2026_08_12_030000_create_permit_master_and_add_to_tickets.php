<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permit_kblis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['permit_company_id', 'code']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('permit_company_id')
                ->nullable()
                ->after('ticket_category_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('permit_kbli_id')
                ->nullable()
                ->after('permit_company_id')
                ->constrained('permit_kblis')
                ->nullOnDelete();
            $table->boolean('permit_kbli_unavailable')
                ->default(false)
                ->after('permit_kbli_id');
        });

        $now = now();

        DB::table('permit_companies')->insert([
            [
                'code' => 'KPMOG',
                'name' => 'KPMOG',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'APCA',
                'name' => 'APCA',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('permit_kbli_id');
            $table->dropConstrainedForeignId('permit_company_id');
            $table->dropColumn('permit_kbli_unavailable');
        });

        Schema::dropIfExists('permit_kblis');
        Schema::dropIfExists('permit_companies');
    }
};
