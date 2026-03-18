<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Orders\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Quote')
                ->icon('heroicon-o-plus'),
        ];
    }
    
    public function getTitle(): string
    {
        return 'Quotes & Proformas';
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Quotes')
                ->badge(Order::quotes()->count()),
                
            'retail' => Tab::make('Retail Orders')
                ->badge(Order::quotes()->where(function($q) {
                    $q->where('channel', 'retail')->orWhereNull('channel');
                })->count())
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->where(function($q) {
                        $q->where('channel', 'retail')->orWhereNull('channel');
                    });
                }),
                
            'wholesale' => Tab::make('Wholesale Orders')
                ->badge(Order::quotes()->where('channel', 'wholesale')->count())
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->where('channel', 'wholesale');
                }),
        ];
    }
}
