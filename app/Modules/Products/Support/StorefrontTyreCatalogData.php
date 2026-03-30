<?php

namespace App\Modules\Products\Support;

use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StorefrontTyreCatalogData
{
    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function catalog(Account $account, ?string $requestedMode = null): array
    {
        $mode = $this->resolveMode($account, $requestedMode);
        $context = $this->visibilityContext($account, $mode);

        $items = $this->catalogQuery($account, $context)
            ->get()
            ->map(fn (TyreCatalogGroup $group) => $this->mapCatalogItem($group, $account, $context))
            ->filter()
            ->sort(function (array $left, array $right): int {
                $leftRank = $left['availability']['origin'] === 'own' ? 0 : 1;
                $rightRank = $right['availability']['origin'] === 'own' ? 0 : 1;

                if ($leftRank !== $rightRank) {
                    return $leftRank <=> $rightRank;
                }

                return [$left['brand'], $left['model'], $left['size']]
                    <=> [$right['brand'], $right['model'], $right['size']];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'mode' => $mode,
                'category' => 'tyres',
                'item_count' => count($items),
                'account_slug' => $account->slug,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(Account $account, string $slug, ?string $requestedMode = null): ?array
    {
        $mode = $this->resolveMode($account, $requestedMode);
        $context = $this->visibilityContext($account, $mode);

        $group = $this->catalogQuery($account, $context)
            ->get()
            ->first(fn (TyreCatalogGroup $candidate) => $this->slugFor($candidate) === $slug);

        if (! $group instanceof TyreCatalogGroup) {
            return null;
        }

        $siblingGroups = $this->siblingQuery($group, $account, $context)->get();
        $siblings = $siblingGroups
            ->map(fn (TyreCatalogGroup $candidate) => [
                'group' => $candidate,
                'item' => $this->mapCatalogItem($candidate, $account, $context),
            ])
            ->filter(fn (array $entry) => is_array($entry['item']))
            ->values();

        $catalogItem = $this->mapCatalogItem($group, $account, $context);

        if (! is_array($catalogItem)) {
            return null;
        }

        $primaryOffer = $this->primaryOffer($group->offers, $account->id);

        return array_merge($catalogItem, [
            'description' => $this->descriptionFor($group),
            'gallery' => $this->galleryForOffers($group->offers),
            'fits' => [],
            'specifications' => $this->specificationsFor($group),
            'options' => $siblings
                ->map(function (array $entry): array {
                    /** @var TyreCatalogGroup $sibling */
                    $sibling = $entry['group'];
                    /** @var array<string, mixed> $item */
                    $item = $entry['item'];
                    return [
                        'sku' => $item['sku'],
                        'slug' => $item['slug'],
                        'size' => $item['size'],
                        'load_index' => $this->stringOrEmpty($sibling?->load_index),
                        'speed_rating' => $this->stringOrEmpty($sibling?->speed_rating),
                        'season' => $this->stringOrEmpty($sibling?->tyre_type),
                        'availability' => $item['availability'],
                        'mode_availability' => $item['mode_availability'],
                    ];
                })
                ->all(),
            'related_slugs' => $siblings
                ->map(fn (array $entry) => $entry['item'])
                ->filter(fn (array $item) => $item['slug'] !== $catalogItem['slug'])
                ->take(6)
                ->pluck('slug')
                ->values()
                ->all(),
            'supplier_summary' => [
                'supplier_offer_count' => $group->offers->where('account_id', '!=', $account->id)->count(),
                'supplier_count' => $group->offers->where('account_id', '!=', $account->id)->pluck('account_id')->unique()->count(),
                'primary_source_sku' => $primaryOffer?->source_sku,
            ],
        ]);
    }

    /**
     * @param  array{mode: string, visible_account_ids: array<int, int>}  $context
     */
    private function catalogQuery(Account $account, array $context): Builder
    {
        return TyreCatalogGroup::query()
            ->whereHas('offers', fn (Builder $query) => $query->whereIn('account_id', $context['visible_account_ids']))
            ->with([
                'offers' => fn ($query) => $query
                    ->whereIn('account_id', $context['visible_account_ids'])
                    ->orderByRaw('CASE WHEN account_id = ? THEN 0 ELSE 1 END', [$account->id])
                    ->orderBy('retail_price')
                    ->orderBy('source_sku'),
            ])
            ->orderByRaw('LOWER(brand_name)')
            ->orderByRaw('LOWER(model_name)')
            ->orderBy('full_size')
            ->orderBy('dot_year');
    }

    /**
     * @param  array{mode: string, visible_account_ids: array<int, int>}  $context
     */
    private function siblingQuery(TyreCatalogGroup $group, Account $account, array $context): Builder
    {
        return TyreCatalogGroup::query()
            ->where('brand_name', $group->brand_name)
            ->where('model_name', $group->model_name)
            ->whereHas('offers', fn (Builder $query) => $query->whereIn('account_id', $context['visible_account_ids']))
            ->with([
                'offers' => fn ($query) => $query
                    ->whereIn('account_id', $context['visible_account_ids'])
                    ->orderByRaw('CASE WHEN account_id = ? THEN 0 ELSE 1 END', [$account->id])
                    ->orderBy('retail_price')
                    ->orderBy('source_sku'),
            ])
            ->orderBy('full_size')
            ->orderBy('dot_year');
    }

    private function resolveMode(Account $account, ?string $requestedMode): string
    {
        if ($requestedMode === 'supplier-preview') {
            return $account->supportsWholesalePortal() ? 'supplier-preview' : 'retail-store';
        }

        if ($requestedMode === 'retail-store') {
            return $account->supportsRetailStorefront() ? 'retail-store' : 'supplier-preview';
        }

        if ($account->supportsRetailStorefront()) {
            return 'retail-store';
        }

        return 'supplier-preview';
    }

    /**
     * @return array{mode: string, visible_account_ids: array<int, int>}
     */
    private function visibilityContext(Account $account, string $mode): array
    {
        if ($mode === 'supplier-preview') {
            return [
                'mode' => $mode,
                'visible_account_ids' => [$account->id],
            ];
        }

        $supplierAccountIds = $account->approvedSupplierConnections()
            ->pluck('supplier_account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'mode' => $mode,
            'visible_account_ids' => array_values(array_unique([$account->id, ...$supplierAccountIds])),
        ];
    }

    /**
     * @param  array{mode: string, visible_account_ids: array<int, int>}  $context
     * @return array<string, mixed>|null
     */
    private function mapCatalogItem(TyreCatalogGroup $group, Account $account, array $context): ?array
    {
        [$ownOffers, $supplierOffers] = $this->splitOffers($group->offers, $account->id);

        if ($context['mode'] === 'supplier-preview') {
            $supplierOffers = collect();
        }

        if ($ownOffers->isEmpty() && $supplierOffers->isEmpty()) {
            return null;
        }

        $availability = $this->availabilityPayload($ownOffers, $supplierOffers);
        $primaryOffer = $this->primaryOffer($group->offers, $account->id);
        $price = $this->priceForOffers($ownOffers, $supplierOffers);

        return [
            'group_id' => $group->id,
            'sku' => $this->syntheticSku($group),
            'slug' => $this->slugFor($group),
            'brand' => $group->brand_name,
            'model' => $group->model_name,
            'subtitle' => $this->subtitleFor($group),
            'category' => 'tyres',
            'size' => $group->full_size,
            'price' => $price,
            'compare_at_price' => null,
            'image' => $this->primaryImage($primaryOffer),
            'availability' => $availability,
            'mode_availability' => [
                'retail_store' => $account->supportsRetailStorefront(),
                'supplier_preview' => $account->supportsWholesalePortal() && $ownOffers->isNotEmpty(),
            ],
            'featured' => $ownOffers->isNotEmpty(),
        ];
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $offers
     * @return array{0: Collection<int, TyreAccountOffer>, 1: Collection<int, TyreAccountOffer>}
     */
    private function splitOffers(Collection $offers, int $ownAccountId): array
    {
        return [
            $offers->filter(fn (TyreAccountOffer $offer) => $offer->account_id === $ownAccountId)->values(),
            $offers->filter(fn (TyreAccountOffer $offer) => $offer->account_id !== $ownAccountId)->values(),
        ];
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $ownOffers
     * @param  Collection<int, TyreAccountOffer>  $supplierOffers
     * @return array{origin: string, label: string, quantity: int, show_quantity: bool, supplier_count: int}
     */
    private function availabilityPayload(Collection $ownOffers, Collection $supplierOffers): array
    {
        if ($ownOffers->isNotEmpty()) {
            return [
                'origin' => 'own',
                'label' => 'in stock',
                'quantity' => 0,
                'show_quantity' => false,
                'supplier_count' => $supplierOffers->pluck('account_id')->unique()->count(),
            ];
        }

        return [
            'origin' => 'supplier',
            'label' => 'available',
            'quantity' => 0,
            'show_quantity' => false,
            'supplier_count' => $supplierOffers->pluck('account_id')->unique()->count(),
        ];
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $ownOffers
     * @param  Collection<int, TyreAccountOffer>  $supplierOffers
     */
    private function priceForOffers(Collection $ownOffers, Collection $supplierOffers): float
    {
        $offer = $ownOffers->first()
            ?? $supplierOffers
                ->filter(fn (TyreAccountOffer $candidate) => $candidate->retail_price !== null)
                ->sortBy('retail_price')
                ->first()
            ?? $supplierOffers->first();

        return (float) ($offer?->retail_price ?? 0);
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $offers
     */
    private function primaryOffer(Collection $offers, int $ownAccountId): ?TyreAccountOffer
    {
        return $offers
            ->sortBy(fn (TyreAccountOffer $offer) => $offer->account_id === $ownAccountId ? 0 : 1)
            ->first();
    }

    private function primaryImage(?TyreAccountOffer $offer): ?string
    {
        if (! $offer instanceof TyreAccountOffer) {
            return null;
        }

        foreach ([
            $offer->product_image_1,
            $offer->product_image_2,
            $offer->product_image_3,
            $offer->brand_image,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TyreAccountOffer>  $offers
     * @return array<int, string>
     */
    private function galleryForOffers(Collection $offers): array
    {
        return $offers
            ->flatMap(function (TyreAccountOffer $offer): array {
                return array_values(array_filter([
                    $offer->product_image_1,
                    $offer->product_image_2,
                    $offer->product_image_3,
                    $offer->brand_image,
                ], fn ($candidate) => is_string($candidate) && trim($candidate) !== ''));
            })
            ->map(fn ($path) => trim((string) $path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function specificationsFor(TyreCatalogGroup $group): array
    {
        return collect([
            ['label' => 'Size', 'value' => $group->full_size],
            ['label' => 'Load Index', 'value' => $this->stringOrDash($group->load_index)],
            ['label' => 'Speed Rating', 'value' => $this->stringOrDash($group->speed_rating)],
            ['label' => 'DOT Year', 'value' => $this->stringOrDash($group->dot_year)],
            ['label' => 'Type', 'value' => $this->stringOrDash($group->tyre_type)],
            ['label' => 'Country', 'value' => $this->stringOrDash($group->country)],
            ['label' => 'Runflat', 'value' => $this->booleanLabel($group->runflat)],
            ['label' => 'RFID', 'value' => $this->booleanLabel($group->rfid)],
            ['label' => 'Sidewall', 'value' => $this->stringOrDash($group->sidewall)],
            ['label' => 'Warranty', 'value' => $this->stringOrDash($group->warranty)],
        ])->all();
    }

    private function subtitleFor(TyreCatalogGroup $group): string
    {
        $parts = array_values(array_filter([
            $group->tyre_type ? "{$group->tyre_type} tyre" : null,
            $group->country ?: null,
            $group->dot_year ? "DOT {$group->dot_year}" : null,
        ]));

        return $parts ? implode(' • ', $parts) : 'Clockwork tyre catalogue';
    }

    private function descriptionFor(TyreCatalogGroup $group): string
    {
        return trim(sprintf(
            '%s %s %s is surfaced through the new Clockwork Tyres catalogue contract, with merged supplier support and future inventory wiring ready.',
            $group->brand_name,
            $group->model_name,
            $group->full_size
        ));
    }

    private function syntheticSku(TyreCatalogGroup $group): string
    {
        return 'TYR-GRP-'.str_pad((string) $group->id, 6, '0', STR_PAD_LEFT);
    }

    private function slugFor(TyreCatalogGroup $group): string
    {
        $slug = Str::slug(implode(' ', array_filter([
            $group->brand_name,
            $group->model_name,
            $this->slugSize($group->full_size),
            $group->dot_year,
        ])));

        return $slug !== '' ? $slug : 'tyre-group-'.$group->id;
    }

    private function slugSize(string $size): string
    {
        $normalized = strtolower(trim($size));
        $normalized = str_replace('/', '-', $normalized);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/-r/', 'r', $normalized) ?? $normalized;

        return $normalized;
    }

    private function booleanLabel(?bool $value): string
    {
        if ($value === null) {
            return '-';
        }

        return $value ? 'Yes' : 'No';
    }

    private function stringOrDash(?string $value): string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : '-';
    }

    private function stringOrEmpty(?string $value): string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : '';
    }
}
