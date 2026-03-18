<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RestoreLogsFromS3 extends Command
{
    protected $signature = 'logs:restore-from-s3
        {key : S3 archive key for a .jsonl.gz file or matching .manifest.json file}
        {--disk=s3_archive : Filesystem disk to read the archive from}
        {--table= : Override the destination table name}
        {--batch=500 : Number of rows to upsert per batch}
        {--dry-run : Parse the archive and report what would be restored without writing to the database}';

    protected $description = 'Restore archived CRM log rows from S3 back into the database';

    protected array $tableMap = [
        'activity_logs' => [
            'primary_key' => 'id',
        ],
        'user_login_histories' => [
            'primary_key' => 'id',
        ],
        'inventory_logs' => [
            'primary_key' => 'id',
        ],
    ];

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $inputKey = trim((string) $this->argument('key'));
        $batchSize = max(1, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');

        if (! array_key_exists($disk, config('filesystems.disks'))) {
            $this->error("Filesystem disk {$disk} is not configured.");
            return self::FAILURE;
        }

        [$archiveKey, $manifestKey] = $this->resolveArchiveKeys($inputKey);

        if (! Storage::disk($disk)->exists($archiveKey)) {
            $this->error("Archive file {$archiveKey} was not found on disk {$disk}.");
            return self::FAILURE;
        }

        $manifest = $this->readManifest($disk, $manifestKey);
        $table = trim((string) ($this->option('table') ?: ($manifest['table'] ?? '')));

        if ($table === '') {
            $table = $this->inferTableFromArchiveKey($archiveKey);
        }

        if (! isset($this->tableMap[$table])) {
            $this->error("Unsupported restore table: {$table}.");
            return self::FAILURE;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Destination table {$table} does not exist.");
            return self::FAILURE;
        }

        $primaryKey = $this->tableMap[$table]['primary_key'];
        $tableColumns = Schema::getColumnListing($table);

        $this->info('Restoring CRM logs from S3');
        $this->line("Disk: {$disk}");
        $this->line("Archive: {$archiveKey}");
        $this->line("Table: {$table}");
        $this->line('Mode: ' . ($dryRun ? 'dry-run' : 'upsert'));

        $tempPath = tempnam(sys_get_temp_dir(), 'logrestore_');

        if ($tempPath === false) {
            $this->error('Failed to allocate a temporary file for the archive download.');
            return self::FAILURE;
        }

        $remoteStream = Storage::disk($disk)->readStream($archiveKey);

        if ($remoteStream === false) {
            @unlink($tempPath);
            $this->error('Failed to open the archive stream from S3.');
            return self::FAILURE;
        }

        $localStream = fopen($tempPath, 'wb');

        if ($localStream === false) {
            if (is_resource($remoteStream)) {
                fclose($remoteStream);
            }

            @unlink($tempPath);
            $this->error('Failed to open the local temporary archive file.');
            return self::FAILURE;
        }

        stream_copy_to_stream($remoteStream, $localStream);
        fclose($remoteStream);
        fclose($localStream);

        $gzHandle = gzopen($tempPath, 'rb');

        if ($gzHandle === false) {
            @unlink($tempPath);
            $this->error('Failed to open the downloaded archive as gzip.');
            return self::FAILURE;
        }

        $buffer = [];
        $lineNumber = 0;
        $parsedRows = 0;
        $writtenRows = 0;

        try {
            while (! gzeof($gzHandle)) {
                $line = gzgets($gzHandle);

                if ($line === false) {
                    continue;
                }

                $lineNumber++;
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    throw new \RuntimeException("Invalid JSON at line {$lineNumber}.");
                }

                $row = array_intersect_key($decoded, array_flip($tableColumns));

                if (! array_key_exists($primaryKey, $row)) {
                    throw new \RuntimeException("Archive row at line {$lineNumber} is missing primary key {$primaryKey}.");
                }

                $buffer[] = $row;
                $parsedRows++;

                if (count($buffer) >= $batchSize) {
                    $writtenRows += $this->flushRows($table, $primaryKey, $buffer, $dryRun);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $writtenRows += $this->flushRows($table, $primaryKey, $buffer, $dryRun);
            }
        } catch (\Throwable $exception) {
            gzclose($gzHandle);
            @unlink($tempPath);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        gzclose($gzHandle);
        @unlink($tempPath);

        $this->newLine();
        $this->info('Restore command completed.');
        $this->line("Parsed rows: {$parsedRows}");
        $this->line(($dryRun ? 'Rows that would be written' : 'Rows written') . ": {$writtenRows}");

        if ($manifest !== null && isset($manifest['row_count'])) {
            $this->line('Manifest row count: ' . (int) $manifest['row_count']);
        }

        return self::SUCCESS;
    }

    protected function resolveArchiveKeys(string $inputKey): array
    {
        if (str_ends_with($inputKey, '.manifest.json')) {
            $archiveKey = preg_replace('/\.manifest\.json$/', '.jsonl.gz', $inputKey) ?: $inputKey;

            return [$archiveKey, $inputKey];
        }

        $manifestKey = preg_replace('/\.jsonl\.gz$/', '.manifest.json', $inputKey) ?: $inputKey;

        return [$inputKey, $manifestKey];
    }

    protected function readManifest(string $disk, string $manifestKey): ?array
    {
        if (! Storage::disk($disk)->exists($manifestKey)) {
            return null;
        }

        $contents = Storage::disk($disk)->get($manifestKey);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function inferTableFromArchiveKey(string $archiveKey): string
    {
        foreach (array_keys($this->tableMap) as $table) {
            if (str_contains($archiveKey, '/' . $table . '/') || str_starts_with(basename($archiveKey), $table . '_')) {
                return $table;
            }
        }

        return '';
    }

    protected function flushRows(string $table, string $primaryKey, array $rows, bool $dryRun): int
    {
        if ($rows === []) {
            return 0;
        }

        if ($dryRun) {
            return count($rows);
        }

        $updateColumns = array_values(array_filter(array_keys($rows[0]), fn (string $column): bool => $column !== $primaryKey));

        DB::table($table)->upsert($rows, [$primaryKey], $updateColumns);

        return count($rows);
    }
}