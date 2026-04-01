<?php

namespace App\Filament\Pages;

use App\Modules\Accounts\Support\CurrentAccountContext;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use UnitEnum;

class SwitchBusinessAccount extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Switch Business Account';

    protected static ?string $title = 'Switch Business Account';

    protected static ?string $slug = 'switch-business-account';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.switch-business-account';

    public ?array $data = [];

    public array $currentAccountSummary = [];

    public array $availableAccountSummaries = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null || $user->hasRole('super_admin')) {
            return false;
        }

        return app(CurrentAccountResolver::class)->availableAccounts($user)->count() > 1;
    }

    public function mount(CurrentAccountResolver $resolver): void
    {
        abort_unless(static::canAccess(), 403);

        $this->hydrateContext(
            $resolver->resolve(request(), auth()->user()),
        );
    }

    public function getHeading(): string
    {
        return 'Switch Business Account';
    }

    public function getSubheading(): ?string
    {
        return 'Change the active CRM business context without signing in as another user.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Active CRM context')
                    ->description('Quotes, invoices, procurement queues, and other business-scoped records follow this selection.')
                    ->schema([
                        Select::make('account_id')
                            ->label('Business account')
                            ->options($this->accountOptions())
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->helperText('This changes your active business context only. It does not impersonate another login or bypass platform permissions.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(CurrentAccountResolver $resolver): void
    {
        $selectedAccountId = (int) Arr::get($this->data, 'account_id', 0);
        $user = auth()->user();

        abort_unless($user !== null, 403);

        $context = $resolver->selectById(request(), $user, $selectedAccountId);

        $this->hydrateContext($context);

        Notification::make()
            ->title('Business account switched')
            ->body(($context->currentAccount?->name ?? 'The selected account').' is now active for CRM-scoped pages.')
            ->success()
            ->send();

        $this->redirect('/admin/dashboard', navigate: true);
    }

    /**
     * @return array<int, string>
     */
    protected function accountOptions(): array
    {
        return collect($this->availableAccountSummaries)
            ->mapWithKeys(fn (array $account): array => [
                (int) $account['id'] => $account['name'].' ('.$this->accountTypeLabel($account).')',
            ])
            ->all();
    }

    private function hydrateContext(CurrentAccountContext $context): void
    {
        $this->currentAccountSummary = $context->currentAccount?->toArray() ?? [];
        $this->availableAccountSummaries = $context->availableAccounts
            ->map(fn ($account) => $account->toArray())
            ->values()
            ->all();

        $this->form->fill([
            'account_id' => $context->currentAccount?->id,
        ]);
    }

    private function accountTypeLabel(array $account): string
    {
        $type = (string) ($account['account_type'] ?? 'retailer');

        return match ($type) {
            'supplier' => 'Supplier',
            'both' => 'Retail + Supplier',
            default => 'Retailer',
        };
    }
}
