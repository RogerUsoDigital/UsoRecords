<?php

namespace App\Repositories;

use App\Models\Audio;
use Google\Cloud\BigQuery\BigQueryClient;
use App\Repositories\BigQueryConnection;

class BigQueryAudioRepository implements AudioRepositoryInterface
{
    private BigQueryClient $client;
    private string $datasetId;
    private string $tableId;

    public function __construct(
        ?BigQueryClient $client = null, 
        ?string $datasetId = null, 
        ?string $tableId = null
    ) {
        // Se o client não for passado (Injeção de dependência), usa a classe Singleton
        $this->client = $client ?? BigQueryConnection::getClient();
        
        // Puxa as configurações do .env como fallback
        $this->datasetId = $datasetId ?? $_ENV['BIGQUERY_DATASET'] ?? getenv('BIGQUERY_DATASET');
        $this->tableId = $tableId ?? $_ENV['BIGQUERY_TABLE'] ?? getenv('BIGQUERY_TABLE');
    }

    public function save(Audio $audio): bool
    {
        try {
            $sql = sprintf(
                "INSERT INTO `%s.%s` (
                    id, source_url, storage_path, file_name, mime_type, file_size, status, created_at, updated_at, expires_at
                ) VALUES (
                    @id, @source_url, @storage_path, @file_name, @mime_type, @file_size, @status, @created_at, @updated_at, @expires_at
                )",
                $this->datasetId,
                $this->tableId
            );

            $query = $this->client->query($sql)->parameters($audio->toArray());
            $result = $this->client->runQuery($query);

            return $result->isComplete();
             
        } catch (\Throwable $e) {
            error_log('Erro ao salvar no BigQuery: ' . $e->getMessage());
            return false;
        }
    }

    public function findById(string $id): ?Audio
    {
        try {
            $sql = sprintf(
                "SELECT * FROM `%s.%s` WHERE id = @id LIMIT 1",
                $this->datasetId,
                $this->tableId
            );

            $query = $this->client->query($sql)->parameters(['id' => $id]);
            $results = $this->client->runQuery($query);

            foreach ($results as $row) {
                return Audio::fromArray($row);
            }

            return null;
        } catch (\Throwable $e) {
            error_log('Erro ao buscar no BigQuery: ' . $e->getMessage());
            return null;
        }
    }
}