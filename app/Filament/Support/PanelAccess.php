<?php

namespace App\Filament\Support;

use App\Models\User;

final class PanelAccess
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::user()?->hasRole('super_admin') ?? false;
    }

    public static function canAccessGovernanceSurface(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canAccessOperationalSurface(string $permission): bool
    {
        $user = self::user();

        if (! $user instanceof User || self::isSuperAdmin()) {
            return false;
        }

        return $user->can($permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function canAccessOperationalSurfaceAny(array $permissions): bool
    {
        $user = self::user();

        if (! $user instanceof User || self::isSuperAdmin()) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
