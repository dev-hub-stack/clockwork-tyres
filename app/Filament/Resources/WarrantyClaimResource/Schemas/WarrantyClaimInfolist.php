<?php

namespace App\Filament\Resources\WarrantyClaimResource\Schemas;

use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;

class WarrantyClaimInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Header Section with Status and Key Info
                Section::make('Claim Overview')
                    ->schema([
                        Split::make([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('claim_number')
                                        ->label('Claim Number')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large)
                                        ->copyable()
                                        ->icon('heroicon-o-hashtag'),

                                    TextEntry::make('status')
                                        ->badge()
                                        ->formatStateUsing(fn ($state) => $state->getLabel())
                                        ->color(fn ($state) => $state->getColor())
                                        ->icon(fn ($state) => $state->getIcon()),
                                ]),
                        ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('claim_date')
                                    ->label('Claim Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('issue_date')
                                    ->label('Issue Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar')
                                    ->placeholder('Not specified'),

                                TextEntry::make('resolution_date')
                                    ->label('Resolution Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-check-circle')
                                    ->visible(fn ($record) => $record->resolution_date !== null)
                                    ->placeholder('Not resolved'),
                            ]),
                    ])
                    ->collapsible()
                    ->icon('heroicon-o-information-circle'),

                // Customer & Invoice Information
                Section::make('Customer Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('customer.business_name')
                                    ->label('Customer')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url(fn ($record) => $record->customer_id 
                                        ? "/admin/customers/{$record->customer_id}" 
                                        : null)
                                    ->color('primary'),

                                TextEntry::make('invoice.order_number')
                                    ->label('Invoice')
                                    ->icon('heroicon-o-document-text')
                                    ->url(fn ($record) => $record->invoice_id 
                                        ? "/admin/invoices/{$record->invoice_id}" 
                                        : null)
                                    ->color('primary')
                                    ->placeholder('No invoice linked')
                                    ->visible(fn ($record) => $record->invoice_id !== null),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('warehouse.warehouse_name')
                                    ->label('Warehouse')
                                    ->icon('heroicon-o-building-storefront'),

                                TextEntry::make('representative.name')
                                    ->label('Sales Representative')
                                    ->icon('heroicon-o-user'),
                            ]),
                    ])
                    ->collapsible()
                    ->icon('heroicon-o-user-circle'),

                // Claimed Items
                Section::make('Claimed Items')
                    ->schema([
                        ViewEntry::make('items')
                            ->label('')
                            ->view('filament.resources.warranty-claim.components.items-table'),
                    ])
                    ->collapsible()
                    ->icon('heroicon-o-cube'),

                // Notes Section
                Section::make('Notes')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('Customer Notes')
                                    ->placeholder('No customer notes')
                                    ->columnSpanFull()
                                    ->prose(),

                                TextEntry::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->placeholder('No internal notes')
                                    ->columnSpanFull()
                                    ->prose(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-document-text'),

                // Activity Timeline
                Section::make('Activity History')
                    ->schema([
                        ViewEntry::make('histories')
                            ->label('')
                            ->view('filament.resources.warranty-claim.components.history-timeline'),
                    ])
                    ->collapsible()
                    ->icon('heroicon-o-clock'),

                // Metadata Section
                Section::make('Metadata')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('createdBy.name')
                                    ->label('Created By')
                                    ->icon('heroicon-o-user-plus'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('M d, Y H:i')
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M d, Y H:i')
                                    ->since()
                                    ->icon('heroicon-o-arrow-path'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('resolvedBy.name')
                                    ->label('Resolved By')
                                    ->icon('heroicon-o-user-check')
                                    ->placeholder('Not resolved')
                                    ->visible(fn ($record) => $record->resolved_by !== null),

                                TextEntry::make('total_quantity')
                                    ->label('Total Items')
                                    ->icon('heroicon-o-cube')
                                    ->suffix(' items'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-information-circle'),
            ]);
    }
}
