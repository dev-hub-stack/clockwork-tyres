<?php

namespace App\Filament\Resources\WarrantyClaimResource\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\View;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class WarrantyClaimViewSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section with Key Info
                Section::make('Claim Information')
                    ->schema([
                        Grid::make(4)->schema([
                            Placeholder::make('claim_number')
                                ->label('Claim Number')
                                ->content(fn ($record) => $record->claim_number),

                            Placeholder::make('status')
                                ->label('Status')
                                ->content(fn ($record) => view('filament.components.status-badge', [
                                    'status' => $record->status,
                                ])),

                            Placeholder::make('claim_date')
                                ->label('Claim Date')
                                ->content(fn ($record) => $record->claim_date?->format('M d, Y') ?? 'N/A'),

                            Placeholder::make('resolution_date')
                                ->label('Resolution Date')
                                ->content(fn ($record) => $record->resolution_date?->format('M d, Y') ?? 'Not resolved'),
                        ]),
                    ])
                    ->collapsible(),

                // Customer & Invoice Section
                Section::make('Customer & Invoice Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Placeholder::make('customer')
                                ->label('Customer')
                                ->content(fn ($record) => $record->customer->business_name ?? 'N/A'),

                            Placeholder::make('invoice')
                                ->label('Invoice')
                                ->content(fn ($record) => $record->invoice?->order_number ?? 'No invoice linked'),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('warehouse')
                                ->label('Warehouse')
                                ->content(fn ($record) => $record->warehouse->warehouse_name ?? 'N/A'),

                            Placeholder::make('representative')
                                ->label('Sales Representative')
                                ->content(fn ($record) => $record->representative->name ?? 'N/A'),
                        ]),
                    ])
                    ->collapsible(),

                // Items Section with Custom View
                Section::make('Claimed Items')
                    ->schema([
                        View::make('filament.resources.warranty-claim.components.items-table-view'),
                    ])
                    ->collapsible(),

                // Notes Section
                Section::make('Notes')
                    ->schema([
                        Placeholder::make('notes')
                            ->label('Customer Notes')
                            ->content(fn ($record) => $record->notes ?? 'No customer notes'),

                        Placeholder::make('internal_notes')
                            ->label('Internal Notes')
                            ->content(fn ($record) => $record->internal_notes ?? 'No internal notes'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Activity History Section
                Section::make('Activity History')
                    ->schema([
                        View::make('filament.resources.warranty-claim.components.history-timeline-view'),
                    ])
                    ->collapsible(),
            ]);
    }
}
