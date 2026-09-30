<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atk_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('atk_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('atk_items', function (Blueprint $table): void {
            $table->foreignId('atk_category_id')->nullable()->after('category')->constrained('atk_categories')->nullOnDelete();
            $table->foreignId('atk_unit_id')->nullable()->after('unit')->constrained('atk_units')->nullOnDelete();
        });

        $now = now();
        foreach (['PCS', 'KG', 'Buah', 'Unit', 'Dus', 'Pack', 'Rim'] as $unit) {
            DB::table('atk_units')->updateOrInsert(
                ['name' => $unit],
                ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (DB::table('atk_items')->whereNotNull('unit')->distinct()->pluck('unit') as $unit) {
            $unitName = trim($unit);
            $unitId = DB::table('atk_units')
                ->whereRaw('LOWER(name) = ?', [strtolower($unitName)])
                ->value('id');

            if (! $unitId) {
                $unitId = DB::table('atk_units')->insertGetId([
                    'name' => $unitName,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('atk_items')->where('unit', $unit)->update(['atk_unit_id' => $unitId]);
        }

        foreach (DB::table('atk_items')->whereNotNull('category')->distinct()->pluck('category') as $category) {
            $categoryName = trim($category);
            $categoryId = DB::table('atk_categories')
                ->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                ->value('id');

            if (! $categoryId) {
                $categoryId = DB::table('atk_categories')->insertGetId([
                    'name' => $categoryName,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('atk_items')->where('category', $category)->update(['atk_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('atk_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('atk_category_id');
            $table->dropConstrainedForeignId('atk_unit_id');
        });

        Schema::dropIfExists('atk_units');
        Schema::dropIfExists('atk_categories');
    }
};
