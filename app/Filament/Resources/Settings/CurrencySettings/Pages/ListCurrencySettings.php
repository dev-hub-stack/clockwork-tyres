<?php

namespace App\Filament\Resources\Settings\CurrencySettings\Pages;

use App\Filament\Resources\Settings\CurrencySettings\CurrencySettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCurrencySettings extends ListRecords
{
    protected static string $resource = CurrencySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
