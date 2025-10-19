<?php

namespace App\Filament\Resources\Settings\TaxSettings\Pages;

use App\Filament\Resources\Settings\TaxSettings\TaxSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxSetting extends CreateRecord
{
    protected static string $resource = TaxSettingResource::class;
}
