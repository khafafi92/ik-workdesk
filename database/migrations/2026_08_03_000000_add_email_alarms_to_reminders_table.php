<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->json('email_alarm_days')->nullable()->after('reminder_at');
        });

        Schema::create('reminder_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['reminder_id', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_email_deliveries');

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('email_alarm_days');
        });
    }
};
