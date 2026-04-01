<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
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
                ->badge($this->quoteCount()),

            'direct' => Tab::make('Direct Quotes')
                ->badge($this->quoteCount(fn (Builder $query) => $query->whereDoesntHave('procurementQuoteRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereDoesntHave('procurementQuoteRequest');
                }),

            'procurement' => Tab::make('Procurement Quotes')
                ->badge($this->quoteCount(fn (Builder $query) => $query->whereHas('procurementQuoteRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('procurementQuoteRequest');
                }),
        ];
    }

    private function quoteCount(?callable $scope = null): int
    {
        $query = clone QuoteResource::getEloquentQuery();

        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }
}
