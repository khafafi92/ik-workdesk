<?php

namespace App\Services\Scm;

use App\Models\Scm\Vendor;
use App\Models\Scm\VendorPortalAccess;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorPublicPortal
{
    public function register(array $input): array
    {
        $data = Validation::make($input, ['company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'name' => 'required|string|min:2|max:255', 'provider_type' => 'exclude_if:company,APCA|required|in:barang,jasa,keduanya'])->validate();
        $secret = $this->newSecret();
        $code = $secret;
        [$record, $token] = DB::transaction(function () use ($data, $secret, $code): array {
            $access = VendorPortalAccess::create(['code_hash' => hash('sha256', $secret), 'access_code' => $code]);

            $context = ['provider_type' => $data['provider_type'] ?? null, 'is_construction' => false, 'amount_minor' => 0, 'duration_months' => null];
            if ($data['company'] === 'APCA') {
                $context['document_profile'] = ApcaVendorChecklist::PROFILE;
            }

            return $this->createDraft($access, $secret, $data['company'], $context,
                [1 => ['name' => $data['name'], 'country' => 'Indonesia', 'company_origin' => 'indonesia', 'document_basis' => 'company']]);
        });

        return [$record, $token, $code];
    }

    public function reissueAccessCode(User $actor, int $registrationId, int $version): void
    {
        abort_unless($actor->isSystemAdministrator(), 403);
        $candidate = VendorRegistration::findOrFail($registrationId);
        abort_unless($candidate->portal_access_id, 404);
        DB::transaction(function () use ($actor, $candidate, $version): void {
            $access = VendorPortalAccess::lockForUpdate()->findOrFail($candidate->portal_access_id);
            $record = $access->registrations()->latest('id')->lockForUpdate()->firstOrFail();
            if ($record->id !== $candidate->id || $record->version !== $version) {
                throw ValidationException::withMessages(['vendorAccess' => 'Data vendor sudah berubah. Muat ulang daftar sebelum menerbitkan Vendor ID pengganti.']);
            }
            if ($access->revoked_at || $record->revoked_at) {
                throw ValidationException::withMessages(['vendorAccess' => 'Akses vendor sudah dicabut. Vendor ID pengganti tidak dapat diterbitkan.']);
            }
            $secret = $this->newSecret();
            $access->update(['code_hash' => hash('sha256', $secret), 'access_code' => $secret]);
            if ($access->vendor_id) {
                $vendor = Vendor::lockForUpdate()->findOrFail($access->vendor_id);
                $vendor->update(['code' => $secret, 'version' => $vendor->version + 1, 'updated_by' => $actor->id]);
            }
            foreach ($access->registrations()->lockForUpdate()->get() as $form) {
                $form->update(['token_hash' => hash('sha256', random_bytes(32)), 'version' => $form->version + 1]);
            }
            $record->refresh()->update(['token_hash' => hash('sha256', $this->token($secret, $record->id))]);
            DB::table('scm_vendor_registration_events')->insert(['registration_id' => $record->id, 'actor_id' => $actor->id,
                'action' => 'portal_access_reissued', 'details' => json_encode(['previous_form_tokens_invalidated' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function resume(string $code): array
    {
        $secret = strtoupper(preg_replace('/[\s-]/', '', trim($code)));
        $legacy = str_starts_with($secret, 'VND') && strlen($secret) === 35;
        if ($legacy) {
            $secret = substr($secret, 3);
        }
        $this->reject(! preg_match($legacy ? '/^[A-F0-9]{32}$/D' : '/^[A-Z0-9]{8}$/D', $secret));
        $createdPaths = [];
        try {
            return DB::transaction(function () use ($secret, &$createdPaths): array {
                $access = VendorPortalAccess::where('code_hash', hash('sha256', $secret))->lockForUpdate()->first();
                $this->reject(! $access || $access->revoked_at !== null);
                $record = $access->registrations()->latest('id')->lockForUpdate()->first();
                $this->reject(! $record || $record->revoked_at !== null);
                if ($record->status === 'accepted') {
                    $answers = $record->answers;
                    if ($access->vendor) {
                        $answers[1] = [...($answers[1] ?? []), ...$access->vendor->only(['name', 'address', 'phone', 'email', 'pic_name', 'pic_email', 'pic_phone', 'pic_position'])];
                    }
                    unset($answers[5]);
                    [$draft, $token] = $this->createDraft($access, $secret, $record->company, $record->context, $answers);
                    foreach (app(VendorEnrollment::class)->latestFiles($record) as $file) {
                        if (! Storage::disk('scm')->exists($file->path)) {
                            continue;
                        }
                        $path = 'vendor-registration/'.now()->format('Y/m').'/'.Str::uuid().'.'.pathinfo($file->path, PATHINFO_EXTENSION);
                        $createdPaths[] = $path;
                        if (! Storage::disk('scm')->copy($file->path, $path)) {
                            throw ValidationException::withMessages(['access_code' => 'Berkas sebelumnya belum dapat disiapkan. Coba kembali.']);
                        }
                        $draft->files()->create([...$file->only(['code', 'number', 'issued_at', 'expires_at', 'original_name', 'mime', 'size', 'notes']), 'path' => $path]);
                    }

                    return [$draft, $token, $access->code_hash];
                }
                $record->expires_at = now()->addDays(30);
                $record->save();

                return [$record, $this->token($secret, $record->id), $access->code_hash];
            });
        } catch (\Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    private function createDraft(VendorPortalAccess $access, string $secret, string $company, array $context, array $answers): array
    {
        $record = $access->registrations()->create(['vendor_id' => $access->vendor_id, 'vendor_version' => $access->vendor?->version,
            'status' => 'draft', 'version' => 1,
            'invited_by' => null, 'company' => $company, 'context' => $context, 'answers' => $answers,
            'token_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now()->addDays(30)]);
        $token = $this->token($secret, $record->id);
        $record->token_hash = hash('sha256', $token);
        $record->save();
        DB::table('scm_vendor_registration_events')->insert(['registration_id' => $record->id, 'actor_id' => null,
            'action' => $access->vendor_id ? 'public_update_started' : 'public_registration_started', 'details' => '{}', 'created_at' => now()]);

        return [$record, $token];
    }

    private function newSecret(): string
    {
        do {
            $secret = '';
            for ($i = 0; $i < 8; $i++) {
                $secret .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (VendorPortalAccess::where('code_hash', hash('sha256', $secret))->exists() || Vendor::where('code', $secret)->exists());

        return $secret;
    }

    private function token(string $secret, int $registrationId): string
    {
        return hash_hmac('sha256', 'vendor-portal:'.$registrationId.':'.$secret, config('app.key'));
    }

    private function reject(bool $condition): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['access_code' => 'Vendor ID tidak valid atau akses tidak tersedia. Periksa ID atau hubungi SCM.']);
        }
    }
}
