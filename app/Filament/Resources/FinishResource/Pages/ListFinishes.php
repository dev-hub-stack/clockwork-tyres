<?php

namespace App\Filament\Resources\FinishResource\Pages;

use App\Filament\Resources\FinishResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinishes extends ListRecords
{
    protected static string $resource = FinishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
