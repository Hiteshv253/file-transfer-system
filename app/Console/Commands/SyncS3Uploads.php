<?php

namespace App\Console\Commands;

use Common\Settings\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncS3Uploads extends Command
{
    protected $signature = 'uploads:sync-s3 {--name=S3 : Backend display name}';

    protected $description =
        'Create or update the S3 upload backend from .env and assign all upload types to it.';

    public function handle(): int
    {
        if (!Schema::hasTable('settings')) {
            $this->error(
                'Settings table not found. Run database migrations and installation first.',
            );
            return self::FAILURE;
        }

        $config = $this->getS3ConfigFromEnv();
        if (!$config) {
            $this->error(
                'Missing S3 env values. Please set STORAGE_S3_KEY, STORAGE_S3_SECRET, STORAGE_S3_REGION and STORAGE_S3_BUCKET first.',
            );
            return self::FAILURE;
        }

        $setting = Setting::firstOrCreate(
            ['name' => 'uploading'],
            ['value' => ['chunk_size' => 2097152, 'backends' => [], 'types' => []]],
        );

        $uploading = $setting->value ?? [];
        $uploading['chunk_size'] = $uploading['chunk_size'] ?? 2097152;
        $uploading['backends'] = $uploading['backends'] ?? [];
        $uploading['types'] = $uploading['types'] ?? [];

        $existingBackendIndex = collect($uploading['backends'])->search(
            fn($backend) => ($backend['type'] ?? null) === 's3',
        );

        $backendId = $existingBackendIndex !== false
            ? $uploading['backends'][$existingBackendIndex]['id']
            : Str::random(10);

        $backend = [
            'id' => $backendId,
            'name' => (string) $this->option('name'),
            'type' => 's3',
            'config' => $config,
        ];

        if ($domain = env('FILE_PREVIEW_ENDPOINT')) {
            $backend['domain'] = $domain;
        }

        if ($existingBackendIndex !== false) {
            $uploading['backends'][$existingBackendIndex] = $backend;
        } else {
            $uploading['backends'][] = $backend;
        }

        foreach (config('filesystems.upload_types', []) as $typeName => $typeConfig) {
            $uploading['types'][$typeName] = array_filter([
                ...($uploading['types'][$typeName] ?? []),
                'backends' => [$backendId],
                'max_file_size' => Arr::get(
                    $uploading['types'][$typeName] ?? [],
                    'max_file_size',
                    Arr::get($typeConfig, 'defaults.max_file_size'),
                ),
                'accept' => Arr::get(
                    $uploading['types'][$typeName] ?? [],
                    'accept',
                    Arr::get($typeConfig, 'defaults.accept'),
                ),
            ], fn($value) => $value !== null);
        }

        $setting->update(['value' => $uploading]);
        Cache::forget('settings.public');

        $this->info('S3 backend synced successfully and assigned to all upload types.');
        $this->line('Backend ID: ' . $backendId);
        $this->line('Bucket: ' . $config['bucket']);
        $this->line('Region: ' . $config['region']);

        if (!empty($config['endpoint'])) {
            $this->line('Endpoint: ' . $config['endpoint']);
        }

        return self::SUCCESS;
    }

    protected function getS3ConfigFromEnv(): ?array
    {
        $key = env('STORAGE_S3_KEY');
        $secret = env('STORAGE_S3_SECRET');
        $region = env('STORAGE_S3_REGION');
        $bucket = env('STORAGE_S3_BUCKET');

        if (!$key || !$secret || !$region || !$bucket) {
            return null;
        }

        $config = [
            'key' => $key,
            'secret' => $secret,
            'region' => $region,
            'bucket' => $bucket,
        ];

        if ($endpoint = env('STORAGE_S3_ENDPOINT')) {
            $config['endpoint'] = $endpoint;
        }

        $proxy = env('STORAGE_S3_PROXY');
        if ($proxy !== null && $proxy !== '') {
            $config['http'] = ['proxy' => $proxy];
        } elseif ($this->envFlag('STORAGE_S3_DISABLE_ENV_PROXY')) {
            $config['http'] = ['proxy' => ''];
        }

        if ($this->envFlag('STORAGE_S3_USE_PATH_STYLE_ENDPOINT')) {
            $config['use_path_style_endpoint'] = true;
        }

        if ($this->envFlag('STORAGE_S3_DIRECT_UPLOAD')) {
            $config['direct_upload'] = true;
        }

        return $config;
    }

    protected function envFlag(string $key): bool
    {
        return filter_var(env($key), FILTER_VALIDATE_BOOLEAN);
    }
}
