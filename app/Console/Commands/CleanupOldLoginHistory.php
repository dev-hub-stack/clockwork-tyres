<?php

namespace App\Console\Commands;

use App\Models\UserLoginHistory;
use Illuminate\Console\Command;

class CleanupOldLoginHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'login-history:cleanup {--days=90 : Number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete login history records older than specified days (default: 90 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get retention days from settings or use command option as override
        $settingDays = \App\Modules\Settings\Models\SystemSetting::get('login_history_retention_days', 90);
        $days = $this->option('days') ?: $settingDays;
        $cutoffDate = now()->subDays($days);
        
        $this->info("Deleting login history records older than {$days} days (before {$cutoffDate->toDateString()})...");
        
        $deleted = UserLoginHistory::where('logged_in_at', '<', $cutoffDate)->delete();
        
        $this->info("✓ Deleted {$deleted} old login history records.");
        $this->info("Current retention setting: {$settingDays} days");
        
        return Command::SUCCESS;
    }
}
