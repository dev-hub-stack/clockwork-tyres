<?php

namespace App\Filament\Resources\ConsignmentResource\Schemas;

use App\Modules\Settings\Models\CurrencySetting;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class ConsignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currency = CurrencySetting::getBase()?->currency_symbol ?? 'AED';
        
        return $schema
            ->components([
                // Section 1: Consignment Information
                Section::make('Consignment Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('consignment_number')
                                    ->label('Consignment Number')
                                    ->weight('bold')
                                    ->copyable()
                                    ->icon('heroicon-o-document-text'),
                                
                                TextEntry::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->placeholder('N/A')
                                    ->copyable(),
                                
                                TextEntry::make('customer.business_name')
                                    ->label('Customer')
                                    ->icon('heroicon-o-user')
                                    ->url(fn ($record) => $record->customer ? route('filament.admin.resources.customers.view', $record->customer) : null),
                                
                                TextEntry::make('representative.name')
                                    ->label('Sales Representative')
                                    ->placeholder('N/A')
                                    ->icon('heroicon-o-user-circle'),
                                
                                TextEntry::make('warehouse.warehouse_name')
                                    ->label('Warehouse')
                                    ->placeholder('N/A')
                                    ->icon('heroicon-o-building-storefront'),
                                
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state?->value) {
                                        'draft' => 'secondary',
                                        'sent' => 'primary',
                                        'partial' => 'warning',
                                        'completed' => 'success',
                                        'returned' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                
                                TextEntry::make('issue_date')
                                    ->label('Issue Date')
                                    ->date()
                                    ->icon('heroicon-o-calendar'),
                                
                                TextEntry::make('expected_return_date')
                                    ->label('Expected Return Date')
                                    ->date()
                                    ->placeholder('Not Set')
                                    ->icon('heroicon-o-calendar-days'),
                                
                                TextEntry::make('sent_at')
                                    ->label('Sent Date')
                                    ->dateTime()
                                    ->placeholder('Not Sent')
                                    ->icon('heroicon-o-paper-airplane'),
                                
                                TextEntry::make('delivered_at')
                                    ->label('Delivered Date')
                                    ->dateTime()
                                    ->placeholder('Not Delivered')
                                    ->icon('heroicon-o-truck'),
                                
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime()
                                    ->icon('heroicon-o-clock'),
                                
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->icon('heroicon-o-arrow-path'),
                            ]),
                    ])
                    ->collapsible(),
                
                // Section 2: Vehicle Information
                Section::make('Vehicle Information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('vehicle_year')
                                    ->label('Year')
                                    ->placeholder('N/A'),
                                
                                TextEntry::make('vehicle_make')
                                    ->label('Make')
                                    ->placeholder('N/A'),
                                
                                TextEntry::make('vehicle_model')
                                    ->label('Model')
                                    ->placeholder('N/A'),
                                
                                TextEntry::make('vehicle_sub_model')
                                    ->label('Sub Model / Trim')
                                    ->placeholder('N/A'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->vehicle_year || $record->vehicle_make || $record->vehicle_model || $record->vehicle_sub_model),
                
                // Section 3: Consignment Items
                Section::make('Consignment Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(8)
                                    ->schema([
                                        TextEntry::make('product_snapshot.product.name')
                                            ->label('Product')
                                            ->getStateUsing(fn ($record) => $record->product_snapshot['product']['name'] ?? $record->product_variant?->product?->name ?? 'N/A')
                                            ->weight('bold')
                                            ->columnSpan(2),
                                        
                                        TextEntry::make('product_snapshot.sku')
                                            ->label('SKU')
                                            ->getStateUsing(fn ($record) => $record->product_snapshot['sku'] ?? $record->product_variant?->sku ?? 'N/A')
                                            ->badge()
                                            ->color('gray')
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('quantity_sent')
                                            ->label('Sent')
                                            ->badge()
                                            ->color('info')
                                            ->alignCenter()
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('quantity_sold')
                                            ->label('Sold')
                                            ->badge()
                                            ->color('success')
                                            ->alignCenter()
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('quantity_returned')
                                            ->label('Returned')
                                            ->badge()
                                            ->color('warning')
                                            ->alignCenter()
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('price')
                                            ->label('Price')
                                            ->money($currency)
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('subtotal')
                                            ->label('Subtotal')
                                            ->money($currency)
                                            ->weight('medium')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->contained(false),
                    ])
                    ->collapsible(),
                
                // Section 4: Financial Summary
                Section::make('Financial Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money($currency)
                                    ->size('lg'),
                                
                                TextEntry::make('discount')
                                    ->label('Discount')
                                    ->money($currency)
                                    ->visible(fn ($record) => $record->discount > 0),
                                
                                TextEntry::make('shipping_cost')
                                    ->label('Shipping Cost')
                                    ->money($currency)
                                    ->visible(fn ($record) => $record->shipping_cost > 0),
                                
                                TextEntry::make('tax')
                                    ->label('Tax')
                                    ->money($currency)
                                    ->helperText(fn ($record) => $record->tax_rate ? "Tax Rate: {$record->tax_rate}%" : null),
                                
                                TextEntry::make('total')
                                    ->label('Total Amount')
                                    ->money($currency)
                                    ->weight('bold')
                                    ->size('xl')
                                    ->color('success'),
                                
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                // Section 5: Statistics Cards
                Section::make('Summary Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('items_sent_count')
                                        ->label('Items Sent')
                                        ->size('xl')
                                        ->weight('bold')
                                        ->color('info')
                                        ->icon('heroicon-o-paper-airplane')
                                        ->alignCenter(),
                                ]),
                                
                                Group::make([
                                    TextEntry::make('items_sold_count')
                                        ->label('Items Sold')
                                        ->size('xl')
                                        ->weight('bold')
                                        ->color('success')
                                        ->icon('heroicon-o-banknotes')
                                        ->alignCenter(),
                                ]),
                                
                                Group::make([
                                    TextEntry::make('items_returned_count')
                                        ->label('Items Returned')
                                        ->size('xl')
                                        ->weight('bold')
                                        ->color('warning')
                                        ->icon('heroicon-o-arrow-uturn-left')
                                        ->alignCenter(),
                                ]),
                                
                                Group::make([
                                    TextEntry::make('items_available_count')
                                        ->label('Items Available')
                                        ->getStateUsing(function ($record) {
                                            $available = $record->items_sent_count - $record->items_sold_count - $record->items_returned_count;
                                            
                                            \Log::debug('ConsignmentInfolist::items_available_count calculation', [
                                                'consignment_id' => $record->id,
                                                'consignment_number' => $record->consignment_number,
                                                'items_sent_count' => $record->items_sent_count,
                                                'items_sold_count' => $record->items_sold_count,
                                                'items_returned_count' => $record->items_returned_count,
                                                'calculated_available' => $available,
                                            ]);
                                            
                                            return $available;
                                        })
                                        ->size('xl')
                                        ->weight('bold')
                                        ->color('primary')
                                        ->icon('heroicon-o-cube')
                                        ->alignCenter(),
                                ]),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                // Section 6: History Timeline
                Section::make('History & Activity')
                    ->schema([
                        RepeatableEntry::make('histories')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('action')
                                            ->badge()
                                            ->color(fn ($state) => match ($state) {
                                                'created' => 'success',
                                                'updated' => 'info',
                                                'status_changed' => 'warning',
                                                'item_sold' => 'success',
                                                'item_returned' => 'warning',
                                                'cancelled' => 'danger',
                                                default => 'gray',
                                            })
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('description')
                                            ->label('Details')
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('created_at')
                                            ->label('When')
                                            ->dateTime()
                                            ->helperText(fn ($record) => $record->performedBy ? "By: {$record->performedBy->name}" : null)
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->contained(false)
                            ->placeholder('No history recorded yet.'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->histories()->exists()),
                
                // Section 7: Notes
                Section::make('Notes & Comments')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('Customer Notes')
                                    ->placeholder('No customer notes')
                                    ->columnSpanFull()
                                    ->html()
                                    ->visible(fn ($record) => !empty($record->notes)),
                                
                                TextEntry::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->placeholder('No internal notes')
                                    ->columnSpanFull()
                                    ->html()
                                    ->visible(fn ($record) => !empty($record->internal_notes)),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->notes) || !empty($record->internal_notes)),
            ]);
    }
}
