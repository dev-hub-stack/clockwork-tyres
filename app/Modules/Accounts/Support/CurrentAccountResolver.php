<?php

namespace App\Modules\Accounts\Support;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Models\Account;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CurrentAccountResolver
{
    private const SESSION_KEY = 'current_account_id';
    private const CACHE_KEY_PREFIX = 'current_account_id:';

    public function resolve(Request $request, User $user): CurrentAccountContext
    {
        $availableAccounts = $this->availableAccounts($user);

        $explicitAccount = $this->resolveExplicitAccount($request, $availableAccounts);

        if ($explicitAccount instanceof Account) {
            $this->rememberSelection($request, $explicitAccount);

            return new CurrentAccountContext(
                user: $user,
                currentAccount: $explicitAccount,
                availableAccounts: $availableAccounts,
                selectionSource: 'explicit',
            );
        }

        $storedAccount = $this->resolveStoredAccount($request, $user, $availableAccounts);

        if ($storedAccount instanceof Account) {
            return new CurrentAccountContext(
                user: $user,
                currentAccount: $storedAccount,
                availableAccounts: $availableAccounts,
                selectionSource: 'stored',
            );
        }

        $fallbackAccount = $availableAccounts->first();

        if ($fallbackAccount instanceof Account) {
            $this->rememberSelection($request, $fallbackAccount);
        }

        return new CurrentAccountContext(
            user: $user,
            currentAccount: $fallbackAccount,
            availableAccounts: $availableAccounts,
            selectionSource: $fallbackAccount ? 'fallback' : null,
        );
    }

    /**
     * @return Collection<int, Account>
     */
    public function availableAccounts(User $user): Collection
    {
        return $user->accounts()
            ->select('accounts.*')
            ->where('accounts.status', AccountStatus::ACTIVE->value)
            ->orderByRaw('CASE WHEN account_user.is_default = 1 THEN 0 ELSE 1 END')
            ->orderBy('accounts.name')
            ->get();
    }

    /**
     * @param  Collection<int, Account>  $availableAccounts
     */
    private function resolveExplicitAccount(Request $request, Collection $availableAccounts): ?Account
    {
        $requestedAccountId = $this->requestInteger($request, ['account_id', 'account']);
        if ($requestedAccountId !== null) {
            return $this->matchOrDeny($availableAccounts, 'id', $requestedAccountId, 'account_id');
        }

        $requestedAccountSlug = $this->requestString($request, ['account_slug', 'account']);
        if ($requestedAccountSlug !== null) {
            return $this->matchOrDeny($availableAccounts, 'slug', $requestedAccountSlug, 'account_slug');
        }

        $headerAccountId = $this->headerInteger($request, ['X-Account-Id', 'X-Clockwork-Account-Id']);
        if ($headerAccountId !== null) {
            return $this->matchOrDeny($availableAccounts, 'id', $headerAccountId, 'X-Account-Id');
        }

        $headerAccountSlug = $this->headerString($request, ['X-Account-Slug', 'X-Clockwork-Account-Slug']);
        if ($headerAccountSlug !== null) {
            return $this->matchOrDeny($availableAccounts, 'slug', $headerAccountSlug, 'X-Account-Slug');
        }

        return null;
    }

    /**
     * @param  Collection<int, Account>  $availableAccounts
     */
    private function resolveStoredAccount(Request $request, User $user, Collection $availableAccounts): ?Account
    {
        $cachedAccountId = Cache::get($this->cacheKeyForUser($user->id));
        if (is_numeric($cachedAccountId)) {
            $cachedAccount = $availableAccounts->firstWhere('id', (int) $cachedAccountId);

            if ($cachedAccount instanceof Account) {
                return $cachedAccount;
            }
        }

        if (! $request->hasSession()) {
            return null;
        }

        $storedAccountId = $request->session()->get(self::SESSION_KEY);
        if (is_numeric($storedAccountId)) {
            return $availableAccounts->firstWhere('id', (int) $storedAccountId);
        }

        return null;
    }

    private function rememberSelection(Request $request, Account $account): void
    {
        if ($request->user() !== null) {
            Cache::put($this->cacheKeyForUser($request->user()->id), $account->id, now()->addDays(30));
        }

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $account->id);
        }
    }

    /**
     * @param  Collection<int, Account>  $availableAccounts
     */
    private function matchOrDeny(Collection $availableAccounts, string $key, int|string $value, string $selectionField): ?Account
    {
        $account = $availableAccounts->first(fn (Account $candidate) => (string) $candidate->getAttribute($key) === (string) $value);

        if ($account instanceof Account) {
            return $account;
        }

        throw new AuthorizationException("The selected {$selectionField} is not available to this user.");
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function requestInteger(Request $request, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function requestString(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function headerInteger(Request $request, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $request->header($key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function headerString(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->header($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function cacheKeyForUser(int $userId): string
    {
        return self::CACHE_KEY_PREFIX.$userId;
    }
}
