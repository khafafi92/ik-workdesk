<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_project_numbering', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        DB::table('scm_project_numbering')->insert(['code' => 'COMP-19E001', 'name' => 'LTRO', 'is_active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('scm_project_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_numbering_id')->constrained('scm_project_numbering')->restrictOnDelete();
            $table->string('kind', 2);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['project_numbering_id', 'kind', 'year'], 'scm_project_sequence_unique');
        });
        Schema::table('scm_request_numbers', function (Blueprint $table): void {
            $table->string('need_type', 10)->default('HO');
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
        });
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
        });
        DB::table('scm_requests')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                DB::table('scm_request_numbers')->insertOrIgnore(['number' => $request->number, 'kind' => $request->kind,
                    'company' => $request->company, 'need_type' => $request->need_type, 'requester_id' => $request->requester_id,
                    'request_id' => $request->id, 'created_at' => $request->created_at, 'updated_at' => $request->updated_at]);
                DB::table('scm_request_numbers')->where('request_id', $request->id)->update(['need_type' => $request->need_type]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('scm_request_numbers')->whereNotNull('project_numbering_id')->exists()) {
            throw new RuntimeException('Master nomor Project sudah digunakan dan harus dipertahankan.');
        }
        Schema::table('scm_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_numbering_id'));
        Schema::table('scm_request_numbers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_numbering_id');
            $table->dropColumn('need_type');
        });
        Schema::dropIfExists('scm_project_number_sequences');
        Schema::dropIfExists('scm_project_numbering');
    }
};
