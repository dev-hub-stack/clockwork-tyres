<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Models\Addon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;

class ViewAddon extends ViewRecord
{
    protected static string $resource = AddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Action::make('clear_restock')
                ->label('Clear Restock Waitlist')
                ->icon('heroicon-o-bell-slash')
                ->color('warning')
                ->visible(fn (): bool => count($this->record->notify_restock ?? []) > 0)
                ->requiresConfirmation()
                ->modalHeading('Clear Restock Waitlist')
                ->modalDescription(fn (): string =>
                    'Remove all ' . count($this->record->notify_restock ?? []) . ' dealer(s) waiting for restock notification?'
                )
                ->action(function (): void {
                    $this->record->update(['notify_restock' => []]);
                    $this->refreshFormData(['notify_restock']);
                    Notification::make()
                        ->title('Waitlist cleared — ' . count($this->record->fresh()->notify_restock ?? []) . ' emails removed.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Format notify_restock as readable text for the view
        $emails = $data['notify_restock'] ?? [];
        $data['notify_restock_display'] = is_array($emails) && count($emails)
            ? implode("\n", $emails)
            : 'No dealers are currently waiting for restock.';
        $data['notify_restock_count'] = is_array($emails) ? count($emails) : 0;
        return $data;
    }
}
