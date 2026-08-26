<?php

namespace App\Services;

use App\Utils\Config;
use App\Services\Storage\StorageDriverInterface;
use App\Services\Storage\LocalStorageDriver;
use App\Services\Storage\GcsStorageDriver;
use Psr\Http\Message\StreamInterface;

class StorageService
{
    private StorageDriverInterface $driver;

    public function __construct(?StorageDriverInterface $driver = null)
    {
        if ($driver !== null) {
            $this->driver = $driver;
            return;
        }

        $driverType = Config::get('storage.driver');

        if ($driverType === 'gcs') {
            $config = Config::get('storage.gcs', []);
            $this->driver = new GcsStorageDriver($config);
        } else {
            $basePath = Config::get('storage.local.path', __DIR__ . '/../../storage');
            $this->driver = new LocalStorageDriver($basePath);
        }
    }

    public function upload(string $path, StreamInterface $stream, string $mimeType): array
    {
        return $this->driver->upload($path, $stream, $mimeType);
    }

    public function getSignedUrl(string $path, int $expiresMinutes = 15): string
    {
        return $this->driver->getSignedUrl($path, $expiresMinutes);
    }

    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }
}