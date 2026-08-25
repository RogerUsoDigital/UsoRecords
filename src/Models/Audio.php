<?php

namespace App\Models;

final class Audio
{
    public function __construct(
        public string $id,
        public string $sourceUrl,
        public string $storagePath,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $fileSize,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
        public ?string $expiresAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            sourceUrl: $data['source_url'],
            storagePath: $data['storage_path'],
            fileName: $data['file_name'] ?? null,
            mimeType: $data['mime_type'] ?? null,
            fileSize: isset($data['file_size']) ? (int) $data['file_size'] : null,
            status: $data['status'],
            createdAt: $data['created_at'],
            updatedAt: $data['updated_at'],
            expiresAt: $data['expires_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_url' => $this->sourceUrl,
            'storage_path' => $this->storagePath,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
