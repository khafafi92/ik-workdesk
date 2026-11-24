<?php

namespace App\Http\Controllers;

use App\Filament\Pages\LanguageSettings;
use App\Http\Middleware\SetWorkdeskLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkdeskLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(SetWorkdeskLocale::LOCALES)],
        ]);

        $request->user()->forceFill(['preferred_locale' => $data['locale']])->save();

        return redirect()->to(LanguageSettings::getUrl(), 303)
            ->with('workdesk_language_saved', true);
    }
}
