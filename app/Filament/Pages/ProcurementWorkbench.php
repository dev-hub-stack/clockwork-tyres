<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Models\User;
use App\Modules\Accounts\Models\Account;
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

    public function mount(): void
    {
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
            $submission = $submitAction->execute(
                retailerAccount: $currentAccount,
                actor: $actor,
                lineItems: $lineItems,
                notes: 'Submitted from Procurement Workbench',
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
        $this->selectedQuantities[(string) $key] = max(0, (int) $value);
        $this->supplierCatalogSections = $this->buildSupplierCatalogSections();
        $this->checkoutSummary = $this->buildCheckoutSummary();
    }

    public function incrementQuantity(int $offerId): void
    {
        $current = (int) ($this->selectedQuantities[(string) $offerId] ?? 0);
        $this->selectedQuantities[(string) $offerId] = $current + 1;
        $this->supplierCatalogSections = $this->buildSupplierCatalogSections();
        $this->checkoutSummary = $this->buildCheckoutSummary();
    }

    public function decrementQuantity(int $offerId): void
    {
        $current = (int) ($this->selectedQuantities[(string) $offerId] ?? 0);
        $this->selectedQuantities[(string) $offerId] = max(0, $current - 1);
        $this->supplierCatalogSections = $this->buildSupplierCatalogSections();
        $this->checkoutSummary = $this->buildCheckoutSummary();
    }

    protected function refreshWorkbench(): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();
        $snapshot = ProcurementWorkbenchData::forAccount($currentAccount)->toArray();

        $this->currentAccountSummary = $snapshot['current_account_summary'];
        $this->supplierConnectionGroups = $snapshot['supplier_groups'] ?? [];
        $this->recentProcurementSignals = $snapshot['recent_procurement_signals'] ?? [];
        $this->supplierCatalogSections = $this->buildSupplierCatalogSections();
        $this->checkoutSummary = $this->buildCheckoutSummary();
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

    protected function primaryImageForOffer(TyreAccountOffer $offer): ?string
    {
        $candidate = collect([
            $offer->product_image_1,
            $offer->product_image_2,
            $offer->product_image_3,
            $offer->brand_image,
        ])->first(fn (?string $value): bool => filled($value) && str_starts_with($value, 'http'));

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
