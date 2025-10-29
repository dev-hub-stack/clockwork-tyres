<?php

namespace App\Filament\Resources\ConsignmentResource\Tables;

use App\Filament\Resources\ConsignmentResource\Actions\CancelConsignmentAction;
use App\Filament\Resources\ConsignmentResource\Actions\ConvertToInvoiceAction;
use App\Filament\Resources\ConsignmentResource\Actions\MarkAsSentAction;
use App\Filament\Resources\ConsignmentResource\Actions\RecordReturnAction;
use App\Filament\Resources\ConsignmentResource\Actions\RecordSaleAction;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Settings\Models\CurrencySetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsignmentsTable
{
    public static function configure(Table $table): Table
    {
        $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
        
        return $table
            ->columns([
                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('consignment_number')
                    ->label('Consignment #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                
                TextColumn::make('customer.business_name')
                    ->label('Customer')
                    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                    ->sortable()
                    ->limit(30),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => ConsignmentStatus::DRAFT->value,
                        'primary' => ConsignmentStatus::SENT->value,
                        'warning' => ConsignmentStatus::PARTIAL->value,
                        'success' => ConsignmentStatus::COMPLETED->value,
                        'info' => ConsignmentStatus::RETURNED->value,
                        'danger' => ConsignmentStatus::CANCELLED->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? $state->getLabel() : 'N/A'),
                
                TextColumn::make('items_counts')
                    ->label('Items (S/S/R)')
                    ->getStateUsing(function ($record) {
                        return sprintf(
                            '%d/%d/%d',
                            $record->items_sent_count ?? 0,
                            $record->items_sold_count ?? 0,
                            $record->items_returned_count ?? 0
                        );
                    })
                    ->description(fn () => 'Sent/Sold/Returned')
                    ->alignCenter()
                    ->tooltip('Format: Sent / Sold / Returned'),
                
                TextColumn::make('total')
                    ->label('Total')
                    ->money($currency)
                    ->sortable()
                    ->weight('medium'),
                
                TextColumn::make('warehouse.warehouse_name')
                    ->label('Warehouse')
                    ->sortable()
                    ->toggleable()
                    ->limit(20),
                
                TextColumn::make('representative.name')
                    ->label('Representative')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),
                
                TextColumn::make('sent_at')
                    ->label('Sent Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ConsignmentStatus::DRAFT->value => 'Draft',
                        ConsignmentStatus::SENT->value => 'Sent',
                        ConsignmentStatus::PARTIAL->value => 'Partial',
                        ConsignmentStatus::COMPLETED->value => 'Completed',
                        ConsignmentStatus::RETURNED->value => 'Returned',
                        ConsignmentStatus::CANCELLED->value => 'Cancelled',
                    ]),
                
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'business_name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->business_name ?? $record->name ?? 'Unknown Customer'),
                
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'warehouse_name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('issue_date')
                    ->form([
                        DatePicker::make('issued_from')
                            ->label('Issued From'),
                        DatePicker::make('issued_until')
                            ->label('Issued Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),
                
                Filter::make('has_sold_items')
                    ->label('Has Sold Items')
                    ->query(fn (Builder $query): Builder => $query->where('items_sold_count', '>', 0))
                    ->toggle(),
                
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                
                EditAction::make(),
                
                MarkAsSentAction::make()
                    ->tooltip('Mark as sent to customer'),
                
                RecordSaleAction::make()
                    ->tooltip('Mark items as sold'),
                
                RecordReturnAction::make()
                    ->tooltip('Mark items as returned'),
                
                ConvertToInvoiceAction::make()
                    ->tooltip('Create invoice for sold items'),
                
                Action::make('print_pdf')
                    ->label('Print PDF')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => "#") // TODO: Implement PDF generation
                    ->openUrlInNewTab()
                    ->tooltip('Download consignment PDF'),
                
                CancelConsignmentAction::make()
                    ->tooltip('Cancel this consignment'),
                
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('issue_date', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
}
