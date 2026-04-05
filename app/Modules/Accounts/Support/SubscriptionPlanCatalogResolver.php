<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Models\SubscriptionPlanCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubscriptionPlanCatalogResolver
{
    public function for(string $accountMode, string $planCode): SubscriptionPlanCatalog
    {
        $catalog = SubscriptionPlanCatalog::query()
            ->where('account_mode', $accountMode)
            ->where('plan_code', $planCode)
            ->first();

        if ($catalog instanceof SubscriptionPlanCatalog) {
            return $catalog;
        }

        throw (new ModelNotFoundException())->setModel(SubscriptionPlanCatalog::class, [
            sprintf('%s:%s', $accountMode, $planCode),
        ]);
    }
}
