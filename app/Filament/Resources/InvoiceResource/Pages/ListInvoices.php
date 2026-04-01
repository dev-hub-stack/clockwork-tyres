<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Invoices')
                ->badge($this->invoiceCount()),

            'direct' => Tab::make('Direct Invoices')
                ->badge($this->invoiceCount(fn (Builder $query) => $query->whereDoesntHave('procurementInvoiceRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereDoesntHave('procurementInvoiceRequest');
                }),

            'procurement' => Tab::make('Procurement Invoices')
                ->badge($this->invoiceCount(fn (Builder $query) => $query->whereHas('procurementInvoiceRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('procurementInvoiceRequest');
                }),
        ];
    }

    private function invoiceCount(?callable $scope = null): int
    {
        $query = clone InvoiceResource::getEloquentQuery();

        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }
}
