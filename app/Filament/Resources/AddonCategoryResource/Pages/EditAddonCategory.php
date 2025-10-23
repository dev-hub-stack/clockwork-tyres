<?php

namespace App\Filament\Resources\AddonCategoryResource\Pages;

use App\Filament\Resources\AddonCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAddonCategory extends EditRecord
{
    protected static string $resource = AddonCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
