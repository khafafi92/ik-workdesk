<?php

use App\Services\Scm\VendorForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (VendorForm::documents() as $code => [$name, $step, $rule, $expiry]) {
            if (! DB::table('scm_document_types')->where('code', $code)->exists()) {
                DB::table('scm_document_types')->insert(['code' => $code, 'name' => in_array($code, ['KTP', 'NPWP']) ? $code : $name, 'description' => 'Form 005, bagian '.$step,
                    'has_expiry' => $expiry, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('scm_vendor_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('scm_vendors')->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('company', 10);
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('vendor_version');
            $table->json('answers')->nullable();
            $table->json('context');
            $table->json('submitted_snapshot')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('scm_vendor_registration_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained('scm_vendor_registrations')->restrictOnDelete();
            $table->string('code');
            $table->string('number');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['registration_id', 'code']);
        });
        Schema::create('scm_vendor_registration_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained('scm_vendor_registrations')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_vendor_registration_events');
        Schema::dropIfExists('scm_vendor_registration_files');
        Schema::dropIfExists('scm_vendor_registrations');
    }
};
