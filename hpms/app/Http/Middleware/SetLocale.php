<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the locale saved for the signed-in user, falling back to the
     * session, the guest cookie, and finally the application default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($request->hasSession() && $request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $supported = array_keys((array) config('localization.supported', ['en']));
        $candidates = [];

        if ($request->user()?->locale) {
            $candidates[] = $request->user()->locale;
        }

        if ($request->hasSession()) {
            $candidates[] = $request->session()->get('locale');
        }

        $candidates[] = $request->cookie((string) config('localization.cookie', 'hpms_locale'));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return (string) config('app.locale', 'en');
    }
}
