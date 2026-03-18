<?php

namespace App\Console\Commands;

use App\Modules\Settings\Models\SystemSetting;
use App\Support\TransactionalCustomerMail;
use Illuminate\Console\Command;

class ToggleEmailSuppression extends Command
{
    protected $signature = 'email:suppress
                            {state? : on or off (omit to show current status)}';

    protected $description = 'Enable or disable transactional customer email suppression';

    public function handle(): int
    {
        $state = $this->argument('state');

        $current = (bool) SystemSetting::get(TransactionalCustomerMail::SUPPRESSION_KEY, false);

        if ($state === null) {
            $this->line('');
            $this->line('  Transactional email suppression: ' . ($current ? '<fg=yellow>ON (emails suppressed)</>' : '<fg=green>OFF (emails sending normally)</>'));
            $this->line('');
            $this->line('  Usage:');
            $this->line('    php artisan email:suppress on   — suppress all customer emails');
            $this->line('    php artisan email:suppress off  — resume sending customer emails');
            $this->line('');
            return self::SUCCESS;
        }

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('Invalid argument. Use "on" or "off".');
            return self::FAILURE;
        }

        $enable = $state === 'on';

        SystemSetting::set(
            TransactionalCustomerMail::SUPPRESSION_KEY,
            $enable ? '1' : '0',
            'boolean',
            'Suppress transactional customer emails and log them internally'
        );

        if ($enable) {
            $this->info('Email suppression ENABLED — customer emails will be logged but not sent.');
        } else {
            $this->info('Email suppression DISABLED — customer emails will send normally.');
        }

        return self::SUCCESS;
    }
}
