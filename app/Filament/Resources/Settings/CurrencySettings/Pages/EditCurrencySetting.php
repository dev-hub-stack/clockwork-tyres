<?php

namespace App\Filament\Resources\Settings\CurrencySettings\Pages;

use App\Filament\Resources\Settings\CurrencySettings\CurrencySettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCurrencySetting extends EditRecord
{
    protected static string $resource = CurrencySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
