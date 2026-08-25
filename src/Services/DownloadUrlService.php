<?php

namespace App\Services;

class DownloadUrlService
{
    public function __construct(private ?StorageService $storageService = null)
    {
        $this->storageService ??= new StorageService();
    }

    public function generateUrl(string $storagePath, int $expiresMinutes = 15): string
    {
        return $this->storageService->getSignedUrl($storagePath, $expiresMinutes);
    }
}