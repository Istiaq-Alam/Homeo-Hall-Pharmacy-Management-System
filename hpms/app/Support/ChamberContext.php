<?php

namespace App\Support;

use App\Models\Chamber;
use Illuminate\Support\Facades\Auth;

class ChamberContext
{
    public function id(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($user->isAdmin()) {
            $activeChamberId = session('active_chamber_id');

            return filled($activeChamberId) ? (int) $activeChamberId : $user->chamber_id;
        }

        return $user->chamber_id;
    }

    public function chamber(): ?Chamber
    {
        $chamberId = $this->id();

        return $chamberId === null ? null : Chamber::query()->find($chamberId);
    }

    public function set(int $chamberId): void
    {
        session()->put('active_chamber_id', $chamberId);
    }

    public function forget(): void
    {
        session()->forget('active_chamber_id');
    }
}
