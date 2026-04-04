<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\TyreOfferAvailabilityResolver;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Support\ProcurementWorkbenchData;
use App\Modules\Products\Models\TyreAccountOffer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class ProcurementWorkbench extends Page
{
    use HasSupplierNetworkAccess;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Procurement';

    protected static ?string $title = 'Procurement Checkout';

    protected static UnitEnum|string|null $navigationGroup = 'Suppliers';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'procurement-workbench';

    protected string $view = 'filament.pages.procurement-workbench';

    public array $currentAccountSummary = [];

    public array $supplierConnectionGroups = [];

    public array $supplierCatalogSections = [];

    public array $selectedQuantities = [];

    public array $checkoutSummary = [];

    public array $recentProcurementSignals = [];

    public array $latestSubmissionSummary = [];

    public string $activeView = 'search';

    public ?int $shipToWarehouseId = null;

    public string $purchaseOrderNumber = '';

    public ?int $searchSupplierId = null;

    public string $searchWidth = '';

    public string $searchHeight = '';

    public string $searchRimSize = '';

    public int $searchMinimumQty = 1;

    public array $shipToWarehouseOptions = [];

    public array $searchResults = [];

    public array $orderHistory = [];

    public array $pendingOrderHistory = [];

    public array $expandedResultKeys = [];

    public function mount(): void
    {
        $requestedSupplierId = request()->integer('supplier');

        if ($requestedSupplierId > 0) {
            $this->searchSupplierId = $requestedSupplierId;
        }

        $requestedView = request()->string('view')->toString();

        if (in_array($requestedView, ['search', 'cart', 'orders', 'pending'], true)) {
            $this->activeView = $requestedView;
        }

        $this->refreshWorkbench();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('view_quotes') ?? false)
            && static::currentRetailAccountForNavigation() instanceof Account;
    }

    public function submitGroupedProcurement(SubmitGroupedProcurementAction $submitAction): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();
        $actor = Auth::user();

        if (! $currentAccount instanceof Account || ! $actor instanceof User) {
            Notification::make()
                ->title('Retail account required')
                ->body('Select an active retailer account before submitting grouped procurement.')
                ->warning()
                ->send();

            return;
        }

        $lineItems = $this->buildWorkbenchLineItems($this->supplierCatalogSections);

        if ($lineItems === []) {
            Notification::make()
                ->title('Select at least one supplier line')
                ->body('Add quantities to one or more supplier offers before placing the grouped procurement checkout.')
                ->warning()
                ->send();

            return;
        }

        try {
            $submissionNote = collect([
                'Submitted from Procurement Workbench',
                $this->purchaseOrderNumber !== '' ? 'PO '.$this->purchaseOrderNumber : null,
                $this->shipToWarehouseLabel() !== null ? 'Ship to '.$this->shipToWarehouseLabel() : null,
            ])->filter()->implode(' | ');

            $submission = $submitAction->execute(
                retailerAccount: $currentAccount,
                actor: $actor,
                lineItems: $lineItems,
                notes: $submissionNote,
            );
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Procurement submit failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->latestSubmissionSummary = [
            'submission_number' => $submission->submission_number,
            'request_count' => $submission->request_count,
            'supplier_count' => $submission->supplier_count,
            'submitted_at' => $submission->submitted_at?->toDateTimeString(),
        ];

        $this->refreshWorkbench();

        Notification::make()
            ->title('Grouped procurement submitted')
            ->body(sprintf(
                '%s created %d supplier request%s for %s.',
                $submission->submission_number,
                $submission->request_count,
                $submission->request_count === 1 ? '' : 's',
                $currentAccount->name
            ))
            ->success()
            ->send();
    }

    public function updatedSelectedQuantities($value, string $key): void
    {
        $offerId = (int) $key;
        $this->selectedQuantities[(string) $key] = min(
            max(0, (int) $value),
            $this->maxSelectableQuantityForOffer($offerId)
        );
        $this->rebuildWorkbenchViews();
    }

    public function incrementQuantity(int $offerId): void
    {
        $current = (int) ($this->selectedQuantities[(string) $offerId] ?? 0);
        $this->selectedQuantities[(string) $offerId] = min(
            $current + 1,
            $this->maxSelectableQuantityForOffer($offerId)
        );
        $this->rebuildWorkbenchViews();
    }

    public function decrementQuantity(int $offerId): void
    {
        $current = (int) ($this->selectedQuantities[(string) $offerId] ?? 0);
        $this->selectedQuantities[(string) $offerId] = max(0, $current - 1);
        $this->rebuildWorkbenchViews();
    }

    public function setActiveView(string $view): void
    {
        if (! in_array($view, ['search', 'cart', 'orders', 'pending'], true)) {
            return;
        }

        $this->activeView = $view;
    }

    public function applySearchFilters(): void
    {
        $this->searchMinimumQty = max(1, (int) $this->searchMinimumQty);
        $this->searchResults = $this->buildSearchResults();
        $this->activeView = 'search';
    }

    public function resetSearchFilters(): void
    {
        $this->searchSupplierId = null;
        $this->searchWidth = '';
        $this->searchHeight = '';
        $this->searchRimSize = '';
        $this->searchMinimumQty = 1;
        $this->searchResults = $this->buildSearchResults();
    }

    public function toggleResultExpansion(string $resultKey): void
    {
        if (in_array($resultKey, $this->expandedResultKeys, true)) {
            $this->expandedResultKeys = array_values(array_filter(
                $this->expandedResultKeys,
                fn (string $key): bool => $key !== $resultKey
            ));

            return;
        }

        $this->expandedResultKeys[] = $resultKey;
    }

    public function addRecommendedQuantity(int $offerId, ?int $recommendedQuantity = null): void
    {
        $current = (int) ($this->selectedQuantities[(string) $offerId] ?? 0);
        $this->selectedQuantities[(string) $offerId] = max(
            1,
            min(
                $current + max(1, (int) ($recommendedQuantity ?? 1)),
                $this->maxSelectableQuantityForOffer($offerId)
            )
        );
        $this->rebuildWorkbenchViews();
        $this->activeView = 'cart';
    }

    protected function refreshWorkbench(): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();
        $snapshot = ProcurementWorkbenchData::forAccount($currentAccount)->toArray();

        $this->currentAccountSummary = $snapshot['current_account_summary'];
        $this->supplierConnectionGroups = $snapshot['supplier_groups'] ?? [];
        $this->recentProcurementSignals = $snapshot['recent_procurement_signals'] ?? [];
        $this->shipToWarehouseOptions = $this->buildShipToWarehouseOptions();

        if ($this->shipToWarehouseId === null && $this->shipToWarehouseOptions !== []) {
            $this->shipToWarehouseId = (int) array_key_first($this->shipToWarehouseOptions);
        }

        $this->rebuildWorkbenchViews();
    }

    protected function buildSupplierCatalogSections(): array
    {
        $availabilityResolver = app(TyreOfferAvailabilityResolver::class);

        return collect($this->supplierConnectionGroups)
            ->filter(fn (array $group): bool => ($group['connection_status'] ?? null) === 'approved' && ! empty($group['supplier_id']))
            ->map(function (array $group) use ($availabilityResolver): array {
                $offers = TyreAccountOffer::query()
                    ->with(['tyreCatalogGroup', 'inventories.warehouse'])
                    ->where('account_id', (int) $group['supplier_id'])
                    ->whereHas('inventories', function ($query): void {
                        $query->where('quantity', '>', 0)
                            ->whereHas('warehouse', fn ($warehouseQuery) => $warehouseQuery->where('code', '!=', 'NON-STOCK'));
                    })
                    ->latest('id')
                    ->limit(8)
                    ->get();

                $rows = $offers
                    ->map(function (TyreAccountOffer $offer) use ($availabilityResolver, $group): array {
                        $catalogGroup = $offer->tyreCatalogGroup;
                        $warehouse = $offer->inventories
                            ->first(fn ($inventory) => $inventory->warehouse?->code !== 'NON-STOCK');
                        $availableQuantity = $availabilityResolver->offerCurrentQuantity($offer);
                        $unitPrice = (float) ($offer->wholesale_price_lvl1 ?? $offer->retail_price ?? 0);
                        $selectedQuantity = min(
                            max(0, (int) ($this->selectedQuantities[(string) $offer->id] ?? 0)),
                            max(0, $availableQuantity)
                        );

                        return [
                            'offer_id' => $offer->id,
                            'supplier_id' => (int) ($group['supplier_id'] ?? 0),
                            'supplier_name' => $group['supplier_name'] ?? 'Supplier',
                            'sku' => $offer->source_sku ?? ('TYR-' . $offer->id),
                            'brand_name' => $catalogGroup?->brand_name ?? 'Tyre',
                            'model_name' => $catalogGroup?->model_name ?? 'Offer',
                            'full_size' => $catalogGroup?->full_size ?? 'N/A',
                            'width' => (string) ($catalogGroup?->width ?? ''),
                            'height' => (string) ($catalogGroup?->height ?? ''),
                            'rim_size' => (string) ($catalogGroup?->rim_size ?? ''),
                            'load_index' => $catalogGroup?->load_index,
                            'speed_rating' => $catalogGroup?->speed_rating,
                            'runflat' => (bool) ($catalogGroup?->runflat ?? false),
                            'dot_year' => $catalogGroup?->dot_year ?? null,
                            'country' => $catalogGroup?->country ?? null,
                            'warehouse_id' => $warehouse?->warehouse_id ?? $warehouse?->id,
                            'warehouse_name' => $warehouse?->warehouse?->warehouse_name ?? $warehouse?->warehouse?->name ?? null,
                            'available_quantity' => $availableQuantity,
                            'unit_price' => round($unitPrice, 2),
                            'selected_quantity' => $selectedQuantity,
                            'line_total' => round($unitPrice * $selectedQuantity, 2),
                            'image_url' => $this->primaryImageForOffer($offer),
                            'summary' => trim(collect([
                                $catalogGroup?->tyre_type,
                                $catalogGroup?->runflat ? 'Run Flat' : null,
                                $catalogGroup?->speed_rating ? 'Speed ' . $catalogGroup->speed_rating : null,
                            ])->filter()->implode(' · ')),
                        ];
                    })
                    ->values();

                return [
                    'supplier_id' => (int) ($group['supplier_id'] ?? 0),
                    'supplier_name' => $group['supplier_name'] ?? 'Supplier',
                    'supplier_summary' => $group['summary'] ?? 'Approved supplier connection ready for procurement.',
                    'offer_count' => $rows->count(),
                    'selected_line_count' => $rows->where('selected_quantity', '>', 0)->count(),
                    'selected_quantity_total' => (int) $rows->sum('selected_quantity'),
                    'selected_subtotal' => round((float) $rows->sum('line_total'), 2),
                    'offers' => $rows->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $supplierSections
     * @return array<int, array<string, mixed>>
     */
    protected function buildWorkbenchLineItems(array $supplierSections): array
    {
        return collect($supplierSections)
            ->flatMap(function (array $section): array {
                return collect($section['offers'] ?? [])
                    ->filter(fn (array $offer): bool => (int) ($offer['selected_quantity'] ?? 0) > 0)
                    ->map(function (array $offer): array {
                        $quantity = max(1, (int) ($offer['selected_quantity'] ?? 0));
                        $productName = trim(($offer['brand_name'] ?? 'Tyre') . ' ' . ($offer['model_name'] ?? 'Offer'));

                        return [
                            'sku' => $offer['sku'] ?? null,
                            'product_name' => $productName,
                            'size' => $offer['full_size'] ?? null,
                            'quantity' => $quantity,
                            'supplier_id' => $offer['supplier_id'] ?? null,
                            'supplier_name' => $offer['supplier_name'] ?? null,
                            'unit_price' => (float) ($offer['unit_price'] ?? 0),
                            'warehouse_id' => $offer['warehouse_id'] ?? null,
                            'source' => 'Approved supplier offer',
                            'status' => 'Ready to quote',
                            'note' => $offer['warehouse_name']
                                ? 'Supplier warehouse: ' . $offer['warehouse_name']
                                : 'Approved supplier offer',
                            'line_total' => round(((float) ($offer['unit_price'] ?? 0)) * $quantity, 2),
                            'product_description' => $offer['summary'] ?? null,
                            'tyre_account_offer_id' => $offer['offer_id'] ?? null,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function buildCheckoutSummary(): array
    {
        $selectedOffers = collect($this->supplierCatalogSections)
            ->flatMap(fn (array $section): array => $section['offers'] ?? [])
            ->filter(fn (array $offer): bool => (int) ($offer['selected_quantity'] ?? 0) > 0)
            ->values();

        return [
            'approved_suppliers' => (int) ($this->currentAccountSummary['supplier_connections']['approved'] ?? 0),
            'selected_suppliers' => $selectedOffers->pluck('supplier_id')->filter()->unique()->count(),
            'selected_lines' => $selectedOffers->count(),
            'quantity_total' => (int) $selectedOffers->sum('selected_quantity'),
            'subtotal' => round((float) $selectedOffers->sum('line_total'), 2),
            'action_label' => $selectedOffers->isEmpty() ? 'Select items to place order' : 'Place Order',
            'supporting_note' => $this->currentAccountSlug()
                ? 'Submitting from ' . $this->currentAccountName() . ' will split the final checkout into one procurement request per supplier.'
                : 'Select a retail account to start procurement.',
        ];
    }

    protected function rebuildWorkbenchViews(): void
    {
        $this->supplierCatalogSections = $this->buildSupplierCatalogSections();
        $this->checkoutSummary = $this->buildCheckoutSummary();
        $this->searchResults = $this->buildSearchResults();
        $this->orderHistory = $this->buildOrderHistory();
        $this->pendingOrderHistory = $this->buildPendingOrderHistory();
    }

    protected function buildSearchResults(): array
    {
        $supplierFilter = $this->searchSupplierId;
        $widthFilter = trim($this->searchWidth);
        $heightFilter = trim($this->searchHeight);
        $rimFilter = trim($this->searchRimSize);
        $minimumQty = max(1, (int) $this->searchMinimumQty);

        return collect($this->supplierCatalogSections)
            ->flatMap(fn (array $section): array => $section['offers'] ?? [])
            ->filter(function (array $offer) use ($supplierFilter, $widthFilter, $heightFilter, $rimFilter, $minimumQty): bool {
                if ($supplierFilter !== null && (int) ($offer['supplier_id'] ?? 0) !== $supplierFilter) {
                    return false;
                }

                if ($widthFilter !== '' && (string) ($offer['width'] ?? '') !== $widthFilter) {
                    return false;
                }

                if ($heightFilter !== '' && (string) ($offer['height'] ?? '') !== $heightFilter) {
                    return false;
                }

                if ($rimFilter !== '' && (string) ($offer['rim_size'] ?? '') !== $rimFilter) {
                    return false;
                }

                return (int) ($offer['available_quantity'] ?? 0) >= $minimumQty;
            })
            ->groupBy(fn (array $offer): string => implode('|', [
                strtolower((string) ($offer['brand_name'] ?? '')),
                strtolower((string) ($offer['model_name'] ?? '')),
                strtolower((string) ($offer['full_size'] ?? '')),
                strtolower((string) ($offer['dot_year'] ?? '')),
            ]))
            ->map(function ($offers, string $resultKey): array {
                $rows = collect($offers)->sortBy('unit_price')->values();
                $first = $rows->first() ?? [];

                return [
                    'result_key' => $resultKey,
                    'brand_name' => $first['brand_name'] ?? 'Tyre',
                    'model_name' => $first['model_name'] ?? 'Offer',
                    'full_size' => $first['full_size'] ?? 'N/A',
                    'load_index' => $first['load_index'] ?? 'N/A',
                    'speed_rating' => $first['speed_rating'] ?? 'N/A',
                    'dot_year' => $first['dot_year'] ?? 'N/A',
                    'runflat_label' => ($first['runflat'] ?? false) ? 'Yes' : 'No',
                    'primary_image' => $first['image_url'] ?? null,
                    'offer_count' => $rows->count(),
                    'available_quantity_total' => (int) $rows->sum('available_quantity'),
                    'best_unit_price' => round((float) $rows->min('unit_price'), 2),
                    'supplier_rows' => $rows->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildOrderHistory(): array
    {
        return collect($this->recentProcurementSignals)
            ->sortByDesc(fn (array $signal): string => (string) ($signal['occurred_at'] ?? ''))
            ->values()
            ->all();
    }

    protected function buildPendingOrderHistory(): array
    {
        return collect($this->recentProcurementSignals)
            ->filter(function (array $signal): bool {
                $status = (string) ($signal['status'] ?? '');

                return ! in_array($status, ['fulfilled', 'cancelled'], true);
            })
            ->sortByDesc(fn (array $signal): string => (string) ($signal['occurred_at'] ?? ''))
            ->values()
            ->all();
    }

    protected function buildShipToWarehouseOptions(): array
    {
        return Warehouse::query()
            ->active()
            ->where('is_system', false)
            ->orderByDesc('is_primary')
            ->orderBy('warehouse_name')
            ->limit(20)
            ->pluck('warehouse_name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }

    protected function maxSelectableQuantityForOffer(int $offerId): int
    {
        $offer = collect($this->supplierCatalogSections)
            ->flatMap(fn (array $section): array => $section['offers'] ?? [])
            ->first(fn (array $row): bool => (int) ($row['offer_id'] ?? 0) === $offerId);

        return max(0, (int) ($offer['available_quantity'] ?? 0));
    }

    protected function shipToWarehouseLabel(): ?string
    {
        if ($this->shipToWarehouseId === null) {
            return null;
        }

        return $this->shipToWarehouseOptions[$this->shipToWarehouseId] ?? null;
    }

    protected function primaryImageForOffer(TyreAccountOffer $offer): ?string
    {
        $candidate = collect([
            $offer->product_image_1_url,
            $offer->product_image_2_url,
            $offer->product_image_3_url,
            $offer->brand_image_url,
        ])->first(fn (?string $value): bool => filled($value));

        return $candidate ?: null;
    }

    protected function currentAccountName(): string
    {
        return $this->currentAccountSummary['account']['name']
            ?? $this->currentAccountSummary['current_account']['name']
            ?? 'No active retail account';
    }

    protected function currentAccountSlug(): ?string
    {
        return $this->currentAccountSummary['account']['slug']
            ?? $this->currentAccountSummary['current_account']['slug']
            ?? null;
    }
}
