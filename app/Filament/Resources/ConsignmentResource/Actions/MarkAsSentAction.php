<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class MarkAsSentAction
{
    public static function make(): Action
    {
        return Action::make('mark_as_sent')
            ->label('Mark as Sent')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (Consignment $record) => $record->status->value === 'draft')
            ->requiresConfirmation()
            ->modalHeading('Mark Consignment as Sent')
            ->modalDescription('This will mark the consignment as sent to the customer. Items will be considered delivered.')
            ->modalIcon('heroicon-o-paper-airplane')
            ->form([
                TextInput::make('tracking_number')
                    ->label('Tracking Number (Optional)')
                    ->placeholder('e.g., 1234567890')
                    ->maxLength(100)
                    ->helperText('Enter shipping tracking number if available'),
            ])
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                try {
                    $trackingNumber = $data['tracking_number'] ?? null;
                    
                    $service->markAsSent($record, $trackingNumber);
                    
                    $body = "Consignment {$record->consignment_number} has been marked as sent";
                    if ($trackingNumber) {
                        $body .= " with tracking number: {$trackingNumber}";
                    }
                    
                    Notification::make()
                        ->success()
                        ->title('Consignment Marked as Sent')
                        ->body($body)
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Marking as Sent')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->modalSubmitActionLabel('Mark as Sent');
    }
}
