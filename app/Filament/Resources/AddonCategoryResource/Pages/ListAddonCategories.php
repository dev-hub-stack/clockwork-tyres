<?php

namespace App\Filament\Resources\AddonCategoryResource\Pages;

use App\Filament\Resources\AddonCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAddonCategories extends ListRecords
{
    protected static string $resource = AddonCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
