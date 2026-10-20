<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_device_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_device_id')->constrained('it_devices')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('from_snapshot');
            $table->json('to_snapshot');
            $table->json('actor_snapshot');
            $table->date('transferred_on');
            $table->text('notes');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['it_device_id', 'transferred_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_device_transfers');
    }
};
