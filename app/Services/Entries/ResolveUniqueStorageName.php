<?php

namespace App\Services\Entries;

use App\Models\FileEntry;
use Common\Files\Uploads\Uploads;

class ResolveUniqueStorageName
{
    public function execute(
        string $desiredName,
        ?string $diskPrefix,
        string $uploadType,
        string $backendId,
    ): string {
        $desiredName = trim($desiredName);
        $pathInfo = pathinfo($desiredName);
        $filename = $pathInfo['filename'] ?? $desiredName;
        $extension = isset($pathInfo['extension']) ? ".{$pathInfo['extension']}" : '';
        $candidate = $desiredName;
        $counter = 1;

        while ($this->exists($candidate, $diskPrefix, $uploadType, $backendId)) {
            $candidate = "{$filename} ({$counter}){$extension}";
            $counter++;
        }

        return $candidate;
    }

    private function exists(
        string $fileName,
        ?string $diskPrefix,
        string $uploadType,
        string $backendId,
    ): bool {
        $query = FileEntry::query()
            ->where('upload_type', $uploadType)
            ->where('backend_id', $backendId)
            ->where('file_name', $fileName);

        if ($diskPrefix) {
            $query->where('disk_prefix', $diskPrefix);
        } else {
            $query->whereNull('disk_prefix');
        }

        if ($query->exists()) {
            return true;
        }

        $path = trim(($diskPrefix ? "$diskPrefix/" : '') . $fileName, '/');

        return Uploads::disk($uploadType, $backendId)->exists($path);
    }
}
