<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementRequestResource\Pages;
use App\Filament\Support\PanelAccess;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProcurementRequestResource extends Resource
{
    protected static ?string $model = ProcurementRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Procurement Requests';

    protected static ?string $modelLabel = 'Procurement Request';

    protected static ?string $pluralModelLabel = 'Procurement Requests';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessOperationalSurface('view_quotes');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Request Overview')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('request_number')
                                    ->label('Request Number')
                                    ->weight('bold')
                                    ->copyable(),
                                \Filament\Infolists\Components\TextEntry::make('current_stage')
                                    ->label('Stage')
                                    ->badge()
                                    ->formatStateUsing(fn (?ProcurementWorkflowStage $state): string => $state?->label() ?? 'Submitted')
                                    ->color(fn (?ProcurementWorkflowStage $state): string => static::stageColor($state)),
                                \Filament\Infolists\Components\TextEntry::make('retailerAccount.name')
                                    ->label('Retailer Account')
                                    ->placeholder('N/A'),
                                \Filament\Infolists\Components\TextEntry::make('supplierAccount.name')
                                    ->label('Supplier Account')
                                    ->placeholder('N/A'),
                                \Filament\Infolists\Components\TextEntry::make('customer.business_name')
                                    ->label('Customer')
                                    ->placeholder('N/A'),
                                \Filament\Infolists\Components\TextEntry::make('submittedBy.name')
                                    ->label('Submitted By')
                                    ->placeholder('System'),
                                \Filament\Infolists\Components\TextEntry::make('quantity_total')
                                    ->label('Total Qty'),
                                \Filament\Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('AED'),
                                \Filament\Infolists\Components\TextEntry::make('quoteOrder.quote_number')
                                    ->label('Quote')
                                    ->placeholder('Not linked'),
                                \Filament\Infolists\Components\TextEntry::make('invoiceOrder.order_number')
                                    ->label('Invoice')
                                    ->placeholder('Not invoiced'),
                                \Filament\Infolists\Components\TextEntry::make('submitted_at')
                                    ->label('Submitted')
                                    ->dateTime()
                                    ->placeholder('Pending'),
                                \Filament\Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Approved')
                                    ->dateTime()
                                    ->placeholder('Pending'),
                            ]),
                    ]),
                \Filament\Schemas\Components\Section::make('Request Items')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('items')
                            ->contained(false)
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(7)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('product_name')
                                            ->label('Product')
                                            ->weight('bold')
                                            ->columnSpan(2),
                                        \Filament\Infolists\Components\TextEntry::make('sku')
                                            ->label('SKU')
                                            ->badge()
                                            ->color('gray'),
                                        \Filament\Infolists\Components\TextEntry::make('size')
                                            ->label('Size')
                                            ->placeholder('N/A'),
                                        \Filament\Infolists\Components\TextEntry::make('quantity')
                                            ->label('Qty'),
                                        \Filament\Infolists\Components\TextEntry::make('source')
                                            ->label('Source')
                                            ->placeholder('N/A'),
                                        \Filament\Infolists\Components\TextEntry::make('line_total')
                                            ->label('Line Total')
                                            ->money('AED'),
                                    ]),
                            ]),
                    ]),
                \Filament\Schemas\Components\Section::make('Notes')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('notes')
                            ->placeholder('No notes provided.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
                \Filament\Schemas\Components\Section::make('Supplier Response')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('meta.last_transition')
                            ->label('Current Supplier Response')
                            ->badge()
                            ->formatStateUsing(fn (?string $state, ProcurementRequest $record): string => static::supplierResponseLabel($record))
                            ->color(fn (?string $state, ProcurementRequest $record): string => static::supplierResponseColor($record)),
                        \Filament\Infolists\Components\TextEntry::make('meta.feedback_note')
                            ->label('Supplier Feedback Note')
                            ->placeholder('No supplier feedback logged.')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state, ProcurementRequest $record): string => static::supplierResponseNote($record)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('retailerAccount.name')
                    ->label('Retailer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplierAccount.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.business_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('N/A'),

                BadgeColumn::make('current_stage')
                    ->label('Stage')
                    ->formatStateUsing(fn (?ProcurementWorkflowStage $state): string => $state?->label() ?? 'Submitted')
                    ->colors([
                        'gray' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            ProcurementWorkflowStage::DRAFT,
                            ProcurementWorkflowStage::SUBMITTED,
                        ], true),
                        'warning' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            ProcurementWorkflowStage::SUPPLIER_REVIEW,
                            ProcurementWorkflowStage::QUOTED,
                            ProcurementWorkflowStage::APPROVED,
                        ], true),
                        'success' => static fn (?ProcurementWorkflowStage $state): bool => in_array($state, [
                            ProcurementWorkflowStage::INVOICED,
                            ProcurementWorkflowStage::STOCK_RESERVED,
                            ProcurementWorkflowStage::STOCK_DEDUCTED,
                            ProcurementWorkflowStage::FULFILLED,
                        ], true),
                        'danger' => static fn (?ProcurementWorkflowStage $state): bool => $state === ProcurementWorkflowStage::CANCELLED,
                    ]),

                TextColumn::make('quantity_total')
                    ->label('Qty')
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('AED')
                    ->sortable(),

                TextColumn::make('quoteOrder.quote_number')
                    ->label('Quote')
                    ->placeholder('Not linked'),

                TextColumn::make('invoiceOrder.order_number')
                    ->label('Invoice')
                    ->placeholder('Not invoiced'),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('current_stage')
                    ->label('Stage')
                    ->options(collect(ProcurementWorkflowStage::ordered())
                        ->mapWithKeys(fn (ProcurementWorkflowStage $stage): array => [$stage->value => $stage->label()])
                        ->all()),
                SelectFilter::make('retailer_account_id')
                    ->label('Retailer')
                    ->relationship('retailerAccount', 'name'),
                SelectFilter::make('supplier_account_id')
                    ->label('Supplier')
                    ->relationship('supplierAccount', 'name'),
            ])
            ->actions([
                Action::make('approve_to_invoice')
                    ->label('Approve to Invoice')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ProcurementRequest $record): bool => static::canApproveRecord($record))
                    ->requiresConfirmation()
                    ->action(function (ProcurementRequest $record): void {
                        app(ApproveProcurementRequestAction::class)->execute($record);
                        $record->refresh();

                        Notification::make()
                            ->title('Procurement approved')
                            ->body(($record->request_number ?? 'Procurement request').' was approved and moved into invoice flow.')
                            ->success()
                            ->send();
                    }),
                Action::make('request_revision')
                    ->label('Request Revision')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ProcurementRequest $record): bool => static::canRequestRevisionRecord($record))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Revision Note')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (ProcurementRequest $record, array $data): void {
                        if (! $record->quoteOrder) {
                            return;
                        }

                        app(ProcurementQuoteLifecycle::class)->requestRevision($record->quoteOrder, $data['note']);
                        $record->refresh();

                        Notification::make()
                            ->title('Revision requested')
                            ->body(($record->request_number ?? 'Procurement request').' was returned to supplier review.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject_procurement')
                    ->label("Reject / Can't Supply")
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ProcurementRequest $record): bool => static::canRejectRecord($record))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(4),
                    ])
                    ->requiresConfirmation()
                    ->action(function (ProcurementRequest $record, array $data): void {
                        if (! $record->quoteOrder) {
                            return;
                        }

                        app(ProcurementQuoteLifecycle::class)->reject($record->quoteOrder, $data['reason']);
                        $record->refresh();

                        Notification::make()
                            ->title('Procurement rejected')
                            ->body(($record->request_number ?? 'Procurement request').' was cancelled for the supplier.')
                            ->success()
                            ->send();
                    }),
                Action::make('open_quote')
                    ->label('Open Quote')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (ProcurementRequest $record): bool => $record->quoteOrder !== null)
                    ->url(fn (ProcurementRequest $record): ?string => $record->quoteOrder
                        ? route('filament.admin.resources.quotes.view', ['record' => $record->quoteOrder])
                        : null),
                Action::make('open_invoice')
                    ->label('Open Invoice')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('gray')
                    ->visible(fn (ProcurementRequest $record): bool => $record->invoiceOrder !== null)
                    ->url(fn (ProcurementRequest $record): ?string => $record->invoiceOrder
                        ? route('filament.admin.resources.invoices.view', ['record' => $record->invoiceOrder])
                        : null),
                ViewAction::make(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcurementRequests::route('/'),
            'view' => Pages\ViewProcurementRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'retailerAccount',
                'supplierAccount',
                'customer',
                'submittedBy',
                'quoteOrder',
                'invoiceOrder',
                'items',
            ]);

        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $currentAccount = app(CurrentAccountResolver::class)
            ->resolve(request(), $user)
            ->currentAccount;

        if (! $currentAccount) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($currentAccount): void {
            $builder->where('retailer_account_id', $currentAccount->id)
                ->orWhere('supplier_account_id', $currentAccount->id);
        });
    }

    private static function canApproveRecord(ProcurementRequest $record): bool
    {
        if (! static::isActiveSupplierForRequest($record)) {
            return false;
        }

        if (! $record->quoteOrder || $record->invoiceOrder) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::QUOTED,
            ProcurementWorkflowStage::APPROVED,
        ], true);
    }

    private static function canRejectRecord(ProcurementRequest $record): bool
    {
        if (! static::isActiveSupplierForRequest($record)) {
            return false;
        }

        if (! $record->quoteOrder || $record->invoiceOrder) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::DRAFT,
            ProcurementWorkflowStage::SUBMITTED,
            ProcurementWorkflowStage::SUPPLIER_REVIEW,
            ProcurementWorkflowStage::QUOTED,
        ], true);
    }

    private static function canRequestRevisionRecord(ProcurementRequest $record): bool
    {
        if (! static::isActiveSupplierForRequest($record)) {
            return false;
        }

        if (! $record->quoteOrder || $record->invoiceOrder) {
            return false;
        }

        return in_array($record->current_stage, [
            ProcurementWorkflowStage::SUPPLIER_REVIEW,
            ProcurementWorkflowStage::QUOTED,
        ], true);
    }

    private static function currentAccount()
    {
        $user = Auth::user();

        if (! $user || $user->hasRole('super_admin')) {
            return null;
        }

        return app(CurrentAccountResolver::class)->resolve(request(), $user)->currentAccount;
    }

    private static function isActiveSupplierForRequest(ProcurementRequest $record): bool
    {
        $currentAccount = static::currentAccount();

        return $currentAccount !== null
            && (int) $record->supplier_account_id === (int) $currentAccount->id;
    }

    private static function supplierResponseLabel(ProcurementRequest $record): string
    {
        return match (data_get($record->meta, 'last_transition')) {
            'supplier_rejected' => "Reject / Can't Supply",
            'supplier_revision_requested' => 'Revision Requested',
            'supplier_quote_ready' => 'Quoted',
            'supplier_review_started' => 'Supplier Review',
            default => $record->current_stage?->label() ?? 'No supplier response yet',
        };
    }

    private static function supplierResponseColor(ProcurementRequest $record): string
    {
        return match (data_get($record->meta, 'last_transition')) {
            'supplier_rejected' => 'danger',
            'supplier_revision_requested' => 'warning',
            'supplier_quote_ready' => 'warning',
            'supplier_review_started' => 'gray',
            default => 'gray',
        };
    }

    private static function supplierResponseNote(ProcurementRequest $record): string
    {
        $transition = data_get($record->meta, 'last_transition');

        return match ($transition) {
            'supplier_rejected' => (string) (data_get($record->meta, 'rejection_reason') ?? $record->notes ?? 'No rejection reason provided.'),
            'supplier_revision_requested' => (string) (data_get($record->meta, 'revision_request_note') ?? $record->notes ?? 'No revision note provided.'),
            default => $record->notes ?? 'No supplier feedback logged.',
        };
    }

    private static function stageColor(?ProcurementWorkflowStage $stage): string
    {
        return match ($stage) {
            ProcurementWorkflowStage::DRAFT,
            ProcurementWorkflowStage::SUBMITTED => 'gray',
            ProcurementWorkflowStage::SUPPLIER_REVIEW,
            ProcurementWorkflowStage::QUOTED,
            ProcurementWorkflowStage::APPROVED => 'warning',
            ProcurementWorkflowStage::INVOICED,
            ProcurementWorkflowStage::STOCK_RESERVED,
            ProcurementWorkflowStage::STOCK_DEDUCTED,
            ProcurementWorkflowStage::FULFILLED => 'success',
            ProcurementWorkflowStage::CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
