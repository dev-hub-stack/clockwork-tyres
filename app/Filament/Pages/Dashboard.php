<?php

namespace App\Filament\Pages;

use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\BusinessAccountInsights;
use App\Modules\Accounts\Models\AccountConnection;
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

    public function mount(): void
    {
        $this->isSuperAdmin = PanelAccess::canAccessGovernanceSurface();

        if (! $this->isSuperAdmin) {
            return;
        }

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
}
