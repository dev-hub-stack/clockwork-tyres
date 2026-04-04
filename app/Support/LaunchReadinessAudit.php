<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;

class LaunchReadinessAudit
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly Router $router,
    ) {
    }

    /**
     * @return array{
     *     summary: array{pass:int,warn:int,fail:int},
     *     checks: array<int, array{name:string,status:string,detail:string}>
     * }
     */
    public function run(?string $storefrontPath = null): array
    {
        $checks = [
            $this->appKeyCheck(),
            $this->databaseCheck(),
            $this->migrationsCheck(),
            $this->s3Check(),
            $this->adminAssetsCheck(),
            $this->runtimePermissionsCheck(),
            $this->adminRoutesCheck(),
            $this->queueCheck(),
        ];

        if ($storefrontPath !== null && trim($storefrontPath) !== '') {
            $checks[] = $this->storefrontBuildCheck($storefrontPath);
        } else {
            $checks[] = [
                'name' => 'Storefront build artifact',
                'status' => 'warn',
                'detail' => 'No storefront path was provided. Pass --storefront-path to verify the Angular production build.',
            ];
        }

        $summary = [
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
        ];

        foreach ($checks as $check) {
            if (array_key_exists($check['status'], $summary)) {
                $summary[$check['status']]++;
            }
        }

        return [
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    private function appKeyCheck(): array
    {
        $key = (string) config('app.key');

        return [
            'name' => 'Application key',
            'status' => $key !== '' ? 'pass' : 'fail',
            'detail' => $key !== ''
                ? 'APP_KEY is configured.'
                : 'APP_KEY is missing.',
        ];
    }

    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return [
                'name' => 'Database connectivity',
                'status' => 'pass',
                'detail' => 'Primary database connection is reachable.',
            ];
        } catch (QueryException|\Throwable $exception) {
            return [
                'name' => 'Database connectivity',
                'status' => 'fail',
                'detail' => 'Database connection failed: '.$exception->getMessage(),
            ];
        }
    }

    private function migrationsCheck(): array
    {
        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return [
                'name' => 'Pending migrations',
                'status' => 'fail',
                'detail' => 'Migration repository does not exist.',
            ];
        }

        $paths = array_merge($this->migrator->paths(), [database_path('migrations')]);
        $migrationFiles = $this->migrator->getMigrationFiles($paths);
        $ran = $repository->getRan();
        $pending = array_values(array_diff(array_keys($migrationFiles), $ran));

        if ($pending === []) {
            return [
                'name' => 'Pending migrations',
                'status' => 'pass',
                'detail' => 'No pending migrations detected.',
            ];
        }

        return [
            'name' => 'Pending migrations',
            'status' => 'fail',
            'detail' => sprintf('%d pending migration(s): %s', count($pending), implode(', ', array_slice($pending, 0, 3))),
        ];
    }

    private function s3Check(): array
    {
        $disk = config('filesystems.disks.s3', []);
        $required = [
            'key' => $disk['key'] ?? null,
            'secret' => $disk['secret'] ?? null,
            'region' => $disk['region'] ?? null,
            'bucket' => $disk['bucket'] ?? null,
        ];

        $missing = array_keys(array_filter($required, fn ($value) => $value === null || $value === ''));

        return [
            'name' => 'S3 image storage',
            'status' => $missing === [] ? 'pass' : 'fail',
            'detail' => $missing === []
                ? 'S3 disk is configured for products, addons, and tyres.'
                : 'Missing S3 settings: '.implode(', ', $missing),
        ];
    }

    private function adminAssetsCheck(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'name' => 'Admin asset manifest',
            'status' => is_file($manifest) ? 'pass' : 'fail',
            'detail' => is_file($manifest)
                ? 'Vite build manifest exists for the admin panel.'
                : 'Missing public/build/manifest.json. Run npm build for the backend theme assets.',
        ];
    }

    private function runtimePermissionsCheck(): array
    {
        $paths = [
            storage_path('framework/views'),
            storage_path('framework/cache'),
            base_path('bootstrap/cache'),
        ];

        $unwritable = array_values(array_filter($paths, fn (string $path) => ! is_dir($path) || ! is_writable($path)));

        return [
            'name' => 'Runtime cache directories',
            'status' => $unwritable === [] ? 'pass' : 'fail',
            'detail' => $unwritable === []
                ? 'Storage/framework and bootstrap/cache are writable.'
                : 'Unwritable paths: '.implode(', ', $unwritable),
        ];
    }

    private function adminRoutesCheck(): array
    {
        $requiredUris = [
            'admin/login',
            'admin/dashboard',
            'admin/accounts',
            'admin/procurement-workbench',
            'admin/tyres/images',
        ];

        $existingUris = collect($this->router->getRoutes()->getRoutes())
            ->map(fn ($route) => ltrim($route->uri(), '/'))
            ->all();

        $missing = array_values(array_diff($requiredUris, $existingUris));

        return [
            'name' => 'Critical admin routes',
            'status' => $missing === [] ? 'pass' : 'fail',
            'detail' => $missing === []
                ? 'Critical admin routes are registered.'
                : 'Missing routes: '.implode(', ', $missing),
        ];
    }

    private function queueCheck(): array
    {
        $default = (string) config('queue.default', '');

        return [
            'name' => 'Queue connection',
            'status' => $default === 'sync' ? 'warn' : 'pass',
            'detail' => $default === 'sync'
                ? 'Queue default is sync. Use a real async queue for production.'
                : sprintf('Queue default is %s.', $default),
        ];
    }

    private function storefrontBuildCheck(string $storefrontPath): array
    {
        $browserIndex = rtrim($storefrontPath, '\\/').DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'clockwork-tyres-storefront'.DIRECTORY_SEPARATOR.'browser'.DIRECTORY_SEPARATOR.'index.html';

        return [
            'name' => 'Storefront build artifact',
            'status' => is_file($browserIndex) ? 'pass' : 'warn',
            'detail' => is_file($browserIndex)
                ? 'Angular storefront production build artifact is present.'
                : 'Missing storefront dist artifact at '.$browserIndex,
        ];
    }
}
