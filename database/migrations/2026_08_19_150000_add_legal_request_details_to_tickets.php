<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->text('legal_background')->nullable()->after('description');
            $table->text('legal_objective')->nullable()->after('legal_background');
            $table->text('legal_desired_scheme')->nullable()->after('legal_objective');
            $table->json('legal_document_types')->nullable()->after('legal_desired_scheme');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_background',
                'legal_objective',
                'legal_desired_scheme',
                'legal_document_types',
            ]);
        });
    }
};
