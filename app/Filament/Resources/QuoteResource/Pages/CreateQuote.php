<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\QuoteStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure it's created as a quote
        $data['document_type'] = DocumentType::QUOTE;
        $data['quote_status'] = QuoteStatus::DRAFT;
        $data['issue_date'] = $data['issue_date'] ?? now();
        $data['currency'] = $data['currency'] ?? 'AED';
        $data['tax_inclusive'] = $data['tax_inclusive'] ?? false;
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Quote created successfully!';
    }
}
