<?php

namespace App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages;

use App\Filament\Resources\Modules\Settings\Models\SystemSettings\SystemSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSystemSettings extends ListRecords
{
    protected static string $resource = SystemSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
