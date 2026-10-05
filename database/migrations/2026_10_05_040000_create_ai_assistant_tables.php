<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('openai');
            $table->string('model')->default('gpt-4o-mini');
            $table->text('api_key')->nullable();
            $table->unsignedBigInteger('monthly_token_limit')->default(0);
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->unsignedBigInteger('prompt_tokens')->nullable();
            $table->unsignedBigInteger('completion_tokens')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_settings');
    }
};
