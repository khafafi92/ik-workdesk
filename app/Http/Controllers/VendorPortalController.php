<?php

namespace App\Http\Controllers;

use App\Models\Scm\VendorRegistration;
use App\Services\Scm\Access;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorPortalController extends Controller
{
    public function show(string $token, int $step, VendorEnrollment $enrollment)
    {
        abort_unless(isset(VendorForm::TITLES[$step]), 404);
        $registration = $enrollment->resolve($token);
        $files = $enrollment->latestFiles($registration);

        return $this->privateResponse(response()->view('vendor-portal.form', ['registration' => $registration, 'step' => $step,
            'token' => $token, 'fields' => VendorForm::fields($step), 'files' => $files,
            'checklist' => $step === 5 ? $enrollment->submissionChecklist($registration, $files) : []]));
    }

    public function save(Request $request, string $token, int $step, VendorEnrollment $enrollment)
    {
        $registration = $enrollment->resolve($token);
        try {
            $input = $request->validate(['version' => 'required|integer|min:1', 'answers' => 'required|array', 'intent' => 'nullable|in:save,continue,submit']);
            $submit = ($input['intent'] ?? 'save') === 'submit';
            $enrollment->saveStep($registration, $input['version'], $step, $input['answers'], $submit);
        } catch (ValidationException $exception) {
            return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $step])->withErrors($exception->errors())->withInput($request->except('_token')));
        }

        $destination = $submit ? 5 : (($input['intent'] ?? '') === 'continue' ? min(5, $step + 1) : $step);

        return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $destination])
            ->with('status', $submit ? 'Form dan dokumen dikirim. SCM akan memeriksa kiriman Anda.' : 'Bagian '.$step.' tersimpan.'));
    }

    public function upload(Request $request, string $token, string $code, VendorEnrollment $enrollment)
    {
        $registration = $enrollment->resolve($token);
        $definition = VendorForm::documents()[$code] ?? abort(404);
        try {
            $input = $request->validate(['version' => 'required|integer|min:1', 'file' => 'required|file']);
            $enrollment->upload($registration, $input['version'], $code, $request->only(['number', 'issued_at', 'expires_at']), $input['file']);
        } catch (ValidationException $exception) {
            return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $definition[1]])->withErrors($exception->errors()));
        }

        return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $definition[1]])->with('status', $definition[0].' tersimpan.'));
    }

    public function download(string $token, int $file, VendorEnrollment $enrollment)
    {
        $registration = $enrollment->resolve($token);

        return $this->downloadFile($registration, $file);
    }

    public function review(Request $request, VendorRegistration $registration, VendorEnrollment $enrollment)
    {
        abort_unless(Access::manages($request->user()), 403);

        return $this->privateResponse(response()->view('vendor-portal.review', ['registration' => $registration,
            'files' => $enrollment->latestFiles($registration)]));
    }

    public function decide(Request $request, VendorRegistration $registration, VendorEnrollment $enrollment)
    {
        abort_unless(Access::manages($request->user()), 403);
        $data = $request->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:accept,correction,revoke', 'notes' => 'nullable|string|max:5000']);
        try {
            $enrollment->review($request->user(), $registration->id, $data['version'], $data['action'], $data['notes'] ?? null);
        } catch (ValidationException $exception) {
            return $this->privateResponse(redirect()->route('scm.vendor-review', $registration)->withErrors($exception->errors()));
        }

        return $this->privateResponse(redirect()->route('scm.vendor-review', $registration)->with('status', 'Hasil pemeriksaan tersimpan.'));
    }

    public function reviewDownload(Request $request, VendorRegistration $registration, int $file)
    {
        abort_unless(Access::manages($request->user()), 403);

        return $this->downloadFile($registration, $file);
    }

    private function downloadFile(VendorRegistration $registration, int $id)
    {
        $file = $registration->files()->findOrFail($id);
        abort_unless(preg_match('/^vendor-registration\/\d{4}\/\d{2}\/[a-f0-9-]{36}\.(pdf|jpg|jpeg|png)$/D', $file->path)
            && Storage::disk('scm')->exists($file->path), 404);

        return $this->privateResponse(Storage::disk('scm')->download($file->path, $file->original_name, ['Content-Type' => $file->mime]));
    }

    private function privateResponse($response)
    {
        foreach (['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY', 'X-Robots-Tag' => 'noindex, nofollow'] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
