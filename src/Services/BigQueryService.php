<?php

namespace App\Services;

use App\Models\Audio;
use App\Repositories\AudioRepositoryInterface;
use App\Repositories\BigQueryAudioRepository;
use App\Repositories\AudioRepository;

class BigQueryService
{
    private AudioRepositoryInterface $repository;

    public function __construct(?AudioRepositoryInterface $repository = null)
    {
        if ($repository !== null) {
            $this->repository = $repository;
            return;
        }

        // Prod → BigQuery | Outros ambientes (homolog, local) → PDO (AudioRepository)
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'local';

        if ($env === 'production') {
            $this->repository = new BigQueryAudioRepository();
        } else {
            $this->repository = new AudioRepository();
        }
    }

    public function save(Audio $audio): bool
    {
        return $this->repository->save($audio);
    }

    public function findById(string $id): ?Audio
    {
        return $this->repository->findById($id);
    }
}