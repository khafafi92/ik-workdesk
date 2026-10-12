<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_assistant_settings', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false);
            $table->unique('provider');
        });

        DB::table('ai_assistant_settings')->where('id', 1)->update(['is_default' => true]);

        Schema::table('ai_assistant_messages', function (Blueprint $table): void {
            $table->string('provider')->default('openai');
            $table->string('model')->nullable();
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_assistant_messages', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'created_at']);
            $table->dropColumn(['provider', 'model']);
        });

        Schema::table('ai_assistant_settings', function (Blueprint $table): void {
            $table->dropUnique(['provider']);
            $table->dropColumn('is_default');
        });
    }
};
