<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Procurement\Support\SupplierIntakeWorkbenchSignals;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupplierIntakeWorkbench extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Supplier Intake';

    protected static ?string $title = 'Supplier Intake Workbench';

    protected static UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'supplier-intake-workbench';

    protected string $view = 'filament.pages.supplier-intake-workbench';

    public array $statusRail = [];
    public array $incomingRequests = [];
    public array $workflowNotes = [];
    public array $actionChecklist = [];
    public array $currentAccountSummary = [];
    public array $signalCards = [];

    public function mount(): void
    {
        $supplierAccount = $this->resolveSupplierAccount();
        $snapshot = $supplierAccount
            ? SupplierIntakeWorkbenchSignals::forAccount($supplierAccount)
            : [
                'current_account_summary' => [
                    'name' => 'No supplier account selected',
                    'type' => 'Supplier',
                    'retailer_connections' => 0,
                    'open_quotes' => 0,
                    'approved_quotes' => 0,
                    'invoices_issued' => 0,
                    'incoming_requests' => 0,
                    'latest_signal' => 'No live requests',
                ],
                'signal_cards' => [],
                'status_rail' => [],
                'incoming_requests' => [],
                'workflow_notes' => [
                    [
                        'title' => 'Quotes & Proformas inbox',
                        'copy' => 'No supplier account is currently selected for this user.',
                    ],
                ],
                'action_checklist' => [
                    'Attach the user to an active supplier account to load live intake signals.',
                ],
            ];

        $this->currentAccountSummary = $snapshot['current_account_summary'];
        $this->signalCards = $snapshot['signal_cards'];
        $this->statusRail = $snapshot['status_rail'];
        $this->incomingRequests = $snapshot['incoming_requests'];
        $this->workflowNotes = $snapshot['workflow_notes'];
        $this->actionChecklist = $snapshot['action_checklist'];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    protected function resolveSupplierAccount(): ?Account
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $context = app(CurrentAccountResolver::class)->resolve(request(), $user);
        $currentAccount = $context->currentAccount;

        if ($currentAccount instanceof Account && $currentAccount->supportsWholesalePortal()) {
            return $currentAccount;
        }

        return $user->accounts()
            ->select('accounts.*')
            ->where('accounts.status', AccountStatus::ACTIVE->value)
            ->where(function (Builder $query): void {
                $query->where('accounts.wholesale_enabled', true)
                    ->orWhere('accounts.account_type', AccountType::SUPPLIER->value)
                    ->orWhere('accounts.account_type', AccountType::BOTH->value);
            })
            ->orderByRaw('CASE WHEN account_user.is_default = 1 THEN 0 ELSE 1 END')
            ->orderBy('accounts.name')
            ->first();
    }
}
