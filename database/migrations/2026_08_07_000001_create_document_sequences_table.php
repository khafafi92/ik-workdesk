<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('prefix')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        $sequences = collect([
            ...DB::table('tickets')->pluck('ticket_no'),
            ...DB::table('work_tasks')->pluck('task_no'),
            ...DB::table('work_task_findings')->pluck('finding_no'),
        ])
            ->filter(fn ($number): bool => is_string($number))
            ->groupBy(fn (string $number): string => substr($number, 0, -4))
            ->map(function ($numbers, string $prefix): array {
                $highestNumber = $numbers
                    ->map(fn (string $number): int => (int) substr($number, -4))
                    ->max();

                return [
                    'prefix' => $prefix,
                    'next_number' => $highestNumber + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if ($sequences !== []) {
            DB::table('document_sequences')->insert($sequences);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
