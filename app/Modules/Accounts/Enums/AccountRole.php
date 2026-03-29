<?php

namespace App\Modules\Accounts\Enums;

enum AccountRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::STAFF => 'Staff',
        };
    }
}
