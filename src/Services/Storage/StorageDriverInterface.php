<?php

namespace App\Services\Storage;

use Psr\Http\Message\StreamInterface;

interface StorageDriverInterface
{
    public function upload(string $path, StreamInterface $stream, string $mimeType): array;

    public function getSignedUrl(string $path, int $expiresMinutes): string;

    public function delete(string $path): bool;
}
