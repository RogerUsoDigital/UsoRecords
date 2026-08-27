<?php

namespace App\Services\Storage;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class LocalStorageDriver implements StorageDriverInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function upload(string $path, StreamInterface $stream, string $mimeType): array
    {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Falha ao salvar o áudio no armazenamento.',
            ];
        }

        $dest = fopen($fullPath, 'wb');

        if ($dest === false) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Falha ao salvar o áudio no armazenamento.',
            ];
        }

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(1024 * 1024);

                if ($chunk === '') {
                    continue;
                }

                $bytesWritten = fwrite($dest, $chunk);

                if ($bytesWritten === false) {
                    fclose($dest);

                    return [
                        'success' => false,
                        'status' => 500,
                        'message' => 'Falha ao salvar o áudio no armazenamento.',
                    ];
                }
            }

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Upload realizado com sucesso.',
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Falha ao salvar o áudio no armazenamento.',
            ];
        } finally {
            fclose($dest);
        }
    }

    public function getSignedUrl(
        string $path,
        int $expiresMinutes
    ): string {
        $id = basename($path);

        $host = getenv('APP_URL') ?: 'http://localhost:8080';

        return $host . '/v1/audio/local-download/' . $id;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }
}