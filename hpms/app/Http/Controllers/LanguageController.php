<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported', ['en' => 'EN'])))],
        ]);

        $locale = $validated['locale'];
        $user = $request->user();

        if ($user) {
            $user->forceFill(['locale' => $locale])->save();
        }

        $request->session()->put('locale', $locale);

        $target = $this->safePreviousUrl($request);

        return redirect()
            ->to($target)
            ->withCookie(cookie()->forever((string) config('localization.cookie', 'hpms_locale'), $locale));
    }

    /**
     * Return to the page the switch was submitted from, preserving its query
     * string, but never to an external host or back to the switch endpoint.
     */
    private function safePreviousUrl(Request $request): string
    {
        $previous = url()->previous();
        $host = parse_url($previous, PHP_URL_HOST);
        $path = parse_url($previous, PHP_URL_PATH) ?: '/dashboard';
        $query = parse_url($previous, PHP_URL_QUERY);

        if ($host !== null && ! hash_equals($request->getHost(), (string) $host)) {
            return route('dashboard');
        }

        if ($path === '/language' || $path === route('language.update', absolute: false)) {
            return route('dashboard');
        }

        return $query ? $path.'?'.$query : $path;
    }
}
