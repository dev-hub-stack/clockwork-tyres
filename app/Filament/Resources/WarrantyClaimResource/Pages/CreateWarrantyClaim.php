<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use App\Modules\Warranties\Enums\ClaimActionType;
use Filament\Resources\Pages\CreateRecord;

class CreateWarrantyClaim extends CreateRecord
{
    protected static string $resource = WarrantyClaimResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate claim number
        $data['claim_number'] = $this->generateClaimNumber();
        
        // Set created_by to current user
        $data['created_by'] = auth()->id();
        
        // Set default status if not set
        $data['status'] = $data['status'] ?? 'draft';
        
        // Set issue_date same as claim_date if not set
        $data['issue_date'] = $data['issue_date'] ?? $data['claim_date'];
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // Log creation to history
        $this->record->addHistory(
            ClaimActionType::CREATED,
            "Warranty claim created by " . auth()->user()->name
        );
    }
    
    protected function generateClaimNumber(): string
    {
        $latest = \App\Modules\Warranties\Models\WarrantyClaim::latest('id')->first();
        $nextNumber = ($latest?->id ?? 0) + 1 + 2390000;
        return str_pad($nextNumber, 7, '0', STR_PAD_LEFT);
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
