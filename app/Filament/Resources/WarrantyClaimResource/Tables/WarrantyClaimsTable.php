<?php

namespace App\Filament\Resources\WarrantyClaimResource\Tables;

use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class WarrantyClaimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_date')
                    ->label('DATE')
                    ->date('M d, Y')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('claim_number')
                    ->label('NUMBER')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Claim number copied')
                    ->weight('bold'),
                
                TextColumn::make('customer.business_name')
                    ->label('CUSTOMER')
                    ->searchable(['business_name', 'first_name', 'last_name'])
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->customer?->business_name 
                            ?? $record->customer?->name 
                            ?? 'N/A';
                    })
                    ->description(fn ($record) => $record->customer?->email),
                
                BadgeColumn::make('status')
                    ->label('STATUS')
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->colors([
                        'gray' => WarrantyClaimStatus::DRAFT,
                        'warning' => [WarrantyClaimStatus::PENDING, WarrantyClaimStatus::REPLACED],
                        'success' => WarrantyClaimStatus::CLAIMED,
                        'info' => WarrantyClaimStatus::RETURNED,
                        'danger' => WarrantyClaimStatus::VOID,
                    ])
                    ->icons([
                        'heroicon-o-document' => WarrantyClaimStatus::DRAFT,
                        'heroicon-o-clock' => WarrantyClaimStatus::PENDING,
                        'heroicon-o-arrow-path' => WarrantyClaimStatus::REPLACED,
                        'heroicon-o-check-circle' => WarrantyClaimStatus::CLAIMED,
                        'heroicon-o-arrow-uturn-left' => WarrantyClaimStatus::RETURNED,
                        'heroicon-o-x-circle' => WarrantyClaimStatus::VOID,
                    ])
                    ->sortable(),
                
                TextColumn::make('items_count')
                    ->label('QUANTITY')
                    ->counts('items')
                    ->alignCenter()
                    ->sortable()
                    ->description(fn ($record) => 
                        $record->items->sum('quantity') . ' total items'
                    ),
                
                TextColumn::make('warehouse.warehouse_name')
                    ->label('WAREHOUSE')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('invoice.order_number')
                    ->label('INVOICE')
                    ->sortable()
                    ->toggleable()
                    ->description(fn ($record) => 
                        $record->invoice ? '$' . number_format($record->invoice->total ?? 0, 2) : null
                    ),
                
                TextColumn::make('representative.name')
                    ->label('REP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WarrantyClaimStatus::class)
                    ->multiple()
                    ->preload(),
                
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'warehouse_name')
                    ->preload(),
                
                Filter::make('claim_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('claim_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('claim_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                
                SelectFilter::make('representative_id')
                    ->label('Sales Rep')
                    ->relationship('representative', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.warranty-claims.view', ['record' => $record])),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => view('templates.warranty-claim-preview', [
                        'claim' => $record->load([
                            'invoice', 'customer', 'warehouse',
                            'items.productVariant.product.brand',
                            'items.productVariant.product.model',
                            'histories.user'
                        ]),
                        'companyName'    => CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC',
                        'companyAddress' => CompanyBranding::getActive()?->company_address ?? '',
                        'companyPhone'   => CompanyBranding::getActive()?->company_phone ?? '',
                        'companyEmail'   => CompanyBranding::getActive()?->company_email ?? '',
                        'taxNumber'      => CompanyBranding::getActive()?->tax_registration_number ?? '',
                        'logo'           => CompanyBranding::getActive()?->logo_url,
                        'currency'       => CurrencySetting::getBase()?->currency_symbol ?? 'AED',
                        'includeHistory' => true,
                    ])),
                Action::make('downloadPdf')
                    ->label('PDF (Full)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => route('warranty-claim.pdf', ['warrantyClaim' => $record, 'include_history' => 1]))
                    ->openUrlInNewTab()
                    ->tooltip('Download PDF with activity history'),
                Action::make('downloadCustomerPdf')
                    ->label('PDF (Customer)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn ($record) => route('warranty-claim.pdf', ['warrantyClaim' => $record, 'include_history' => 0]))
                    ->openUrlInNewTab()
                    ->tooltip('Download clean PDF for customer'),
                EditAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    Action::make('markAsPending')
                        ->label('Mark as Pending')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->changeStatus(WarrantyClaimStatus::PENDING, 'Bulk action');
                            }
                        }),
                    Action::make('exportSelected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn () => null), // TODO: Implement export
                ]),
            ])
            ->defaultSort('claim_date', 'desc')
            ->poll('30s')
            ->striped();
    }
}
