<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\User;
use App\Support\ChamberContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminConsoleController extends Controller
{
    public function index(): View
    {
        $chambers = Chamber::query()
            ->with(['users' => fn ($query) => $query->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get();
        $users = User::query()->with('chamber')->orderBy('name')->get();
        $activeChamber = app(ChamberContext::class)->chamber();

        return view('admin.index', compact('chambers', 'users', 'activeChamber'));
    }

    public function activate(Request $request, Chamber $chamber): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        app(ChamberContext::class)->set($chamber->id);

        return redirect()->route('dashboard')->with('success', 'Now managing '.$chamber->name.'.');
    }
}
