<?php

namespace App\Console\Commands;

use App\Support\LaunchReadinessAudit;
use Illuminate\Console\Command;

class ClockworkLaunchReadiness extends Command
{
    protected $signature = 'clockwork:launch-readiness
        {--storefront-path= : Absolute path to the storefront repo for build verification}
        {--json : Output readiness data as JSON}';

    protected $description = 'Run the Clockwork Tyres launch-readiness audit for backend and optional storefront assets.';

    public function handle(LaunchReadinessAudit $audit): int
    {
        $report = $audit->run($this->option('storefront-path'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['summary']['fail'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $rows = array_map(
            fn (array $check) => [strtoupper($check['status']), $check['name'], $check['detail']],
            $report['checks'],
        );

        $this->table(['Status', 'Check', 'Detail'], $rows);

        $this->newLine();
        $this->line(sprintf(
            'Summary: %d pass / %d warn / %d fail',
            $report['summary']['pass'],
            $report['summary']['warn'],
            $report['summary']['fail'],
        ));

        if ($report['summary']['fail'] > 0) {
            $this->error('Launch readiness has blocking failures.');

            return self::FAILURE;
        }

        if ($report['summary']['warn'] > 0) {
            $this->warn('Launch readiness passed with warnings.');

            return self::SUCCESS;
        }

        $this->info('Launch readiness passed.');

        return self::SUCCESS;
    }
}
