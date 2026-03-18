<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Console\Command;

class InitLogArchiveBucket extends Command
{
    protected $signature = 'logs:archive-bucket:init
        {bucket : Globally unique S3 bucket name to create}
        {--region= : AWS region override (defaults to AWS_DEFAULT_REGION)}';

    protected $description = 'Create and harden a dedicated S3 bucket for archived CRM logs';

    public function handle(): int
    {
        $bucket = strtolower(trim((string) $this->argument('bucket')));
        $region = $this->option('region') ?: config('filesystems.disks.s3.region');

        if ($bucket === '') {
            $this->error('Bucket name is required.');
            return self::FAILURE;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint'),
        ]);

        $exists = false;

        try {
            $client->headBucket(['Bucket' => $bucket]);
            $exists = true;
        } catch (AwsException $exception) {
            $statusCode = $exception->getStatusCode();

            if ($statusCode === 403) {
                $this->error("Bucket {$bucket} already exists but is not accessible with the configured credentials.");
                return self::FAILURE;
            }

            if (! in_array($statusCode, [301, 404, null], true)) {
                throw $exception;
            }
        }

        if (! $exists) {
            $params = ['Bucket' => $bucket];

            if ($region !== 'us-east-1') {
                $params['CreateBucketConfiguration'] = [
                    'LocationConstraint' => $region,
                ];
            }

            $client->createBucket($params);
            $client->waitUntil('BucketExists', ['Bucket' => $bucket]);
            $this->info("Created bucket {$bucket} in {$region}.");
        } else {
            $this->info("Bucket {$bucket} already exists and is accessible.");
        }

        $client->putPublicAccessBlock([
            'Bucket' => $bucket,
            'PublicAccessBlockConfiguration' => [
                'BlockPublicAcls' => true,
                'IgnorePublicAcls' => true,
                'BlockPublicPolicy' => true,
                'RestrictPublicBuckets' => true,
            ],
        ]);

        $client->putBucketEncryption([
            'Bucket' => $bucket,
            'ServerSideEncryptionConfiguration' => [
                'Rules' => [[
                    'ApplyServerSideEncryptionByDefault' => [
                        'SSEAlgorithm' => 'AES256',
                    ],
                ]],
            ],
        ]);

        $this->newLine();
        $this->info('Bucket is ready for private log archives.');
        $this->line("Set AWS_LOG_ARCHIVE_BUCKET={$bucket} in the deployment environment.");

        return self::SUCCESS;
    }
}