<?php

namespace App\Repositories;

use Google\Cloud\BigQuery\BigQueryClient;

class BigQueryConnection
{
    private static ?BigQueryClient $client = null;

    /**
     * Retorna a instância única do BigQueryClient
     */
    public static function getClient(): BigQueryClient
    {
        if (self::$client === null) {
            // Pega o Project ID do .env
            $projectId = $_ENV['BIGQUERY_PROJECT_ID'] ?? getenv('BIGQUERY_PROJECT_ID');

            // Instancia o Client nativo do Google. 
            self::$client = new BigQueryClient([
                'projectId' => $projectId
            ]);
        }

        return self::$client;
    }
}