<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('plate_number')->unique();
            $table->string('vehicle_type')->nullable();
            $table->string('brand_model')->nullable();
            $table->unsignedInteger('capacity')->default(5);
            $table->string('color')->nullable();
            $table->text('notes')->nullable();
            $table->time('available_from')->default('06:00:00');
            $table->time('available_until')->default('22:00:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('vehicle_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('title');
            $table->string('destination');
            $table->text('purpose')->nullable();
            $table->unsignedInteger('passengers_count')->default(1);
            $table->string('driver_name')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status')->default('confirmed');
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['vehicle_id', 'status', 'start_at', 'end_at'],
                'vehicle_booking_availability_idx'
            );
            $table->index(['requester_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_bookings');
        Schema::dropIfExists('vehicles');
    }
};
