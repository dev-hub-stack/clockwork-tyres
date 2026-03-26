<?php

namespace App\Filament\Auth;

class Login extends \Filament\Auth\Pages\Login
{
    public function mount(): void
    {
        $this->sanitizeFilamentNotifications();

        parent::mount();
    }

    protected function sanitizeFilamentNotifications(): void
    {
        $notifications = session('filament.notifications');

        if ($notifications === null) {
            return;
        }

        if (! is_array($notifications)) {
            session()->forget('filament.notifications');

            return;
        }

        $notifications = array_values(array_filter(
            $notifications,
            static fn (mixed $notification): bool => is_array($notification),
        ));

        session()->put('filament.notifications', $notifications);
    }
}