<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Mail\WholesaleInviteMail;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendWholesaleInvite')
                ->label('Send Wholesale Invite')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->visible(fn () => $this->record->isDealer())
                ->requiresConfirmation()
                ->modalHeading('Send Wholesale Account Invite')
                ->modalDescription(fn () => "Send an invite email to {$this->record->email} so they can set their password and access the wholesale portal. The link expires in 48 hours.")
                ->modalSubmitActionLabel('Send Invite')
                ->action(function () {
                    $token = Str::random(64);

                    $this->record->update([
                        'wholesale_invite_token'      => $token,
                        'wholesale_invite_expires_at' => now()->addHours(48),
                        'wholesale_invited_at'        => $this->record->wholesale_invited_at ?? now(),
                    ]);

                    Mail::to($this->record->email)->send(new WholesaleInviteMail($this->record, $token));

                    Notification::make()
                        ->title('Invite Sent')
                        ->body("Wholesale invite sent to {$this->record->email}. Valid for 48 hours.")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}

