<?php

namespace App\Filament\Resources\PaymentHistoryResource\Pages;

use App\Filament\Resources\PaymentHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentHistories extends ListRecords
{
    protected static string $resource = PaymentHistoryResource::class;

    public function getTitle(): string
    {
        return 'Payment History Log';
    }
}