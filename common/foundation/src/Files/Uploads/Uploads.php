<?php

namespace Common\Files\Uploads;

use Common\Files\FileEntry as FilesFileEntry;
use Common\Files\Uploads\UploadDiskLocation;
use Common\Settings\Models\Setting;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class Uploads
{
    const STRATEGY_SUBFOLDER = 'subfolder';
    const STRATEGY_FLAT = 'flat';

    public static function disk(
        string|UploadType $uploadType,
        string|UploadBackend $backend,
        string|null $prefix = null,
        bool $throw = false,
    ): Filesystem {
        $uploadType = is_string($uploadType)
            ? self::type($uploadType)
            : $uploadType;
        $backend = is_string($backend) ? self::backend($backend) : $backend;

        throw_if(
            !$backend || !$uploadType,
            new Exception('Could not resolve upload disk'),
        );

        $location = new UploadDiskLocation($uploadType, $backend);

        return Storage::build([
            'driver' => $backend->flysystemDriver(),
            'throw' => $throw,
            'root' => $location->root,
            'prefix' =>
                $prefix ?? ($location->prefix ? $location->prefix : null),
            'url' => $location->url,
            'backend_id' => $backend->id,
            'visibility' => $uploadType->visibility,
            ...$backend->config,
            // if endpoint and custom domain are the same, make sure bucket is not included in url twice
            // this will enable temporary urls to work with custom domain
            'bucket_endpoint' =>
                isset($backend->config['endpoint']) &&
                $backend->customDomain &&
                $backend->customDomain === $backend->config['endpoint']
                    ? true
                    : false,
            'port' => isset($backend->config['port'])
                ? (int) $backend->config['port']
                : null,
        ]);
    }

    public static function getAllBackends(
        UploadType|string|null $uploadType = null,
    ): array {
        $backends = self::getUploadingSetting('backends') ?? [];
        $backends = empty($backends)
            ? self::getFallbackBackends()
            : self::mergeConfiguredFallbackBackend($backends);

        $allBackends = array_map(
            fn($backend) => new UploadBackend($backend),
            $backends,
        );

        $uploadType = is_string($uploadType)
            ? self::type($uploadType)
            : $uploadType;
        if ($uploadType) {
            return array_filter(
                $allBackends,
                fn($backend) => in_array($backend->id, $uploadType->backendIds),
            );
        }

        return $allBackends;
    }

    public static function backend(string $id): ?UploadBackend
    {
        return Arr::first(
            self::getAllBackends(),
            fn($backend) => $backend->id === $id,
        );
    }

    public static function getAllTypes(): array
    {
        $typeConfig = config('filesystems.upload_types');
        $userTypeConfig = self::getUploadingSetting('types') ?? [];
        $types = [];

        foreach ($typeConfig as $name => $config) {
            $resolvedConfig = [
                ...$config,
                ...$userTypeConfig[$name] ?? [],
            ];

            if (empty($resolvedConfig['backends'])) {
                $backend = self::resolveFallbackBackendForVisibility(
                    Arr::get($resolvedConfig, 'visibility'),
                );
                if ($backend) {
                    $resolvedConfig['backends'] = [$backend['id']];
                }
            }

            $types[] = new UploadType($name, [
                ...$resolvedConfig,
            ]);
        }

        return $types;
    }

    public static function type(string $name): ?UploadType
    {
        return Arr::first(
            self::getAllTypes(),
            fn($type) => $type->name === $name,
        );
    }

    public static function buildLegacyDisk(FilesFileEntry $entry)
    {
        $oldDriver = $entry->public
            ? config('filesystems.public_disk_driver')
            : config('filesystems.uploads_disk_driver');
        $backends = Uploads::getAllBackends();
        $backend = Arr::first(
            $backends,
            fn(UploadBackend $backend) => Str::startsWith(
                $oldDriver,
                $backend->type,
            ),
            $backends[0],
        );

        $root = null;
        if ($oldDriver === 'local') {
            $root = $entry->public
                ? public_path('storage')
                : storage_path('app/uploads');
        }

        $prefix = $entry->public ? $entry->disk_prefix : '';
        if ($oldDriver !== 'local') {
            $prefix = ($entry->public ? 'storage' : 'uploads') . '/' . $prefix;
        }
        $prefix = trim($prefix, '/');

        return Storage::build([
            'driver' => $backend->flysystemDriver(),
            'throw' => true,
            'root' => $root,
            'url' => $backend->customDomain,
            'prefix' => $prefix ? $prefix : null,
            ...$backend->config,
        ]);
    }

    protected static function getFallbackBackends(): array
    {
        $backends = [[
            'id' => 'local_default',
            'name' => 'Local',
            'type' => 'local',
        ]];

        if ($s3 = self::getConfiguredS3Backend()) {
            $backends[] = $s3;
        }

        return $backends;
    }

    protected static function mergeConfiguredFallbackBackend(array $backends): array
    {
        $selectedDrivers = [
            config('filesystems.uploads_disk_driver'),
            config('filesystems.public_disk_driver'),
        ];

        $shouldUseConfiguredS3 = collect($selectedDrivers)->contains(
            fn($driver) => is_string($driver) && Str::startsWith($driver, 's3'),
        );

        if (
            !$shouldUseConfiguredS3 ||
            Arr::first($backends, fn($backend) => ($backend['type'] ?? null) === 's3')
        ) {
            return $backends;
        }

        if ($s3 = self::getConfiguredS3Backend()) {
            $backends[] = $s3;
        }

        return $backends;
    }

    protected static function resolveFallbackBackendForVisibility(
        ?string $visibility,
    ): ?array {
        $driver =
            $visibility === 'public'
                ? config('filesystems.public_disk_driver')
                : config('filesystems.uploads_disk_driver');

        return Arr::first(
            self::getFallbackBackends(),
            fn($backend) => Str::startsWith($driver, $backend['type']),
            self::getFallbackBackends()[0] ?? null,
        );
    }

    protected static function getConfiguredS3Backend(): ?array
    {
        $key = self::envOrConfig('STORAGE_S3_KEY', 'services.s3.key');
        $secret = self::envOrConfig('STORAGE_S3_SECRET', 'services.s3.secret');
        $region = self::envOrConfig('STORAGE_S3_REGION', 'services.s3.region');
        $bucket = self::envOrConfig('STORAGE_S3_BUCKET', 'services.s3.bucket');

        if (!$key || !$secret || !$region || !$bucket) {
            return null;
        }

        $backend = [
            'id' => 'env_s3',
            'name' => 'S3',
            'type' => 's3',
            'config' => [
                'key' => $key,
                'secret' => $secret,
                'region' => $region,
                'bucket' => $bucket,
            ],
        ];

        if ($endpoint = self::envOrConfig('STORAGE_S3_ENDPOINT', 'services.s3.endpoint')) {
            $backend['config']['endpoint'] = $endpoint;
        }

        $proxy = self::envOrConfig('STORAGE_S3_PROXY');
        if ($proxy !== null && $proxy !== '') {
            $backend['config']['http'] = ['proxy' => $proxy];
        } elseif (self::envFlag('STORAGE_S3_DISABLE_ENV_PROXY')) {
            $backend['config']['http'] = ['proxy' => ''];
        }

        if (
            self::envFlag(
                'STORAGE_S3_USE_PATH_STYLE_ENDPOINT',
                'services.s3.use_path_style_endpoint',
            )
        ) {
            $backend['config']['use_path_style_endpoint'] = true;
        }

        if ($domain = self::envOrConfig('FILE_PREVIEW_ENDPOINT', 'filesystems.file_preview_endpoint')) {
            $backend['domain'] = $domain;
        }

        if (self::envFlag('STORAGE_S3_DIRECT_UPLOAD')) {
            $backend['config']['direct_upload'] = true;
        }

        return $backend;
    }

    protected static function envOrConfig(
        string $envKey,
        ?string $configKey = null,
    ): mixed {
        $value = env($envKey);
        if ($value !== null) {
            return $value;
        }

        return $configKey ? Config::get($configKey) : null;
    }

    protected static function envFlag(
        string $envKey,
        ?string $configKey = null,
    ): bool {
        return filter_var(
            self::envOrConfig($envKey, $configKey),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    protected static function getUploadingSetting(?string $key = null): mixed
    {
        static $uploading;

        if ($uploading === null) {
            $uploading = Setting::where('name', 'uploading')->first()?->value ?? [];
        }

        return $key ? Arr::get($uploading, $key) : $uploading;
    }
}
