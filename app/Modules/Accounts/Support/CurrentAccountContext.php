<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;

readonly class CurrentAccountContext
{
    /**
     * @param  Collection<int, Account>  $availableAccounts
     */
    public function __construct(
        public User $user,
        public ?Account $currentAccount,
        public Collection $availableAccounts,
        public ?string $selectionSource = null,
    ) {
    }

    public function hasCurrentAccount(): bool
    {
        return $this->currentAccount !== null;
    }

    public function toArray(): array
    {
        return [
            'selection_source' => $this->selectionSource,
            'current_account' => $this->currentAccount ? $this->accountPayload($this->currentAccount) : null,
            'available_accounts' => $this->availableAccounts
                ->map(fn (Account $account) => $this->accountPayload($account))
                ->values()
                ->all(),
        ];
    }

    private function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'account_type' => $account->account_type?->value,
            'retail_enabled' => $account->retail_enabled,
            'wholesale_enabled' => $account->wholesale_enabled,
            'status' => $account->status?->value,
        ];
    }
}
