<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
