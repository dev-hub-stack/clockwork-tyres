<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Support\StorefrontTyreCatalogData;
use Illuminate\Http\Request;

class StorefrontTyreCatalogController extends BaseWholesaleController
{
    public function __construct(
        private readonly StorefrontTyreCatalogData $catalog,
    ) {
    }

    public function index(Request $request)
    {
        $account = $this->currentAccount($request);

        if (! $account instanceof Account) {
            return $this->error('Active account context is required.', code: 401);
        }

        return $this->success(
            $this->catalog->catalog(
                $account,
                $request->query('mode'),
                $this->catalogFilters($request)
            )
        );
    }

    public function show(Request $request, string $slug)
    {
        $account = $this->currentAccount($request);

        if (! $account instanceof Account) {
            return $this->error('Active account context is required.', code: 401);
        }

        $product = $this->catalog->product($account, $slug, $request->query('mode'));

        if (! is_array($product)) {
            return $this->error('Tyre product not found.', code: 404);
        }

        return $this->success([
            'product' => $product,
        ]);
    }

    private function currentAccount(Request $request): ?Account
    {
        $account = $request->attributes->get('currentAccount');

        return $account instanceof Account ? $account : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogFilters(Request $request): array
    {
        return collect($request->query())
            ->except([
                'mode',
                'category',
                'fitmentMode',
                'searchBySize',
                'search_by_size',
                'searchByVehicle',
                'search_by_vehicle',
            ])
            ->all();
    }
}
