<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atk_items', function (Blueprint $table): void {
            $table->string('size', 100)->nullable()->after('name');
            $table->decimal('actual_stock', 15, 2)->nullable()->after('current_stock');
        });
    }

    public function down(): void
    {
        Schema::table('atk_items', function (Blueprint $table): void {
            $table->dropColumn(['size', 'actual_stock']);
        });
    }
};
