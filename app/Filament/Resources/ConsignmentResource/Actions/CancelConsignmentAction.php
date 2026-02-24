<?php

namespace App\Filament\Resources\ConsignmentResource\Actions;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Services\ConsignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CancelConsignmentAction
{
    public static function make(): Action
    {
        return Action::make('cancel_consignment')
            ->label('Cancel Consignment')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Consignment $record) => 
                in_array($record->status->value, ['draft', 'sent']) && 
                $record->items_sold_count === 0
            )
            ->requiresConfirmation()
            ->modalHeading('Cancel Consignment')
            ->modalDescription('Are you sure you want to cancel this consignment? This action cannot be undone.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->form([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->required()
                    ->rows(3)
                    ->placeholder('Please provide a reason for cancelling this consignment...')
                    ->helperText('This reason will be logged in the consignment history'),
            ])
            ->action(function (Consignment $record, array $data, ConsignmentService $service) {
                \Log::info('CancelConsignmentAction called', [
                    'consignment_id' => $record->id,
                    'consignment_number' => $record->consignment_number,
                    'action' => 'cancel_consignment',
                    'reason' => $data['reason'] ?? null,
                ]);
                
                try {
                    // Additional validation
                    if ($record->items_sold_count > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Cannot Cancel')
                            ->body('Cannot cancel consignment with sold items. Please record returns first.')
                            ->send();
                        return;
                    }
                    
                    $reason = $data['reason'] ?? 'No reason provided';
                    
                    $service->cancelConsignment($record, $reason);
                    
                    Notification::make()
                        ->success()
                        ->title('Consignment Cancelled')
                        ->body("Consignment {$record->consignment_number} has been cancelled.<br>Reason: {$reason}")
                        ->send();
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error Cancelling Consignment')
                        ->body($e->getMessage())
                        ->send();
                    
                    throw $e;
                }
            })
            ->modalSubmitActionLabel('Cancel Consignment')
            ->modalSubmitAction(fn ($action) => $action->color('danger'));
    }
}
