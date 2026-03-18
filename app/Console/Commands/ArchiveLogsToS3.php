<?php

namespace App\Console\Commands;

use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchiveLogsToS3 extends Command
{
    protected $signature = 'logs:archive-to-s3
        {--tables=activity_logs,user_login_histories,inventory_logs : Comma-separated list of tables to archive}
        {--days= : Archive rows older than this many days}
        {--disk=s3_archive : Filesystem disk to use for archive upload}
        {--prefix=archives/logs : S3 prefix/folder path}
        {--delete : Delete rows after a successful upload}
        {--dry-run : Show what would be archived without uploading or deleting}';

    protected $description = 'Archive old CRM log tables to S3 as gzipped JSONL files';

    protected array $tableMap = [
        'activity_logs' => [
            'date_column' => 'created_at',
            'order_column' => 'id',
        ],
        'user_login_histories' => [
            'date_column' => 'logged_in_at',
            'order_column' => 'id',
        ],
        'inventory_logs' => [
            'date_column' => 'created_at',
            'order_column' => 'id',
        ],
    ];

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $configuredDays = (int) SystemSetting::get(
            'log_archive_retention_days',
            SystemSetting::get('login_history_retention_days', 365)
        );
        $daysOption = $this->option('days');
        $days = max(1, (int) ($daysOption !== null ? $daysOption : $configuredDays));
        $cutoff = now()->subDays($days);
        $prefix = trim((string) $this->option('prefix'), '/');
        $delete = (bool) $this->option('delete');
        $dryRun = (bool) $this->option('dry-run');
        $tables = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('tables')))));

        if (! array_key_exists($disk, config('filesystems.disks'))) {
            $this->error("Filesystem disk {$disk} is not configured.");
            return self::FAILURE;
        }

        if (empty(config("filesystems.disks.{$disk}.bucket"))) {
            $this->error("Filesystem disk {$disk} does not have a bucket configured.");
            return self::FAILURE;
        }

        $this->info('Archiving CRM logs to S3');
        $this->line("Disk: {$disk}");
        $this->line('Retention window: ' . $days . ' day(s)' . ($daysOption !== null ? ' (command override)' : ' (from settings)'));
        $this->line('Cutoff: ' . $cutoff->toDateTimeString());
        $this->line('Mode: ' . ($dryRun ? 'dry-run' : ($delete ? 'upload + delete' : 'upload only')));

        foreach ($tables as $table) {
            if (! isset($this->tableMap[$table])) {
                $this->warn("Skipping unsupported table: {$table}");
                continue;
            }

            $dateColumn = $this->tableMap[$table]['date_column'];
            $orderColumn = $this->tableMap[$table]['order_column'];
            $count = DB::table($table)->where($dateColumn, '<', $cutoff)->count();

            $this->newLine();
            $this->info("{$table}: {$count} row(s) older than {$days} days");

            if ($count === 0 || $dryRun) {
                continue;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'logarc_');
            if ($tempPath === false) {
                $this->error("Failed to allocate temporary file for {$table}.");
                return self::FAILURE;
            }

            $gzHandle = gzopen($tempPath, 'wb9');
            if ($gzHandle === false) {
                @unlink($tempPath);
                $this->error("Failed to open gzip stream for {$table}.");
                return self::FAILURE;
            }

            DB::table($table)
                ->where($dateColumn, '<', $cutoff)
                ->orderBy($orderColumn)
                ->chunk(500, function ($rows) use ($gzHandle): void {
                    foreach ($rows as $row) {
                        gzwrite($gzHandle, json_encode((array) $row, JSON_UNESCAPED_SLASHES) . "\n");
                    }
                });

            gzclose($gzHandle);

            $archiveStamp = now()->format('Ymd_His');
            $baseName = "{$table}_before_{$cutoff->format('Ymd_His')}_{$archiveStamp}";
            $s3BasePath = "{$prefix}/{$table}/{$cutoff->format('Y/m')}";
            $archiveKey = "{$s3BasePath}/{$baseName}.jsonl.gz";
            $manifestKey = "{$s3BasePath}/{$baseName}.manifest.json";

            $stream = fopen($tempPath, 'r');
            if ($stream === false) {
                @unlink($tempPath);
                $this->error("Failed to reopen archive file for {$table}.");
                return self::FAILURE;
            }

            Storage::disk($disk)->put($archiveKey, $stream);
            fclose($stream);

            Storage::disk($disk)->put($manifestKey, json_encode([
                'table' => $table,
                'row_count' => $count,
                'cutoff' => $cutoff->toIso8601String(),
                'archived_at' => now()->toIso8601String(),
                'delete_after_upload' => $delete,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            @unlink($tempPath);

            $this->line("Uploaded {$archiveKey}");

            if ($delete) {
                $deleted = DB::table($table)->where($dateColumn, '<', $cutoff)->delete();
                $this->line("Deleted {$deleted} archived row(s) from {$table}");
            }
        }

        $this->newLine();
        $this->info('Archive command completed.');

        return self::SUCCESS;
    }
}