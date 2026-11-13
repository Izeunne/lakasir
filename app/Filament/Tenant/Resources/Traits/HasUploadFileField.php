<?php

namespace App\Filament\Tenant\Resources\Traits;

use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;

trait HasUploadFileField
{
    private function getUploadedFileUsing(BaseFileUpload $component, string $file, string|array|null $storedFileNames)
    {
        /** @var Storage $storage */
        $storage = $component->getDisk();

        $shouldFetchFileInformation = $component->shouldFetchFileInformation();

        if ($shouldFetchFileInformation) {
            try {
                if (! $storage->exists($file)) {
                    return null;
                }
            } catch (UnableToCheckFileExistence) {
                return null;
            }
        }

        $url = $storage->url($file);

        // If using local/public disk, use root-relative URL to prevent CORS errors when accessing via 127.0.0.1 vs localhost
        if (config('filesystems.upload_disk') === 'public' || $component->getDiskName() === 'public') {
            $url = '/storage/' . ltrim($file, '/');
        } elseif (str_contains($url, '/storage/')) {
            $url = '/storage/' . ltrim(substr($url, strpos($url, '/storage/') + 9), '/');
        }

        return [
            'name' => basename($file),
            'size' => $shouldFetchFileInformation ? $storage->size($file) : 0,
            'type' => $shouldFetchFileInformation ? $storage->mimeType($file) : null,
            'url' => $url,
        ];
    }
}
