<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Setting\Models\LanguageSetting;
use Symfony\Component\HttpFoundation\Response;

/** Shows the panel in the signed-in admin's own language, or in the site default from Settings → Language. */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user('admin')?->locale;

        app()->setLocale(LanguageSetting::isSupported($locale) ? $locale : LanguageSetting::defaultLocale());

        return $next($request);
    }
}
