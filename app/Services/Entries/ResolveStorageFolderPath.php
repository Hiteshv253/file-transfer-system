<?php

namespace App\Services\Entries;

use App\Models\FileEntry;

class ResolveStorageFolderPath
{
    public function execute(?int $parentId = null, ?string $relativePath = null): ?string
    {
        $segments = [];

        if ($parentId) {
            $segments = [...$segments, ...$this->getParentSegments($parentId)];
        }

        if ($relativePath) {
            $dirname = dirname(str_replace('\\', '/', $relativePath));
            if ($dirname !== '.' && $dirname !== '') {
                $segments = [...$segments, ...array_filter(explode('/', $dirname))];
            }
        }

        $segments = array_values(array_filter($segments, fn($segment) => $segment !== ''));

        return empty($segments) ? null : implode('/', $segments);
    }

    private function getParentSegments(int $parentId): array
    {
        $segments = [];
        $folder = FileEntry::find($parentId);

        while ($folder) {
            array_unshift($segments, $folder->name);
            $folder = $folder->parent_id ? FileEntry::find($folder->parent_id) : null;
        }

        return $segments;
    }
}
