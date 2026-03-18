<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        ActivityLogService::log(
            'user_logout',
            'User logged out',
            userId: $user->id,
        );
    }
}