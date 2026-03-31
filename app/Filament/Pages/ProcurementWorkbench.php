<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSupplierNetworkAccess;
use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Support\ProcurementWorkbenchData;
use App\Modules\Procurement\Support\ProcurementWorkflow;
use App\Modules\Procurement\Support\SupplierGroupedProcurementPlanner;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

class ProcurementWorkbench extends Page
{
    use HasSupplierNetworkAccess;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Procurement Workbench';

    protected static ?string $title = 'Procurement Workbench';

    protected static UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'procurement-workbench';

    protected string $view = 'filament.pages.procurement-workbench';

    public array $currentAccountSummary = [];

    public array $statusRail = [];

    public array $supplierGroups = [];

    public array $placeOrderCallout = [];

    public array $requestSummary = [];

    public array $plannedSubmission = [];

    public array $supplierConnectionGroups = [];

    public array $recentProcurementSignals = [];

    public array $latestSubmissionSummary = [];

    public function mount(): void
    {
        $this->refreshWorkbench();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
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

        $lineItems = $this->buildWorkbenchLineItems($this->supplierConnectionGroups);

        if ($lineItems === []) {
            Notification::make()
                ->title('No approved supplier groups')
                ->body('Grouped procurement only opens when the active retailer account has approved supplier connections.')
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

    protected function refreshWorkbench(): void
    {
        $currentAccount = $this->resolveCurrentRetailAccount();
        $snapshot = ProcurementWorkbenchData::forAccount($currentAccount)->toArray();

        $this->currentAccountSummary = $snapshot['current_account_summary'];
        $this->supplierConnectionGroups = $snapshot['supplier_groups'] ?? [];
        $this->plannedSubmission = SupplierGroupedProcurementPlanner::plan(
            $this->buildWorkbenchLineItems($this->supplierConnectionGroups)
        );
        $this->recentProcurementSignals = $snapshot['recent_procurement_signals'] ?? [];
        $this->statusRail = $this->buildStatusRail();
        $this->supplierGroups = $this->buildSupplierGroups();
        $this->placeOrderCallout = $this->buildPlaceOrderCallout();
        $this->requestSummary = $this->buildRequestSummary();
    }

    protected function buildStatusRail(): array
    {
        $descriptions = [
            'draft' => 'Build the request cart and confirm the tyre lines.',
            'submitted' => 'Send the procurement request forward to the supplier side.',
            'supplier_review' => 'Supplier reviews availability, pricing, and fulfilment fit.',
            'quoted' => 'Review the supplier quote before approval.',
            'approved' => 'Approved requests move toward invoice conversion.',
            'invoiced' => 'Invoice creation follows the quote approval path.',
            'stock_reserved' => 'Reserved stock is held against the selected supplier source.',
            'stock_deducted' => 'Stock deduction follows the current CRM method.',
            'fulfilled' => 'Procurement is completed and ready for downstream reporting.',
            'cancelled' => 'Cancelled requests add stock back to the selected warehouse.',
        ];

        return array_map(
            static function (array $stage, int $index) use ($descriptions): array {
                $value = $stage['value'];

                return [
                    'key' => $value,
                    'label' => $stage['label'],
                    'description' => $descriptions[$value] ?? 'Procurement stage placeholder.',
                    'state' => $index === 0 ? 'active' : 'pending',
                    'terminal' => $stage['terminal'],
                ];
            },
            ProcurementWorkflow::stages(),
            array_keys(ProcurementWorkflow::stages())
        );
    }

    protected function buildRequestSummary(): array
    {
        return [
            [
                'label' => 'Current retailer account',
                'value' => $this->currentAccountName(),
                'note' => 'George\'s grouped-by-supplier admin checkout rule stays scoped to the active retailer account.',
            ],
            [
                'label' => 'Approved supplier groups',
                'value' => $this->currentAccountSummary['supplier_connections']['approved'] ?? 0,
                'note' => 'Each approved supplier connection becomes its own grouped workbench section while pending links stay out of checkout.',
            ],
            [
                'label' => 'Live procurement requests',
                'value' => ($this->currentAccountSummary['procurement_request_counts']['total'] ?? 0) > 0
                    ? ($this->currentAccountSummary['procurement_request_counts']['total'] ?? 0)
                    : ($this->currentAccountSummary['document_counts']['total'] ?? 0),
                'note' => 'Persisted grouped supplier requests sit alongside the existing quote, order, and invoice history.',
            ],
        ];
    }

    protected function buildSupplierGroups(): array
    {
        return array_map(
            function (array $supplierOrder, int $index): array {
                return [
                    'supplier_name' => $supplierOrder['supplier_name'],
                    'supplier_reference' => 'Supplier order #' . ($index + 1),
                    'status' => 'Ready to submit',
                    'summary' => sprintf(
                        '%s keeps this supplier grouped until the unified submit action runs.',
                        $this->currentAccountName()
                    ),
                    'items' => array_map(
                        static fn (array $lineItem): array => [
                            'sku' => $lineItem['sku'] ?? '--',
                            'product_name' => $lineItem['product_name'] ?? 'Procurement line',
                            'size' => $lineItem['size'] ?? 'Pending tyre size',
                            'quantity' => $lineItem['quantity'] ?? 0,
                            'source' => $lineItem['source'] ?? 'Approved supplier connection',
                            'status' => $lineItem['status'] ?? 'Ready',
                            'note' => $lineItem['note'] ?? 'Grouped under the active retailer account before submit.',
                        ],
                        $supplierOrder['line_items'] ?? []
                    ),
                ];
            },
            $this->plannedSubmission['supplier_orders'] ?? [],
            array_keys($this->plannedSubmission['supplier_orders'] ?? [])
        );
    }

    protected function buildPlaceOrderCallout(): array
    {
        return [
            'title' => 'George\'s grouped-by-supplier admin checkout rule',
            'description' => 'Retailer admins work inside the active account, add requests from approved suppliers, and keep each supplier section separate until the shared submit action runs.',
            'highlights' => [
                'The current retail account stays in scope for the whole checkout flow.',
                'Each approved supplier keeps its own grouped workbench section.',
                'The backend creates separate supplier orders, quotes, and invoices per supplier.',
            ],
            'action_label' => $this->plannedSubmission['place_order_label'] ?? 'Place Order',
            'supporting_note' => $this->currentAccountSlug()
                ? 'Working in ' . $this->currentAccountName() . ' keeps the grouped checkout rule tied to the active retailer account.'
                : 'Select a retail account to load supplier groups.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $supplierGroups
     * @return array<int, array<string, mixed>>
     */
    protected function buildWorkbenchLineItems(array $supplierGroups): array
    {
        return collect($supplierGroups)
            ->filter(fn (array $group): bool => ($group['connection_status'] ?? null) === 'approved')
            ->map(function (array $group): array {
                $supplierName = $group['supplier_name'] ?? 'Supplier';
                $supplierSlug = $group['supplier_slug'] ?? $supplierName;

                return [
                    'sku' => sprintf(
                        'PROC-%s-%s',
                        Str::upper(Str::slug($this->currentAccountSlug() ?? $this->currentAccountName())),
                        Str::upper(Str::slug($supplierSlug))
                    ),
                    'product_name' => $this->currentAccountName() . ' procurement request',
                    'size' => 'Grouped for ' . $supplierName,
                    'quantity' => 1,
                    'supplier_id' => $group['supplier_id'] ?? null,
                    'supplier_name' => $supplierName,
                    'unit_price' => 0,
                    'source' => 'Approved supplier connection',
                    'status' => 'Ready to submit',
                    'note' => $group['summary'] ?? ('Approved supplier connection for ' . $this->currentAccountName() . '.'),
                ];
            })
            ->filter()
            ->values()
            ->all();
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
