<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->string('need_type')->default('HO')->index();
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
        });

        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->foreignId('source_atk_request_id')->nullable()->unique()->constrained('atk_requests')->restrictOnDelete();
            $table->json('source_atk_quantities')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_atk_request_id');
            $table->dropColumn('source_atk_quantities');
        });

        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_numbering_id');
            $table->dropColumn('need_type');
        });
    }
};
