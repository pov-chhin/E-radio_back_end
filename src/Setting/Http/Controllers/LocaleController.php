<?php

namespace Src\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Setting\Models\LanguageSetting;

/** The language switch in the account menu: any signed-in admin picks their own, no settings permission needed. */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(LanguageSetting::LOCALES))],
        ]);

        $request->user('admin')->forceFill($data)->save();

        return back();
    }
}
