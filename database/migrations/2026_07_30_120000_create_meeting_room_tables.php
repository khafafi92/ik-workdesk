<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->default(8);
            $table->text('description')->nullable();
            $table->time('available_from')->default('08:00:00');
            $table->time('available_until')->default('18:00:00');
            $table->boolean('has_display')->default(false);
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_video_conference')->default(false);
            $table->boolean('has_whiteboard')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('meeting_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_room_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('organizer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('meeting_type')->default('onsite');
            $table->string('meeting_link')->nullable();
            $table->text('external_guests')->nullable();
            $table->string('status')->default('confirmed');
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['meeting_room_id', 'status', 'start_at', 'end_at'],
                'meeting_booking_availability_idx'
            );
            $table->index(['organizer_id', 'start_at']);
        });

        Schema::create(
            'meeting_booking_participants',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('meeting_booking_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->string('attendance_status')
                    ->default('invited');
                $table->timestamps();

                $table->unique([
                    'meeting_booking_id',
                    'user_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_booking_participants');
        Schema::dropIfExists('meeting_bookings');
        Schema::dropIfExists('meeting_rooms');
    }
};
