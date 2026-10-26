<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->string('customer_po')->nullable();
            $table->string('job_order')->nullable();
        });
        Schema::table('scm_request_items', function (Blueprint $table): void {
            $table->string('brand')->nullable();
            $table->string('item_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_request_items', function (Blueprint $table): void {
            $table->dropColumn(['brand', 'item_reference']);
        });
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->dropColumn(['customer_po', 'job_order']);
        });
    }
};
