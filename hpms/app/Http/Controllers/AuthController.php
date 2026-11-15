<?php

namespace App\Http\Controllers;

use App\Models\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($request->string('email')->toString()).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.too_many_attempts', ['seconds' => localized_number(RateLimiter::availableIn($key))])]);
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            SecurityLog::record(SecurityLog::SIGN_IN_FAILED, 'Failed sign-in attempt for '.$credentials['email'].'.');
            throw ValidationException::withMessages(['email' => __('auth.invalid_credentials')]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        SecurityLog::record(SecurityLog::SIGN_IN, 'Signed in successfully.', Auth::user());

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        SecurityLog::record(SecurityLog::SIGN_OUT, 'Signed out.', $user);

        return redirect()->route('login');
    }
}
