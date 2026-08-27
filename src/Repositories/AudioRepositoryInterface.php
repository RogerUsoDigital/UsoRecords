<?php

namespace App\Repositories;

use App\Models\Audio;

interface AudioRepositoryInterface
{
    /**
     * Persiste um novo áudio no repositório.
     */
    public function save(Audio $audio): bool;

    /**
     * Busca um áudio pelo seu ID único.
     */
    public function findById(string $id): ?Audio;
}