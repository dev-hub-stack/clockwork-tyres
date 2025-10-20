<?php

namespace App\Filament\Resources\FinishResource\Pages;

use App\Filament\Resources\FinishResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinish extends EditRecord
{
    protected static string $resource = FinishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}
