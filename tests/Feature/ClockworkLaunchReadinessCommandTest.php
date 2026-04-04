<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClockworkLaunchReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_passes_when_launch_critical_checks_are_configured(): void
    {
        config([
            'filesystems.disks.s3.key' => 'testing-key',
            'filesystems.disks.s3.secret' => 'testing-secret',
            'filesystems.disks.s3.region' => 'ap-south-1',
            'filesystems.disks.s3.bucket' => 'testing-bucket',
            'queue.default' => 'database',
        ]);

        $storefrontPath = storage_path('framework/testing/storefront-build');
        $browserPath = $storefrontPath.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'clockwork-tyres-storefront'.DIRECTORY_SEPARATOR.'browser';

        if (! is_dir($browserPath)) {
            mkdir($browserPath, 0777, true);
        }

        file_put_contents($browserPath.DIRECTORY_SEPARATOR.'index.html', '<html></html>');

        $this->artisan('clockwork:launch-readiness', [
            '--storefront-path' => $storefrontPath,
        ])
            ->expectsOutputToContain('Launch readiness passed')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_when_launch_critical_configuration_is_missing(): void
    {
        config([
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
            'filesystems.disks.s3.region' => null,
            'filesystems.disks.s3.bucket' => null,
        ]);

        $this->artisan('clockwork:launch-readiness', [
            '--json' => true,
        ])
            ->expectsOutputToContain('Missing S3 settings')
            ->assertExitCode(1);
    }
}
