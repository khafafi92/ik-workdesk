<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_po_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company')->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('buyer_position')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_po_approval_policies')->exists()) {
            throw new RuntimeException('Pertahankan Master Approval PO yang sudah digunakan.');
        }
        Schema::dropIfExists('scm_po_approval_policies');
    }
};
