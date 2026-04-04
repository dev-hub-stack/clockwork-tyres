<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Models\Warehouse;

final class TyreImportWarehouseResolver
{
    public function resolve(Account $account): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            [
                'account_id' => $account->id,
                'code' => $this->codeFor($account),
            ],
            [
                'warehouse_name' => $this->nameFor($account),
                'status' => 1,
                'is_primary' => false,
                'is_system' => false,
                'notes' => 'Auto-created for tyre import inventory routing.',
            ],
        );
    }

    private function codeFor(Account $account): string
    {
        return 'TYRE-ACC-'.$account->id;
    }

    private function nameFor(Account $account): string
    {
        return trim($account->name.' Tyre Import');
    }
}
