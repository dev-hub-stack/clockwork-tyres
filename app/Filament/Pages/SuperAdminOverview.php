<?php

namespace App\Filament\Pages;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\SuperAdminAccountCreationBlueprint;
use BackedEnum;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

class SuperAdminOverview extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Super Admin Overview';

    protected static ?string $title = 'Super Admin Overview';

    protected static UnitEnum|string|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'super-admin-overview';

    protected string $view = 'filament.pages.super-admin-overview';

    public array $governanceCards = [];
    public array $accountBreakdown = [];
    public array $subscriptionBreakdown = [];
    public array $connectionSummary = [];
    public array $metricCards = [];
    public array $accountGovernanceCards = [];
    public array $accountCreationFields = [];
    public array $accountDirectoryColumns = [];
    public array $accountRows = [];
    public array $reportAddOnTiers = [];
    public array $accountGovernanceActions = [];
    public array $guardrailCards = [];
    public array $governanceActions = [];
    public array $opsPanels = [];
    public array $accountCreationBlueprint = [];

    public function mount(): void
    {
        $blueprint = new SuperAdminAccountCreationBlueprint();
        $this->accountCreationBlueprint = $blueprint->toArray();
        $this->governanceCards = $this->buildGovernanceCards();
        $this->accountBreakdown = $this->buildAccountBreakdown();
        $this->subscriptionBreakdown = $this->buildSubscriptionBreakdown();
        $this->connectionSummary = $this->buildConnectionSummary();
        $this->metricCards = $this->governanceCards;
        $this->accountGovernanceCards = $this->buildAccountGovernanceCards();
        $this->accountCreationFields = $blueprint->creationFields();
        $this->accountDirectoryColumns = $this->buildAccountDirectoryColumns();
        $this->accountRows = $this->buildAccountRows();
        $this->reportAddOnTiers = $this->buildReportAddOnTiers();
        $this->accountGovernanceActions = $blueprint->accountCreationFlow();
        $this->guardrailCards = $this->buildGuardrailCards($blueprint);
        $this->governanceActions = $this->buildGovernanceActions();
        $this->opsPanels = $this->buildOpsPanels();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function buildGovernanceCards(): array
    {
        $totals = $this->counts();

        return [
            [
                'label' => 'Managed accounts',
                'value' => $totals['accounts'],
                'note' => 'Platform-level count of retailer, supplier, and mixed accounts.',
            ],
            [
                'label' => 'Active subscriptions',
                'value' => $totals['subscriptions'],
                'note' => 'Main plan coverage across the platform.',
            ],
            [
                'label' => 'Reports add-ons',
                'value' => $totals['reports_addons'],
                'note' => 'Super-admin configurable reporting entitlements.',
            ],
            [
                'label' => 'Platform alerts',
                'value' => 'Pending live data',
                'note' => 'Import failures, sync issues, and operational exceptions.',
            ],
        ];
    }

    protected function buildAccountBreakdown(): array
    {
        $totals = $this->counts();

        return [
            [
                'label' => 'Retail enabled',
                'value' => $totals['retail_enabled'],
                'note' => 'Accounts that can operate the retail storefront or retailer admin.',
            ],
            [
                'label' => 'Wholesale enabled',
                'value' => $totals['wholesale_enabled'],
                'note' => 'Accounts that can operate supplier workflows and supplier preview mode.',
            ],
            [
                'label' => 'Mixed accounts',
                'value' => $totals['both_accounts'],
                'note' => 'Businesses with both retailer and supplier capabilities.',
            ],
        ];
    }

    protected function buildSubscriptionBreakdown(): array
    {
        $totals = $this->counts();

        return [
            [
                'label' => 'Subscription records',
                'value' => $totals['subscriptions'],
                'note' => 'Platform subscription records across all accounts.',
            ],
            [
                'label' => 'Reports add-ons',
                'value' => $totals['reports_addons'],
                'note' => 'Accounts with reporting add-ons enabled.',
            ],
        ];
    }

    protected function buildAccountGovernanceCards(): array
    {
        $totals = $this->counts();

        return [
            [
                'label' => 'Create supplier account',
                'value' => 'Direct control',
                'note' => 'Super admin creates and manages supplier accounts without an approval queue.',
            ],
            [
                'label' => 'Manage retailer accounts',
                'value' => $totals['retail_enabled'],
                'note' => 'Retail accounts can be activated, suspended, or assigned plans.',
            ],
            [
                'label' => 'Manage mixed accounts',
                'value' => $totals['both_accounts'],
                'note' => 'A single business can be retailer, supplier, or both.',
            ],
        ];
    }

    protected function buildConnectionSummary(): array
    {
        $totals = $this->counts();

        return [
            [
                'label' => 'Approved connections',
                'value' => $totals['approved_connections'],
                'note' => 'Live retailer-to-supplier relationships.',
            ],
            [
                'label' => 'Pending connections',
                'value' => $totals['pending_connections'],
                'note' => 'Supplier links still awaiting review or approval.',
            ],
        ];
    }

    protected function buildAccountDirectoryColumns(): array
    {
        return [
            'Account',
            'Type',
            'Status',
            'Base plan',
            'Reports add-on',
            'Wholesale',
            'Retail',
            'Connected retailers',
        ];
    }

    protected function buildAccountRows(): array
    {
        $totals = $this->counts();

        return [
            [
                'account' => 'Retail placeholder',
                'type' => AccountType::RETAILER->label(),
                'status' => AccountStatus::ACTIVE->label(),
                'base_plan' => SubscriptionPlan::BASIC->label(),
                'reports_addon' => 'Disabled',
                'wholesale' => 'No',
                'retail' => 'Yes',
                'connected_retailers' => (string) $totals['approved_connections'],
            ],
            [
                'account' => 'Supplier placeholder',
                'type' => AccountType::SUPPLIER->label(),
                'status' => AccountStatus::ACTIVE->label(),
                'base_plan' => SubscriptionPlan::BASIC->label(),
                'reports_addon' => '250 customers',
                'wholesale' => 'Yes',
                'retail' => 'No',
                'connected_retailers' => (string) $totals['approved_connections'],
            ],
            [
                'account' => 'Mixed account placeholder',
                'type' => AccountType::BOTH->label(),
                'status' => AccountStatus::SUSPENDED->label(),
                'base_plan' => SubscriptionPlan::PREMIUM->label(),
                'reports_addon' => '500 customers',
                'wholesale' => 'Yes',
                'retail' => 'Yes',
                'connected_retailers' => (string) $totals['pending_connections'],
            ],
        ];
    }

    protected function buildReportAddOnTiers(): array
    {
        return [
            [
                'label' => '250 connected retailers',
                'price' => 'AED 50 / month',
                'note' => 'Example tier shared by George.',
            ],
            [
                'label' => '500 connected retailers',
                'price' => 'AED 100 / month',
                'note' => 'Example tier shared by George.',
            ],
            [
                'label' => 'Custom tier',
                'price' => 'Set in super admin',
                'note' => 'Adjust customer limit and pricing case by case.',
            ],
        ];
    }

    protected function buildGuardrailCards(SuperAdminAccountCreationBlueprint $blueprint): array
    {
        return [
            [
                'label' => 'No impersonation',
                'value' => $blueprint->canImpersonateAccounts() ? 'Enabled' : 'Disabled by design',
                'note' => 'Super admin should never log in as an account for support.',
            ],
            [
                'label' => 'No supplier approval queue',
                'value' => $blueprint->usesManualApprovalQueue() ? 'Approval queue' : 'Direct management',
                'note' => 'Supplier accounts are created and managed directly instead of being approved.',
            ],
            [
                'label' => 'No product editing',
                'value' => 'Platform boundary',
                'note' => 'Products and inventory stay in supplier or retailer admin, not super admin.',
            ],
        ];
    }

    protected function buildGovernanceActions(): array
    {
        return [
            'Create supplier or retailer account',
            'Activate or suspend an account',
            'Change the main subscription plan',
            'Configure reports add-on and customer limit',
            'Review connected-retailer growth and platform analytics',
        ];
    }

    protected function buildOpsPanels(): array
    {
        return [
            [
                'title' => 'Error log',
                'description' => 'Keep visibility over import failures, sync exceptions, and operational blockers.',
            ],
            [
                'title' => 'Audit visibility',
                'description' => 'Track account status changes, subscription updates, and reports-tier adjustments.',
            ],
            [
                'title' => 'Retail store bridge',
                'description' => 'Context-switch into the retail storefront without turning super admin into a product-edit surface.',
            ],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function counts(): array
    {
        try {
            return [
                'accounts' => Account::query()->count(),
                'retail_enabled' => Account::query()->where('retail_enabled', true)->count(),
                'wholesale_enabled' => Account::query()->where('wholesale_enabled', true)->count(),
                'both_accounts' => Account::query()->where('account_type', AccountType::BOTH->value)->count(),
                'subscriptions' => AccountSubscription::query()->count(),
                'reports_addons' => AccountSubscription::query()->where('reports_enabled', true)->count(),
                'approved_connections' => AccountConnection::query()->where('status', 'approved')->count(),
                'pending_connections' => AccountConnection::query()->where('status', 'pending')->count(),
            ];
        } catch (Throwable) {
            return [
                'accounts' => 'Pending live data',
                'retail_enabled' => 'Pending live data',
                'wholesale_enabled' => 'Pending live data',
                'both_accounts' => 'Pending live data',
                'subscriptions' => 'Pending live data',
                'reports_addons' => 'Pending live data',
                'approved_connections' => 'Pending live data',
                'pending_connections' => 'Pending live data',
            ];
        }
    }
}
