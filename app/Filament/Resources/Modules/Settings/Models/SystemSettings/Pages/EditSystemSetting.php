<?php

namespace App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages;

use App\Filament\Resources\Modules\Settings\Models\SystemSettings\SystemSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSystemSetting extends EditRecord
{
    protected static string $resource = SystemSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
