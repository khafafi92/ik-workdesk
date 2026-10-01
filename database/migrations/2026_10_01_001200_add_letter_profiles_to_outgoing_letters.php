<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->foreignId('permit_company_id')->nullable()->constrained('permit_companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('form_variant', 30)->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('document_numbering_templates', function (Blueprint $table): void {
            $table->foreignId('letter_profile_id')->nullable()->after('id')->constrained('letter_profiles')->nullOnDelete();
            $table->index('letter_profile_id');
        });

        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->foreignId('letter_profile_id')->nullable()->after('id')->constrained('letter_profiles')->nullOnDelete();
            $table->index('letter_profile_id');
        });

        $now = now();

        DB::table('permissions')->updateOrInsert(['code' => 'letters.profiles'], [
            'name' => 'Manage Letter Profiles',
            'module' => 'Surat',
            'description' => 'Manage outgoing-letter profiles and their default company or department.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('letter_profile_id');
        });

        Schema::table('document_numbering_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('letter_profile_id');
        });

        Schema::dropIfExists('letter_profiles');

        DB::table('permissions')->where('code', 'letters.profiles')->delete();
    }
};
