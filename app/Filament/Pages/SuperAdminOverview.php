<?php

namespace App\Filament\Pages;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use BackedEnum;
use Filament\Pages\Page;
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

    public array $metricCards = [];
    public array $accountDirectoryColumns = [];
    public array $accountRows = [];
    public array $reportAddOnTiers = [];
    public array $governanceActions = [];
    public array $opsPanels = [];

    public function mount(): void
    {
        $this->metricCards = $this->buildMetricCards();
        $this->accountDirectoryColumns = $this->buildAccountDirectoryColumns();
        $this->accountRows = $this->buildAccountRows();
        $this->reportAddOnTiers = $this->buildReportAddOnTiers();
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

    protected function buildMetricCards(): array
    {
        return [
            [
                'label' => 'Managed accounts',
                'value' => 'Pending live data',
                'note' => 'Platform-level count of retailer, supplier, and mixed accounts.',
            ],
            [
                'label' => 'Active subscriptions',
                'value' => 'Pending live data',
                'note' => 'Main plan coverage across the platform.',
            ],
            [
                'label' => 'Reports add-ons',
                'value' => 'Pending live data',
                'note' => 'Super-admin configurable reporting entitlements.',
            ],
            [
                'label' => 'Platform alerts',
                'value' => 'Pending live data',
                'note' => 'Import failures, sync issues, and operational exceptions.',
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
        return [
            [
                'account' => 'Retail placeholder',
                'type' => AccountType::RETAILER->label(),
                'status' => AccountStatus::ACTIVE->label(),
                'base_plan' => SubscriptionPlan::BASIC->label(),
                'reports_addon' => 'Disabled',
                'wholesale' => 'No',
                'retail' => 'Yes',
                'connected_retailers' => '-',
            ],
            [
                'account' => 'Supplier placeholder',
                'type' => AccountType::SUPPLIER->label(),
                'status' => AccountStatus::ACTIVE->label(),
                'base_plan' => SubscriptionPlan::BASIC->label(),
                'reports_addon' => '250 customers',
                'wholesale' => 'Yes',
                'retail' => 'No',
                'connected_retailers' => 'Pending live data',
            ],
            [
                'account' => 'Mixed account placeholder',
                'type' => AccountType::BOTH->label(),
                'status' => AccountStatus::SUSPENDED->label(),
                'base_plan' => SubscriptionPlan::PREMIUM->label(),
                'reports_addon' => '500 customers',
                'wholesale' => 'Yes',
                'retail' => 'Yes',
                'connected_retailers' => 'Pending live data',
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
}
