<?php

namespace App\Filament\Resources\Settings\CompanyBrandings\Pages;

use App\Filament\Resources\Settings\CompanyBrandings\CompanyBrandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanyBrandings extends ListRecords
{
    protected static string $resource = CompanyBrandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
