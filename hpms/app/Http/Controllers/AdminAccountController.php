<?php

namespace App\Http\Controllers;

use App\Services\ChamberProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    public function store(Request $request, ChamberProvisioner $chambers): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'designation' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);

        $admin = $chambers->createWithAdmin($data);

        return back()->with('success', 'Admin account created for '.$admin->email.' with a new chamber. They can now sign in and edit their chamber details.');
    }
}
