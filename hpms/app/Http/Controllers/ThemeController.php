<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function index(): View
    {
        return view('themes.index', [
            'themes' => config('themes'),
            'current' => auth()->user()->activeTheme(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys(config('themes')))],
        ]);

        $user = $request->user();
        $user->theme = $data['theme'];
        $user->save();

        return redirect()
            ->route('themes.index')
            ->with('success', config("themes.{$data['theme']}.name").' theme applied.');
    }
}
