<?php

namespace App\Filament\Pages;

use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Support\BusinessAccountInsights;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use App\Modules\Products\Models\TyreImportBatch;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public bool $isSuperAdmin = false;

    /** @var array<int, array<string, string|int>> */
    public array $headlineStats = [];

    /** @var array<int, array<string, string|int>> */
    public array $businessMixStats = [];

    /** @var array<int, array<string, string|int>> */
    public array $operationalStats = [];

    /** @var array<int, array<string, string|int>> */
    public array $commerceStats = [];

    /** @var array<int, array<string, string|null>> */
    public array $recentAccounts = [];

    /** @var array<int, array<string, string|null>> */
    public array $recentProcurementRequests = [];

    /** @var array<int, array<string, string|int|null>> */
    public array $importAlerts = [];

    /** @var array<string, string|bool>|null */
    public ?array $currentAccountSummary = null;

    /** @var array<int, array<string, string|int>> */
    public array $businessHeadlineStats = [];

    /** @var array<int, array<string, string|int>> */
    public array $businessCommerceStats = [];

    /** @var array<int, array<string, string|int>> */
    public array $businessOperationalStats = [];

    /** @var array<int, array<string, string|null>> */
    public array $recentBusinessOrders = [];

    /** @var array<int, array<string, string|null>> */
    public array $recentBusinessProcurementRequests = [];

    public bool $canSwitchBusinessAccount = false;

    public ?string $switchBusinessAccountUrl = null;

    public function mount(): void
    {
        $this->isSuperAdmin = PanelAccess::canAccessGovernanceSurface();

        if ($this->isSuperAdmin) {
            $this->loadPlatformDashboard();

            return;
        }

        $this->loadBusinessDashboard();
    }

    private function loadPlatformDashboard(): void
    {
        $this->headlineStats = [
            [
                'label' => 'Business Accounts',
                'value' => Account::query()->count(),
                'hint' => 'Platform tenants across retailer, supplier, and mixed accounts.',
            ],
            [
                'label' => 'Active Accounts',
                'value' => Account::query()->where('status', AccountStatus::ACTIVE->value)->count(),
                'hint' => 'Businesses currently able to use the platform.',
            ],
            [
                'label' => 'Premium Plans',
                'value' => Account::query()->where('base_subscription_plan', SubscriptionPlan::PREMIUM->value)->count(),
                'hint' => 'Paid base subscriptions on the platform.',
            ],
            [
                'label' => 'Reports Add-ons',
                'value' => Account::query()->where('reports_subscription_enabled', true)->count(),
                'hint' => 'Accounts with reporting enabled from governance.',
            ],
        ];

        $commerce = app(BusinessAccountInsights::class)->platform();

        $this->commerceStats = [
            [
                'label' => 'Products Listed',
                'value' => $commerce['products_listed'],
                'hint' => 'Tyre offers currently listed across all business accounts.',
            ],
            [
                'label' => 'Retail Transactions',
                'value' => $commerce['retail_transaction_count'],
                'hint' => 'Platform-wide retail invoices processed.',
            ],
            [
                'label' => 'Retail Transaction Value',
                'value' => 'AED '.number_format((float) $commerce['retail_transaction_value'], 2),
                'hint' => 'Retail transaction value across all business accounts.',
            ],
            [
                'label' => 'Wholesale Transactions',
                'value' => $commerce['wholesale_transaction_count'],
                'hint' => 'Procurement invoices processed for suppliers.',
            ],
            [
                'label' => 'Wholesale Transaction Value',
                'value' => 'AED '.number_format((float) $commerce['wholesale_transaction_value'], 2),
                'hint' => 'Wholesale invoice value across supplier procurement flows.',
            ],
        ];

        $this->businessMixStats = [
            [
                'label' => 'Retailers',
                'value' => Account::query()->where('account_type', AccountType::RETAILER->value)->count(),
                'hint' => 'Retail-only business accounts.',
            ],
            [
                'label' => 'Suppliers',
                'value' => Account::query()->where('account_type', AccountType::SUPPLIER->value)->count(),
                'hint' => 'Wholesale-only business accounts.',
            ],
            [
                'label' => 'Mixed Accounts',
                'value' => Account::query()->where('account_type', AccountType::BOTH->value)->count(),
                'hint' => 'Businesses operating in both modes.',
            ],
            [
                'label' => 'Suspended Accounts',
                'value' => Account::query()->where('status', AccountStatus::SUSPENDED->value)->count(),
                'hint' => 'Accounts paused by governance.',
            ],
        ];

        $openProcurementStages = [
            ProcurementWorkflowStage::DRAFT->value,
            ProcurementWorkflowStage::SUBMITTED->value,
            ProcurementWorkflowStage::SUPPLIER_REVIEW->value,
            ProcurementWorkflowStage::QUOTED->value,
            ProcurementWorkflowStage::APPROVED->value,
        ];

        $this->operationalStats = [
            [
                'label' => 'Approved Links',
                'value' => AccountConnection::query()->where('status', AccountConnectionStatus::APPROVED->value)->count(),
                'hint' => 'Active retailer-supplier relationships.',
            ],
            [
                'label' => 'Open Procurement Queue',
                'value' => ProcurementRequest::query()->whereIn('current_stage', $openProcurementStages)->count(),
                'hint' => 'Requests waiting for supplier action or approval.',
            ],
            [
                'label' => 'Invoiced Procurement',
                'value' => ProcurementRequest::query()->where('current_stage', ProcurementWorkflowStage::INVOICED->value)->count(),
                'hint' => 'Requests already moved into invoice flow.',
            ],
            [
                'label' => 'Import Alerts',
                'value' => TyreImportBatch::query()
                    ->where(function ($query): void {
                        $query->where('status', TyreImportBatchStatus::INVALID_HEADERS->value)
                            ->orWhere('invalid_rows', '>', 0)
                            ->orWhere('duplicate_rows', '>', 0);
                    })
                    ->count(),
                'hint' => 'Tyre imports needing review or correction.',
            ],
        ];

        $this->recentAccounts = Account::query()
            ->with('createdBy:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Account $account): array {
                return [
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'type' => $account->account_type?->label() ?? 'Retailer',
                    'status' => $account->status?->label() ?? 'Active',
                    'plan' => $account->base_subscription_plan === SubscriptionPlan::PREMIUM ? 'Premium' : 'Starter',
                    'created_by' => $account->createdBy?->name ?? 'System',
                    'created_at' => optional($account->created_at)?->diffForHumans(),
                    'url' => route('filament.admin.resources.accounts.view', ['record' => $account]),
                ];
            })
            ->all();

        $this->recentProcurementRequests = ProcurementRequest::query()
            ->with(['retailerAccount:id,name', 'supplierAccount:id,name'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (ProcurementRequest $request): array {
                return [
                    'request_number' => $request->request_number,
                    'retailer' => $request->retailerAccount?->name,
                    'supplier' => $request->supplierAccount?->name,
                    'stage' => $request->current_stage?->label() ?? 'Submitted',
                    'submitted_at' => optional($request->submitted_at ?? $request->created_at)?->diffForHumans(),
                    'url' => route('filament.admin.resources.procurement-requests.view', ['record' => $request]),
                ];
            })
            ->all();

        $this->importAlerts = TyreImportBatch::query()
            ->with('account:id,name')
            ->where(function ($query): void {
                $query->where('status', TyreImportBatchStatus::INVALID_HEADERS->value)
                    ->orWhere('invalid_rows', '>', 0)
                    ->orWhere('duplicate_rows', '>', 0);
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (TyreImportBatch $batch): array {
                return [
                    'file_name' => $batch->source_file_name,
                    'account' => $batch->account?->name ?? 'Unknown account',
                    'status' => $batch->status?->label() ?? 'Staged',
                    'invalid_rows' => $batch->invalid_rows,
                    'duplicate_rows' => $batch->duplicate_rows,
                    'uploaded_at' => optional($batch->created_at)?->diffForHumans(),
                ];
            })
            ->all();
    }

    private function loadBusinessDashboard(): void
    {
        $user = auth()->user();

        if (! $user || ! request()) {
            return;
        }

        $context = app(CurrentAccountResolver::class)->resolve(request(), $user);
        $currentAccount = $context->currentAccount;

        $this->canSwitchBusinessAccount = $context->availableAccounts->count() > 1;
        $this->switchBusinessAccountUrl = $this->canSwitchBusinessAccount
            ? route('filament.admin.pages.switch-business-account')
            : null;

        if (! $currentAccount instanceof Account) {
            return;
        }

        $insights = app(BusinessAccountInsights::class)->for($currentAccount);
        $openProcurementStages = [
            ProcurementWorkflowStage::DRAFT->value,
            ProcurementWorkflowStage::SUBMITTED->value,
            ProcurementWorkflowStage::SUPPLIER_REVIEW->value,
            ProcurementWorkflowStage::QUOTED->value,
            ProcurementWorkflowStage::APPROVED->value,
        ];

        $openProcurementCount = ProcurementRequest::query()
            ->where(function ($query) use ($currentAccount): void {
                $query->where('retailer_account_id', $currentAccount->id)
                    ->orWhere('supplier_account_id', $currentAccount->id);
            })
            ->whereIn('current_stage', $openProcurementStages)
            ->count();

        $importAlertCount = TyreImportBatch::query()
            ->where('account_id', $currentAccount->id)
            ->where(function ($query): void {
                $query->where('status', TyreImportBatchStatus::INVALID_HEADERS->value)
                    ->orWhere('invalid_rows', '>', 0)
                    ->orWhere('duplicate_rows', '>', 0);
            })
            ->count();

        $this->currentAccountSummary = [
            'name' => $currentAccount->name,
            'slug' => $currentAccount->slug,
            'type' => $currentAccount->account_type?->label() ?? 'Retailer',
            'status' => $currentAccount->status?->label() ?? 'Active',
            'plan' => $this->planLabelForAccount($currentAccount),
            'retail_enabled' => $currentAccount->supportsRetailStorefront(),
            'wholesale_enabled' => $currentAccount->supportsWholesalePortal(),
            'reports_enabled' => $currentAccount->reports_subscription_enabled,
        ];

        $this->businessHeadlineStats = [
            [
                'label' => 'Products Listed',
                'value' => $insights['products_listed'],
                'hint' => 'Tyre offers listed for the current business account.',
            ],
            [
                'label' => 'Warehouses',
                'value' => $insights['warehouses'],
                'hint' => 'Active warehouse locations under this business.',
            ],
            [
                'label' => 'Users',
                'value' => $insights['users'],
                'hint' => 'Platform users attached to this business account.',
            ],
            [
                'label' => 'Customers',
                'value' => $insights['customers'],
                'hint' => 'CRM customers scoped to the current business account.',
            ],
        ];

        $this->businessCommerceStats = [
            [
                'label' => 'Retail Transactions',
                'value' => $insights['retail_transaction_count'],
                'hint' => 'Retail invoices processed inside this business account.',
            ],
            [
                'label' => 'Retail Value',
                'value' => 'AED '.number_format((float) $insights['retail_transaction_value'], 2),
                'hint' => 'Retail invoice value for the current business account.',
            ],
            [
                'label' => 'Wholesale Transactions',
                'value' => $insights['wholesale_transaction_count'],
                'hint' => 'Procurement invoices linked to this business account.',
            ],
            [
                'label' => 'Wholesale Value',
                'value' => 'AED '.number_format((float) $insights['wholesale_transaction_value'], 2),
                'hint' => 'Wholesale invoice value for the current business account.',
            ],
        ];

        $this->businessOperationalStats = [
            [
                'label' => 'Connected Suppliers',
                'value' => $insights['connected_suppliers'],
                'hint' => 'Approved supplier links available to the current account.',
            ],
            [
                'label' => 'Connected Retailers',
                'value' => $insights['connected_retailers'],
                'hint' => 'Approved retailer links for supplier-side trade.',
            ],
            [
                'label' => 'Open Procurement',
                'value' => $openProcurementCount,
                'hint' => 'Requests still waiting on review, quote, or approval.',
            ],
            [
                'label' => 'Import Alerts',
                'value' => $importAlertCount,
                'hint' => 'Tyre imports for this business needing correction.',
            ],
        ];

        $this->recentBusinessOrders = Order::query()
            ->select('orders.*')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('customers.account_id', $currentAccount->id)
            ->where('orders.document_type', DocumentType::INVOICE->value)
            ->whereNull('orders.deleted_at')
            ->with('customer:id,first_name,last_name,business_name')
            ->latest('orders.created_at')
            ->limit(5)
            ->get()
            ->map(function (Order $order): array {
                return [
                    'order_number' => $order->order_number ?: 'INV-'.$order->id,
                    'customer' => $order->customer?->name ?? 'Unknown customer',
                    'status' => $order->order_status?->label() ?? 'Pending',
                    'total' => 'AED '.number_format((float) $order->total, 2),
                    'created_at' => optional($order->created_at)?->diffForHumans(),
                    'url' => route('filament.admin.resources.invoices.view', ['record' => $order]),
                ];
            })
            ->all();

        $this->recentBusinessProcurementRequests = ProcurementRequest::query()
            ->with(['retailerAccount:id,name', 'supplierAccount:id,name'])
            ->where(function ($query) use ($currentAccount): void {
                $query->where('retailer_account_id', $currentAccount->id)
                    ->orWhere('supplier_account_id', $currentAccount->id);
            })
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (ProcurementRequest $request): array {
                return [
                    'request_number' => $request->request_number,
                    'retailer' => $request->retailerAccount?->name,
                    'supplier' => $request->supplierAccount?->name,
                    'stage' => $request->current_stage?->label() ?? 'Submitted',
                    'submitted_at' => optional($request->submitted_at ?? $request->created_at)?->diffForHumans(),
                    'url' => route('filament.admin.resources.procurement-requests.view', ['record' => $request]),
                ];
            })
            ->all();

        $this->importAlerts = TyreImportBatch::query()
            ->where('account_id', $currentAccount->id)
            ->where(function ($query): void {
                $query->where('status', TyreImportBatchStatus::INVALID_HEADERS->value)
                    ->orWhere('invalid_rows', '>', 0)
                    ->orWhere('duplicate_rows', '>', 0);
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (TyreImportBatch $batch): array {
                return [
                    'file_name' => $batch->source_file_name,
                    'account' => $this->currentAccountSummary['name'] ?? 'Current account',
                    'status' => $batch->status?->label() ?? 'Staged',
                    'invalid_rows' => $batch->invalid_rows,
                    'duplicate_rows' => $batch->duplicate_rows,
                    'uploaded_at' => optional($batch->created_at)?->diffForHumans(),
                ];
            })
            ->all();
    }

    private function planLabelForAccount(Account $account): string
    {
        if ($account->base_subscription_plan === SubscriptionPlan::PREMIUM) {
            return $account->account_type === AccountType::RETAILER ? 'Plus' : 'Premium';
        }

        return 'Starter';
    }
}
