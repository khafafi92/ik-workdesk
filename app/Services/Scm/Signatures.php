<?php

namespace App\Services\Scm;

use App\Models\Scm\Procurement;
use App\Models\Scm\UserSignature;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Signatures
{
    public function current(User $actor): ?UserSignature
    {
        return UserSignature::where('user_id', $actor->id)->latest('id')->first();
    }

    public function upload(User $actor, UploadedFile $file): UserSignature
    {
        abort_unless(Access::signs($actor), 403);
        Validation::make(['signatureFile' => $file], [
            'signatureFile' => 'required|file|mimes:png,jpg,jpeg|mimetypes:image/png,image/jpeg|max:2048|dimensions:max_width=4096,max_height=4096',
        ], ['mimes' => 'Gambar tanda tangan harus berupa PNG/JPG.', 'mimetypes' => 'Gambar tanda tangan harus berupa PNG/JPG yang valid.',
            'dimensions' => 'Gambar tanda tangan maksimal 4096 × 4096 piksel.'], ['signatureFile' => 'gambar tanda tangan'])->validate();
        $mime = $file->getMimeType();
        $path = 'signatures/'.Str::uuid().($mime === 'image/png' ? '.png' : '.jpg');
        Storage::disk('scm')->putFileAs('signatures', $file, basename($path));
        try {
            return DB::transaction(function () use ($actor, $path, $mime): UserSignature {
                $signature = UserSignature::create(['user_id' => $actor->id, 'path' => $path, 'mime' => $mime]);
                app(Workflow::class)->audit($actor, $signature, 'simpan_ttd_profil', ['signature_id' => $signature->id]);

                return $signature;
            });
        } catch (\Throwable $exception) {
            Storage::disk('scm')->delete($path);
            throw $exception;
        }
    }

    public function sign(User $actor, int $id, int $version): Procurement
    {
        abort_unless(Access::has($actor, 'scm.request'), 403);

        return DB::transaction(function () use ($actor, $id, $version): Procurement {
            $request = Procurement::lockForUpdate()->findOrFail($id);
            abort_unless((int) $request->requester_id === (int) $actor->id, 403);
            if ($request->status !== 'draft' || $request->version !== $version) {
                throw ValidationException::withMessages(['scm' => 'Hanya draft terbaru milik sendiri yang dapat ditandatangani.']);
            }
            $signature = $this->current($actor);
            if (! $signature || ! Storage::disk('scm')->exists($signature->path)) {
                throw ValidationException::withMessages(['scm' => 'Simpan gambar TTD di profil terlebih dahulu.']);
            }
            $request->update(['prepared_signature_id' => $signature->id, 'prepared_signed_at' => now(),
                'prepared_signed_name' => $actor->name, 'version' => $request->version + 1]);
            app(Workflow::class)->audit($actor, $request, 'tanda_tangan_prepared',
                ['signature_id' => $signature->id, 'name' => $actor->name, 'signed_at' => $request->prepared_signed_at->toIso8601String()], $id);

            return $request->fresh();
        }, 3);
    }
}
