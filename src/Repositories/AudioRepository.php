<?php

namespace App\Repositories;

use App\Models\Audio;
use PDO;

class AudioRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getConnection();
    }

    public function save(Audio $audio): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO audios (
                id, source_url, storage_path, file_name, mime_type, file_size, status, created_at, updated_at, expires_at
            ) VALUES (
                :id, :source_url, :storage_path, :file_name, :mime_type, :file_size, :status, :created_at, :updated_at, :expires_at
            )
        ");

        $data = $audio->toArray();
        return $stmt->execute($data);
    }

    public function findById(string $id): ?Audio
    {
        $stmt = $this->db->prepare("SELECT * FROM audios WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return Audio::fromArray($row);
    }

    public function updateStatus(string $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE audios
            SET status = :status, updated_at = :updated_at
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'status' => $status,
            'updated_at' => date('c'),
        ]);
    }
}
