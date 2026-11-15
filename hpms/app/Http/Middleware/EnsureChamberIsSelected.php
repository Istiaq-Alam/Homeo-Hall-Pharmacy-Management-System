<?php

namespace App\Http\Middleware;

use App\Models\Chamber;
use App\Support\ChamberContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChamberIsSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(ChamberContext::class);
        $chamberId = $context->id();

        if ($user?->isAdmin()) {
            if ($chamberId === null) {
                return redirect()->route('admin.index')->with('error', 'Choose a chamber to manage before continuing.');
            }

            if (! Chamber::query()->whereKey($chamberId)->exists()) {
                $context->forget();

                return redirect()->route('admin.index')->with('error', 'That chamber no longer exists. Choose another chamber.');
            }
        }

        if ($user?->isStaff() && $chamberId === null) {
            abort(403, 'Your staff account is not assigned to a chamber.');
        }

        return $next($request);
    }
}
