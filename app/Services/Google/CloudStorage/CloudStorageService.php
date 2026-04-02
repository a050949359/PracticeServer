<?php

namespace App\Services\Google\CloudStorage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CloudStorageService
{
    public function upload(array $payload): array
    {
        $diskName = $this->diskName();
        $disk = $this->disk($diskName);

        $file = $payload['file'];

        if (! $file instanceof UploadedFile) {
            throw new RuntimeException('Upload file is invalid.');
        }

        $directory = trim((string) ($payload['directory'] ?? ''), '/');
        $fileName = (string) ($payload['file_name'] ?? $file->getClientOriginalName());
        $visibility = (string) ($payload['visibility'] ?? data_get(config('services'), 'cloud_storage.default_visibility', 'private'));

        $path = $disk->putFileAs($directory, $file, $fileName, [
            'visibility' => $visibility,
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Cloud storage upload failed.');
        }

        $url = null;

        try {
            $url = $disk->url($path);
        } catch (\Throwable) {
            $url = null;
        }

        return [
            'provider' => 'cloud_storage',
            'disk' => $diskName,
            'path' => $path,
            'file_name' => basename($path),
            'mime_type' => $disk->mimeType($path),
            'file_size' => $disk->size($path),
            'visibility' => $visibility,
            'url' => $url,
        ];
    }

    public function list(array $payload): array
    {
        $disk = $this->disk($this->diskName());
        $directory = trim((string) ($payload['directory'] ?? ''), '/');
        $recursive = (bool) ($payload['recursive'] ?? false);
        $page = (int) ($payload['page'] ?? 1);
        $perPage = (int) ($payload['per_page'] ?? 20);

        $paths = $recursive ? $disk->allFiles($directory) : $disk->files($directory);

        sort($paths);

        $total = count($paths);
        $lastPage = max((int) ceil($total / max($perPage, 1)), 1);
        $offset = ($page - 1) * $perPage;

        $items = array_map(function (string $path) use ($disk): array {
            return [
                'path' => $path,
                'file_name' => basename($path),
                'mime_type' => $disk->mimeType($path),
                'file_size' => $disk->size($path),
                'last_modified' => Carbon::createFromTimestamp($disk->lastModified($path))->toIso8601String(),
            ];
        }, array_slice($paths, $offset, $perPage));

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function download(string $path): array
    {
        $disk = $this->disk($this->diskName());

        if (! $disk->exists($path)) {
            throw new RuntimeException('Cloud storage file not found.');
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Cloud storage stream open failed.');
        }

        return [
            'path' => $path,
            'file_name' => basename($path),
            'mime_type' => (string) ($disk->mimeType($path) ?: 'application/octet-stream'),
            'stream' => $stream,
        ];
    }

    public function delete(string $path): array
    {
        $disk = $this->disk($this->diskName());

        if (! $disk->exists($path)) {
            throw new RuntimeException('Cloud storage file not found.');
        }

        $deleted = $disk->delete($path);

        if (! $deleted) {
            throw new RuntimeException('Cloud storage delete failed.');
        }

        return [
            'path' => $path,
            'deleted' => true,
        ];
    }

    private function diskName(): string
    {
        return (string) data_get(config('services'), 'cloud_storage.disk', 's3');
    }

    private function disk(string $diskName): FilesystemAdapter
    {
        $disk = Storage::disk($diskName);

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Cloud storage disk adapter is invalid.');
        }

        return $disk;
    }
}
