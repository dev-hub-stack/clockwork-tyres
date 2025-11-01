<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use App\Filament\Resources\WarrantyClaimResource\Schemas\WarrantyClaimViewSchema;
use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewWarrantyClaim extends ViewRecord
{
    protected static string $resource = WarrantyClaimResource::class;

    public function schema(Schema $schema): Schema
    {
        return WarrantyClaimViewSchema::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Submit Claim (Draft → Pending)
            Actions\Action::make('submitClaim')
                ->label('Submit Claim')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn ($record) => $record->status === WarrantyClaimStatus::DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Submit Warranty Claim')
                ->modalDescription('Are you sure you want to submit this claim? It will be marked as Pending.')
                ->action(function ($record) {
                    $record->changeStatus(WarrantyClaimStatus::PENDING, 'Claim submitted for processing');
                    
                    Notification::make()
                        ->success()
                        ->title('Claim Submitted')
                        ->body('The warranty claim has been submitted and is now pending.')
                        ->send();
                }),

            // Mark Items Replaced (Pending → Replaced)
            Actions\Action::make('markReplaced')
                ->label('Mark Items Replaced')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record) => $record->status === WarrantyClaimStatus::PENDING)
                ->requiresConfirmation()
                ->modalHeading('Mark Items as Replaced')
                ->modalDescription('Mark all items in this claim as replaced.')
                ->form([
                    Textarea::make('notes')
                        ->label('Notes (Optional)')
                        ->placeholder('Add any notes about the replacement...')
                        ->rows(3),
                ])
                ->action(function ($record, array $data) {
                    $record->markAsReplaced($data['notes'] ?? null);
                    
                    Notification::make()
                        ->success()
                        ->title('Items Marked as Replaced')
                        ->body('The claim status has been updated to Replaced.')
                        ->send();
                }),

            // Mark as Claimed (Replaced → Claimed)
            Actions\Action::make('markClaimed')
                ->label('Mark as Claimed')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status === WarrantyClaimStatus::REPLACED)
                ->requiresConfirmation()
                ->modalHeading('Mark Claim as Completed')
                ->modalDescription('Mark this claim as completed and approved.')
                ->form([
                    Textarea::make('notes')
                        ->label('Notes (Optional)')
                        ->placeholder('Add any notes about the completion...')
                        ->rows(3),
                ])
                ->action(function ($record, array $data) {
                    $record->markAsClaimed($data['notes'] ?? null);
                    
                    Notification::make()
                        ->success()
                        ->title('Claim Completed')
                        ->body('The warranty claim has been marked as claimed/approved.')
                        ->send();
                }),

            // Void Claim
            Actions\Action::make('voidClaim')
                ->label('Void Claim')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->status !== WarrantyClaimStatus::VOID)
                ->requiresConfirmation()
                ->modalHeading('Void Warranty Claim')
                ->modalDescription('This will void the claim and cannot be undone. Please provide a reason.')
                ->form([
                    Textarea::make('reason')
                        ->label('Reason for Voiding')
                        ->placeholder('Explain why this claim is being voided...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function ($record, array $data) {
                    $record->void($data['reason']);
                    
                    Notification::make()
                        ->warning()
                        ->title('Claim Voided')
                        ->body('The warranty claim has been voided.')
                        ->send();
                }),

            Actions\ActionGroup::make([
                // Add Note
                Actions\Action::make('addNote')
                    ->label('Add Note')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->form([
                        Textarea::make('note')
                            ->label('Note')
                            ->placeholder('Enter your note...')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function ($record, array $data) {
                        $record->addNote($data['note']);
                        
                        Notification::make()
                            ->success()
                            ->title('Note Added')
                            ->body('Your note has been added to the claim history.')
                            ->send();
                    }),

                // Add Video Link
                Actions\Action::make('addVideo')
                    ->label('Add Video Link')
                    ->icon('heroicon-o-video-camera')
                    ->color('gray')
                    ->form([
                        TextInput::make('url')
                            ->label('Video URL')
                            ->placeholder('https://...')
                            ->url()
                            ->required(),
                        Textarea::make('description')
                            ->label('Description (Optional)')
                            ->placeholder('Brief description of the video...')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->addVideoLink($data['url'], $data['description'] ?? null);
                        
                        Notification::make()
                            ->success()
                            ->title('Video Link Added')
                            ->body('The video link has been added to the claim history.')
                            ->send();
                    }),

                // Edit Action
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),

                // Delete Action
                Actions\DeleteAction::make(),
            ])
                ->label('More Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }
}
