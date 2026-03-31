<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Accounts\Support\CurrentAccountContext;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Storefront\Support\StorefrontWorkspaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontWorkspaceController extends BaseWholesaleController
{
    public function __construct(
        private readonly CurrentAccountResolver $currentAccountResolver,
        private readonly StorefrontWorkspaceData $workspaceData,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $owner = $request->user();

        if (! $owner) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $context = $request->attributes->get('currentAccountContext');

        if (! $context instanceof CurrentAccountContext) {
            $context = $this->currentAccountResolver->resolve($request, $owner);
        }

        if (! $context->currentAccount) {
            return $this->success([
                'profile' => null,
                'addresses' => [],
                'orders' => [],
            ]);
        }

        return $this->success(
            $this->workspaceData->forAccount($context->currentAccount, $owner)
        );
    }
}
