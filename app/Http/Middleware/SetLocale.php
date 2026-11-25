<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locales = config('agency.locales');
        $locale = $request->user()?->locale ?? $request->session()->get('locale');
        if (! in_array($locale, $locales, true)) {
            $locale = $request->getPreferredLanguage($locales) ?? config('app.locale');
        }
        App::setLocale($locale);

        return $next($request);
    }
}
