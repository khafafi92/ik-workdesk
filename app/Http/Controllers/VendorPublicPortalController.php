<?php

namespace App\Http\Controllers;

use App\Models\Scm\VendorRegistration;
use App\Services\Scm\VendorPublicPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorPublicPortalController extends Controller
{
    public function index()
    {
        return view('vendor-portal.index');
    }

    public function register(Request $request, VendorPublicPortal $portal)
    {
        [$registration, $token, $code] = $portal->register($request->only(['company', 'name', 'provider_type']));

        return $this->openForm($request, $registration, $token, hash('sha256', $code), $code);
    }

    public function resume(Request $request, VendorPublicPortal $portal)
    {
        try {
            $data = $request->validate(['access_code' => 'required|string|max:100'], [], ['access_code' => 'Vendor ID']);
            [$registration, $token, $verifiedHash] = $portal->resume($data['access_code']);
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.index')->withErrors($exception->errors());
        }

        return $this->openForm($request, $registration, $token, $verifiedHash);
    }

    private function openForm(Request $request, VendorRegistration $registration, string $token, string $verifiedHash, ?string $issuedCode = null): RedirectResponse
    {
        $response = redirect()->route('vendor.portal', ['token' => $token, 'step' => 1]);
        if ($registration->company === 'APCA') {
            $request->session()->put('apca_vendor_access.'.$registration->portal_access_id, $verifiedHash);
        } elseif ($issuedCode !== null) {
            $response->with('vendor_access_code', $issuedCode);
        }

        return $response;
    }
}
