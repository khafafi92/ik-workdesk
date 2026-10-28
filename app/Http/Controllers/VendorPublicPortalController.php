<?php

namespace App\Http\Controllers;

use App\Services\Scm\VendorPublicPortal;
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

        return redirect()->route('vendor.portal', ['token' => $token, 'step' => 1])->with('vendor_access_code', $code);
    }

    public function resume(Request $request, VendorPublicPortal $portal)
    {
        try {
            $data = $request->validate(['access_code' => 'required|string|max:100'], [], ['access_code' => 'Vendor ID']);
            [$registration, $token] = $portal->resume($data['access_code']);
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.index')->withErrors($exception->errors());
        }

        return redirect()->route('vendor.portal', ['token' => $token, 'step' => 1]);
    }
}
