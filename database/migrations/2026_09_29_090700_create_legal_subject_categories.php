<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_subject_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('legal_subject_category_id')
                ->nullable()
                ->constrained('legal_subject_categories')
                ->nullOnDelete()
                ->after('ticket_category_id');
        });

        $legalDepartmentIds = DB::table('departments')
            ->whereRaw('LOWER(code) = ?', ['legal'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%legal%'])
            ->pluck('id');

        if ($legalDepartmentIds->isEmpty()) {
            return;
        }

        DB::table('tickets')
            ->whereIn('handler_department_id', $legalDepartmentIds)
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->orderBy('id')
            ->each(function (object $ticket): void {
                $categoryId = DB::table('legal_subject_categories')
                    ->where('name', $ticket->subject)
                    ->value('id');

                if (! $categoryId) {
                    $categoryId = DB::table('legal_subject_categories')->insertGetId([
                        'name' => $ticket->subject,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('tickets')->where('id', $ticket->id)->update([
                    'legal_subject_category_id' => $categoryId,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('legal_subject_category_id');
        });

        Schema::dropIfExists('legal_subject_categories');
    }
};
