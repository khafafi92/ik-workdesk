<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->dropUnique('outgoing_letters_document_number_unique');
            $table->index(['permit_company_id', 'document_number'], 'outgoing_letters_company_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->dropIndex('outgoing_letters_company_number_index');
            $table->unique('document_number');
        });
    }
};
