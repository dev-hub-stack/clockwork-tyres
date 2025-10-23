<?php

namespace App\Filament\Resources\AddonCategoryResource\Pages;

use App\Filament\Resources\AddonCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAddonCategory extends CreateRecord
{
    protected static string $resource = AddonCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
