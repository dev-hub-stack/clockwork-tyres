<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\SupplierIntakeWorkbenchSignals;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
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
    public array $latestApprovalSummary = [];

    public function mount(): void
    {
        $this->refreshWorkbench();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::experimentalPagesEnabled()
            && (auth()->user()?->can('view_quotes') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::experimentalPagesEnabled()
            && (auth()->user()?->can('view_quotes') ?? false);
    }

    protected static function experimentalPagesEnabled(): bool
    {
        return (bool) config('wholesale.experimental_admin_pages', false);
    }

    public function approveRequest(int $requestId, ApproveProcurementRequestAction $approveAction): void
    {
        $supplierAccount = $this->resolveSupplierAccount();

        if (! $supplierAccount instanceof Account) {
            Notification::make()
                ->title('Supplier account required')
                ->body('Attach the user to an active supplier account before approving procurement requests.')
                ->warning()
                ->send();

            return;
        }

        $request = ProcurementRequest::query()
            ->forSupplier($supplierAccount)
            ->with(['quoteOrder.items', 'invoiceOrder.items', 'retailerAccount', 'customer'])
            ->find($requestId);

        if (! $request instanceof ProcurementRequest) {
            Notification::make()
                ->title('Request not found')
                ->body('The selected procurement request is no longer available for this supplier account.')
                ->warning()
                ->send();

            return;
        }

        if (in_array($request->current_stage, [
            ProcurementWorkflowStage::STOCK_RESERVED,
            ProcurementWorkflowStage::STOCK_DEDUCTED,
            ProcurementWorkflowStage::FULFILLED,
            ProcurementWorkflowStage::CANCELLED,
        ], true)) {
            Notification::make()
                ->title('Request already processed')
                ->body('This procurement request has already moved beyond quote approval.')
                ->warning()
                ->send();

            return;
        }

        try {
            $approved = $approveAction->execute($request);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Approval failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->latestApprovalSummary = [
            'request_number' => $approved->request_number,
            'invoice_number' => $approved->invoiceOrder?->order_number
                ?? $approved->invoiceOrder?->quote_number
                ?? $approved->request_number,
            'stage' => $approved->current_stage?->label() ?? 'Approved',
        ];

        $this->refreshWorkbench();

        Notification::make()
            ->title('Procurement approved')
            ->body(sprintf(
                '%s was approved and moved to %s.',
                $approved->request_number ?? ('PRQ-'.$approved->id),
                $approved->current_stage?->label() ?? 'invoice conversion'
            ))
            ->success()
            ->send();
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

    protected function refreshWorkbench(): void
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
}
