<?php

namespace App\Filament\Resources\Modules\Settings\Models\SystemSettings\Pages;

use App\Filament\Resources\Modules\Settings\Models\SystemSettings\SystemSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSystemSetting extends CreateRecord
{
    protected static string $resource = SystemSettingResource::class;
}
