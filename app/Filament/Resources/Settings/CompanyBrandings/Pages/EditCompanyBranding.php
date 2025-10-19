<?php

namespace App\Filament\Resources\Settings\CompanyBrandings\Pages;

use App\Filament\Resources\Settings\CompanyBrandings\CompanyBrandingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyBranding extends EditRecord
{
    protected static string $resource = CompanyBrandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
