<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['permit_company_id', 'id']);
        });

        Schema::create('global_chat_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('global_chat_message_id')
                ->constrained('global_chat_messages')
                ->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_chat_attachments');
        Schema::dropIfExists('global_chat_messages');
    }
};
