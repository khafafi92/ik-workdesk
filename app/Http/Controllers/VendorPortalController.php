<?php

namespace App\Http\Controllers;

use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ScmVendorRegistrations;
use App\Models\Scm\VendorRegistration;
use App\Services\Scm\Access;
use App\Services\Scm\IndonesiaAddress;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorPortalController extends Controller
{
    public function address(string $city, IndonesiaAddress $addresses)
    {
        $result = $addresses->city($city);
        abort_unless($result, 404);

        return $this->privateResponse(response()->json($result));
    }

    public function show(Request $request, string $token, int $step, VendorEnrollment $enrollment)
    {
        $registration = $enrollment->resolve($token);
        abort_unless(isset(VendorForm::titles($registration->context)[$step]), 404);
        $files = $enrollment->latestFiles($registration);
        $vendorId = null;
        $access = $registration->company === 'APCA' ? $registration->portalAccess : null;
        if ($access) {
            $verifiedHash = $request->session()->get('apca_vendor_access.'.$registration->portal_access_id);
            if (is_string($verifiedHash) && hash_equals($access->code_hash, $verifiedHash)) {
                $vendorId = $access->access_code;
            }
        }

        return $this->privateResponse(response()->view('vendor-portal.form', ['registration' => $registration, 'step' => $step,
            'token' => $token, 'vendorId' => $vendorId, 'fields' => VendorForm::fields($step, $registration->answers ?? [], $registration->context), 'files' => $files,
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
        $definition = VendorForm::documents($registration->context)[$code] ?? abort(404);
        try {
            $input = $request->validate(['version' => 'required|integer|min:1', 'file' => 'required|file']);
            $enrollment->upload($registration, $input['version'], $code, $request->only(['number', 'issued_at', 'expires_at', 'notes']), $input['file']);
        } catch (ValidationException $exception) {
            return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $definition[1]])->withErrors($exception->errors()));
        }

        return $this->privateResponse(redirect()->route('vendor.portal', ['token' => $token, 'step' => $definition[1]])->with('status', VendorForm::documentLabel($code, $registration->answers ?? [], $registration->context).' tersimpan.'));
    }

    public function download(string $token, int $file, VendorEnrollment $enrollment)
    {
        $registration = $enrollment->resolve($token);

        return $this->downloadFile($registration, $file);
    }

    public function review(Request $request, VendorRegistration $registration, VendorEnrollment $enrollment)
    {
        abort_unless(Access::manages($request->user()), 403);
        $reviewContext = $this->reviewContext($request);
        $masterPage = ScmProcurement::masterVendorPage($request->user());
        $masterVendorUrl = $masterPage && $registration->vendor && ! $registration->vendor->archived_at
            ? $masterPage::getUrl(['bagian' => 'vendors', 'vendor' => $registration->vendor->id])
            : null;

        return $this->privateResponse(response()->view('vendor-portal.review', ['registration' => $registration,
            'reviewContext' => $reviewContext,
            'directoryUrl' => $reviewContext ? ScmVendorRegistrations::getUrl(array_diff_key($reviewContext, ['dari' => true])) : null,
            'masterVendorUrl' => $masterVendorUrl,
            'masterListUrl' => $masterPage ? $masterPage::getUrl(['bagian' => 'vendors']) : null,
            'files' => $enrollment->latestFiles($registration)]));
    }

    public function decide(Request $request, VendorRegistration $registration, VendorEnrollment $enrollment)
    {
        abort_unless(Access::manages($request->user()), 403);
        $returnParameters = ['registration' => $registration, ...$this->reviewContext($request)];
        $data = $request->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:accept,correction,revoke', 'notes' => 'nullable|string|max:5000']);
        try {
            $enrollment->review($request->user(), $registration->id, $data['version'], $data['action'], $data['notes'] ?? null);
        } catch (ValidationException $exception) {
            return $this->privateResponse(redirect()->route('scm.vendor-review', $returnParameters)->withErrors($exception->errors()));
        }

        return $this->privateResponse(redirect()->route('scm.vendor-review', $returnParameters)->with('status', 'Hasil pemeriksaan tersimpan.'));
    }

    private function reviewContext(Request $request): array
    {
        return $request->query('dari') === 'data-vendor'
            ? ['dari' => 'data-vendor', ...ScmVendorRegistrations::directoryFilters($request->query())]
            : [];
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
