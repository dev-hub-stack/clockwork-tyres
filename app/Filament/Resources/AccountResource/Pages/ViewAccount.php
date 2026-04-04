<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Business Account: '.($this->record->name ?? 'N/A');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
