<?php

namespace App\Filament\Pages\Concerns;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use Throwable;

trait HasSupplierNetworkAccess
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_customers') ?? false)
            && static::currentRetailAccountForNavigation() instanceof Account;
    }

    protected static function currentRetailAccountForNavigation(): ?Account
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        try {
            $context = app(CurrentAccountResolver::class)->resolve(request(), $user);
        } catch (Throwable) {
            return null;
        }

        $account = $context->currentAccount;

        return $account?->isRetailEnabled() ? $account : null;
    }

    protected function resolveCurrentRetailAccount(): ?Account
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        try {
            $context = app(CurrentAccountResolver::class)->resolve(request(), $user);
        } catch (Throwable) {
            return null;
        }

        $account = $context->currentAccount;

        return $account?->isRetailEnabled() ? $account : null;
    }
}
