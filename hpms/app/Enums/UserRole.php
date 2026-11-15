<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Owner => 'Chamber Owner',
            self::Staff => 'Staff',
        };
    }
}
