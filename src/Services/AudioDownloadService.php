<?php

namespace App\Services;

use App\Http\HttpClient;
use Psr\Http\Message\StreamInterface;
use Throwable;

final class AudioDownloadService
{
    private int $maxSizeBytes;

    /** @param list<string> $allowedMimeTypes */
    public function __construct(
        private ?HttpClient $httpClient = null,
        ?int $maxSizeBytes = null,
        private array $allowedMimeTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/aac', 'audio/mp4', 'audio/webm'],
    ) {
        $this->httpClient ??= new HttpClient();
        $this->maxSizeBytes = $maxSizeBytes ?? $this->envInt('AUDIO_MAX_SIZE_MB', 100) * 1024 * 1024;
    }

    /** @return array{success: bool, status: int, message?: string, data?: array{stream: StreamInterface, mime_type: string, file_size: ?int}} */
    public function download(string $url): array
    {
        try {
            $response = $this->httpClient->get($url, [
                'stream' => true,
                'allow_redirects' => false,
                'headers' => ['Accept' => 'audio/*'],
            ]);
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'status' => 504,
                'message' => 'Não foi possível baixar o áudio da fonte externa.',
            ];
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $response->getBody()->close();
            return ['success' => false, 'status' => 502, 'message' => 'A fonte externa não retornou um áudio válido.'];
        }

        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));

        $contentDisposition = $response->getHeaderLine('Content-Disposition');

        $mimeType = $this->resolveMimeType($contentType, $contentDisposition);

        if ($mimeType === null) {
            $response->getBody()->close();
            return [
                'success' => false,
                'status' => 422,
                'message' => 'O tipo de conteúdo recebido não é um áudio permitido.',
            ];
        }

        $contentLength = $response->getHeaderLine('Content-Length');
        $fileSize = is_numeric($contentLength) ? (int) $contentLength : null;
        if ($fileSize !== null && $fileSize > $this->maxSizeBytes) {
            $response->getBody()->close();
            return ['success' => false, 'status' => 422, 'message' => 'O áudio excede o tamanho máximo permitido.'];
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'stream' => $response->getBody(),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ],
        ];
    }

    private function isAllowedMimeType(string $mimeType): bool
    {
        foreach ($this->allowedMimeTypes as $allowed) {
            if ($mimeType === strtolower($allowed)) {
                return true;
            }
        }
        return false;
    }

    private function streamFitsLimit(StreamInterface $stream): bool
    {
        $size = 0;
        while (!$stream->eof()) {
            $chunk = $stream->read(1024 * 1024);
            $size += strlen($chunk);
            if ($size > $this->maxSizeBytes) {
                return false;
            }
        }
        $stream->rewind();
        return true;
    }

    private function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        return $value !== false && ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private function resolveMimeType(string $contentType, string $contentDisposition): ?string
    {
        if ($this->isAllowedMimeType($contentType)) {
            return $contentType;
        }

        if ($contentType !== 'application/octet-stream') {
            return null;
        }

        $extension = $this->extractExtensionFromDisposition(
            $contentDisposition
        );

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            'mp4' => 'audio/mp4',
            'webm' => 'audio/webm',
            default => null,
        };
    }

    private function extractExtensionFromDisposition(string $contentDisposition): ?string
    {
        if (
            preg_match(
                '/filename=["\']?([^"\';]+)["\']?/i',
                $contentDisposition,
                $matches
            )
        ) {
            $filename = trim($matches[1]);

            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            return $extension !== ''
                ? strtolower($extension)
                : null;
        }

        return null;
    }
}
