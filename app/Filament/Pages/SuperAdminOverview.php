<?php

namespace App\Filament\Pages;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\SuperAdminAccountCreationBlueprint;
use App\Modules\Accounts\Support\SuperAdminOverviewData;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
    public array $accountTypeOptions = [];
    public array $accountStatusOptions = [];
    public array $subscriptionPlanOptions = [];
    public array $createAccountForm = [];
    public array $manageAccountForm = [];
    public array $selectedAccountSummary = [];
    public array $latestGovernanceAction = [];
    public ?int $selectedAccountId = null;

    public function mount(): void
    {
        $blueprint = new SuperAdminAccountCreationBlueprint();
        $this->accountCreationBlueprint = $blueprint->toArray();
        $this->accountTypeOptions = collect(AccountType::cases())
            ->mapWithKeys(fn (AccountType $type) => [$type->value => $type->label()])
            ->all();
        $this->accountStatusOptions = collect(AccountStatus::cases())
            ->mapWithKeys(fn (AccountStatus $status) => [$status->value => $status->label()])
            ->all();
        $this->subscriptionPlanOptions = collect(SubscriptionPlan::cases())
            ->mapWithKeys(fn (SubscriptionPlan $plan) => [$plan->value => $plan->label()])
            ->all();
        $this->accountCreationFields = $blueprint->creationFields();
        $this->accountGovernanceActions = $blueprint->accountCreationFlow();
        $this->guardrailCards = $this->buildGuardrailCards($blueprint);
        $this->governanceActions = $this->buildGovernanceActions();
        $this->opsPanels = $this->buildOpsPanels();
        $this->seedCreateAccountFormDefaults();
        $this->seedManageAccountFormDefaults();
        $this->refreshOverview();
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

    protected function buildAccountDirectoryColumns(): array
    {
        return $this->overviewData()->buildAccountDirectoryColumns();
    }

    protected function buildAccountRows(): array
    {
        return $this->overviewData()->buildAccountRows();
    }

    protected function buildReportAddOnTiers(): array
    {
        return $this->overviewData()->buildReportAddOnTiers();
    }

    protected function buildConnectionSummary(): array
    {
        return $this->overviewData()->buildConnectionSummary();
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
                'approved_connections' => AccountConnection::query()->where('status', AccountConnectionStatus::APPROVED->value)->count(),
                'pending_connections' => AccountConnection::query()->where('status', AccountConnectionStatus::PENDING->value)->count(),
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

    protected function overviewData(): SuperAdminOverviewData
    {
        return new SuperAdminOverviewData();
    }

    public function createAccount(): void
    {
        $payload = $this->validatedPayload($this->createAccountForm, true);

        DB::transaction(function () use ($payload): void {
            $account = Account::query()->create([
                ...$payload,
                'created_by_user_id' => Auth::id(),
            ]);

            $this->syncActiveSubscription($account, $payload);

            $this->selectedAccountId = $account->id;
            $this->latestGovernanceAction = [
                'label' => 'Created account',
                'summary' => sprintf('%s (%s)', $account->name, $account->account_type?->label() ?? 'Account'),
                'note' => sprintf(
                    'Base plan: %s. Reports add-on: %s.',
                    $account->base_subscription_plan?->label() ?? 'Unknown',
                    $account->reports_subscription_enabled
                        ? ($account->reports_customer_limit ? $account->reports_customer_limit.' customers' : 'Enabled')
                        : 'Disabled',
                ),
            ];
        });

        $this->seedCreateAccountFormDefaults();
        $this->refreshOverview();
        $this->loadSelectedAccount();

        Notification::make()
            ->title('Account created')
            ->body('Super admin account creation has been applied to the platform directory.')
            ->success()
            ->send();
    }

    public function selectAccount(int $accountId): void
    {
        $this->selectedAccountId = $accountId;
        $this->loadSelectedAccount();
    }

    public function saveSelectedAccount(): void
    {
        if (! $this->selectedAccountId) {
            Notification::make()
                ->title('No account selected')
                ->body('Choose an account from the directory before saving governance changes.')
                ->warning()
                ->send();

            return;
        }

        $payload = $this->validatedPayload($this->manageAccountForm, false, $this->selectedAccountId);

        DB::transaction(function () use ($payload): void {
            $account = Account::query()->findOrFail($this->selectedAccountId);
            $account->fill($payload);
            $account->save();

            $this->syncActiveSubscription($account, $payload);

            $this->latestGovernanceAction = [
                'label' => 'Updated account',
                'summary' => sprintf('%s is now %s', $account->name, $account->status?->label() ?? 'updated'),
                'note' => sprintf(
                    'Plan: %s. Reports add-on: %s.',
                    $account->base_subscription_plan?->label() ?? 'Unknown',
                    $account->reports_subscription_enabled
                        ? ($account->reports_customer_limit ? $account->reports_customer_limit.' customers' : 'Enabled')
                        : 'Disabled',
                ),
            ];
        });

        $this->refreshOverview();
        $this->loadSelectedAccount();

        Notification::make()
            ->title('Account updated')
            ->body('Status, capabilities, and subscription settings were saved.')
            ->success()
            ->send();
    }

    protected function refreshOverview(): void
    {
        $this->governanceCards = $this->buildGovernanceCards();
        $this->accountBreakdown = $this->buildAccountBreakdown();
        $this->subscriptionBreakdown = $this->buildSubscriptionBreakdown();
        $this->connectionSummary = $this->buildConnectionSummary();
        $this->metricCards = $this->governanceCards;
        $this->accountGovernanceCards = $this->buildAccountGovernanceCards();
        $this->accountDirectoryColumns = $this->buildAccountDirectoryColumns();
        $this->accountRows = $this->buildAccountRows();
        $this->reportAddOnTiers = $this->buildReportAddOnTiers();
    }

    protected function seedCreateAccountFormDefaults(): void
    {
        $this->createAccountForm = [
            'name' => '',
            'slug' => '',
            'account_type' => AccountType::RETAILER->value,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE->value,
            'base_subscription_plan' => SubscriptionPlan::BASIC->value,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ];
    }

    protected function seedManageAccountFormDefaults(): void
    {
        $this->manageAccountForm = [
            'name' => '',
            'slug' => '',
            'account_type' => AccountType::RETAILER->value,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE->value,
            'base_subscription_plan' => SubscriptionPlan::BASIC->value,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
        ];
        $this->selectedAccountSummary = [];
    }

    protected function loadSelectedAccount(): void
    {
        if (! $this->selectedAccountId) {
            $this->seedManageAccountFormDefaults();

            return;
        }

        $account = Account::query()
            ->withCount([
                'connectionsAsRetailer as approved_retailer_connections_count' => function ($query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
                'connectionsAsSupplier as approved_supplier_connections_count' => function ($query): void {
                    $query->where('status', AccountConnectionStatus::APPROVED->value);
                },
            ])
            ->find($this->selectedAccountId);

        if (! $account) {
            $this->selectedAccountId = null;
            $this->seedManageAccountFormDefaults();

            return;
        }

        $this->manageAccountForm = [
            'name' => $account->name,
            'slug' => $account->slug,
            'account_type' => $account->account_type?->value ?? AccountType::RETAILER->value,
            'retail_enabled' => $account->retail_enabled,
            'wholesale_enabled' => $account->wholesale_enabled,
            'status' => $account->status?->value ?? AccountStatus::ACTIVE->value,
            'base_subscription_plan' => $account->base_subscription_plan?->value ?? SubscriptionPlan::BASIC->value,
            'reports_subscription_enabled' => $account->reports_subscription_enabled,
            'reports_customer_limit' => $account->reports_customer_limit,
        ];

        $this->selectedAccountSummary = [
            'name' => $account->name,
            'slug' => $account->slug,
            'type' => $account->account_type?->label() ?? 'Retailer',
            'status' => $account->status?->label() ?? 'Active',
            'approved_connections' => (int) ($account->approved_retailer_connections_count + $account->approved_supplier_connections_count),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function validatedPayload(array $input, bool $creating, ?int $accountId = null): array
    {
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) ($input['name'] ?? ''));
        }

        $normalized = [
            'name' => trim((string) ($input['name'] ?? '')),
            'slug' => $slug,
            'account_type' => (string) ($input['account_type'] ?? AccountType::RETAILER->value),
            'retail_enabled' => (bool) ($input['retail_enabled'] ?? false),
            'wholesale_enabled' => (bool) ($input['wholesale_enabled'] ?? false),
            'status' => (string) ($input['status'] ?? AccountStatus::ACTIVE->value),
            'base_subscription_plan' => (string) ($input['base_subscription_plan'] ?? SubscriptionPlan::BASIC->value),
            'reports_subscription_enabled' => (bool) ($input['reports_subscription_enabled'] ?? false),
            'reports_customer_limit' => blank($input['reports_customer_limit'] ?? null)
                ? null
                : (int) $input['reports_customer_limit'],
        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:accounts,slug'.($creating ? '' : ','.$accountId)],
            'account_type' => ['required', 'in:'.implode(',', AccountType::values())],
            'retail_enabled' => ['boolean'],
            'wholesale_enabled' => ['boolean'],
            'status' => ['required', 'in:'.implode(',', AccountStatus::values())],
            'base_subscription_plan' => ['required', 'in:'.implode(',', array_keys($this->subscriptionPlanOptions))],
            'reports_subscription_enabled' => ['boolean'],
            'reports_customer_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($normalized, $rules)
            ->after(function ($validator) use ($normalized): void {
                if (! $normalized['reports_subscription_enabled']) {
                    return;
                }

                if ($normalized['reports_customer_limit'] === null) {
                    $validator->errors()->add('reports_customer_limit', 'Provide the reports customer limit when the add-on is enabled.');
                }
            })
            ->validate();

        if ($validated['account_type'] === AccountType::SUPPLIER->value && ! $validated['wholesale_enabled']) {
            $validated['wholesale_enabled'] = true;
        }

        if ($validated['account_type'] === AccountType::RETAILER->value && ! $validated['retail_enabled']) {
            $validated['retail_enabled'] = true;
        }

        if ($validated['account_type'] === AccountType::BOTH->value) {
            $validated['retail_enabled'] = true;
            $validated['wholesale_enabled'] = true;
        }

        if (! $validated['reports_subscription_enabled']) {
            $validated['reports_customer_limit'] = null;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncActiveSubscription(Account $account, array $payload): void
    {
        $subscription = $account->subscriptions()
            ->where('status', 'active')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            $account->subscriptions()->create([
                'plan_code' => $payload['base_subscription_plan'],
                'status' => 'active',
                'reports_enabled' => $payload['reports_subscription_enabled'],
                'reports_customer_limit' => $payload['reports_customer_limit'],
                'starts_at' => now(),
                'meta' => ['source' => 'super_admin_overview'],
                'created_by_user_id' => Auth::id(),
            ]);

            return;
        }

        $subscription->fill([
            'plan_code' => $payload['base_subscription_plan'],
            'reports_enabled' => $payload['reports_subscription_enabled'],
            'reports_customer_limit' => $payload['reports_customer_limit'],
            'meta' => array_merge($subscription->meta ?? [], ['source' => 'super_admin_overview']),
        ]);
        $subscription->save();
    }
}
