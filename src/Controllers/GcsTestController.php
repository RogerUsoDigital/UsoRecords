<?php

namespace App\Controllers;

use App\Services\Storage\GcsStorageDriver;

final class GcsTestController
{
    public function testUpload(): void
    {
        header('Content-Type: application/json');

        try {
            $config = [
                'project_id' => getenv('GCS_PROJECT_ID') ?: null,
                'bucket' => getenv('GCS_BUCKET') ?: 'usorecords-prod',
                'audio_path' => getenv('GCS_AUDIO_PATH') ?: 'audio',
                'key_file' => getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: null,
            ];

            $driver = new GcsStorageDriver($config);

            $success = $driver->testUpload();

            echo json_encode([
                'success' => $success,
                'config' => [
                    'project_id' => $config['project_id'],
                    'bucket' => $config['bucket'],
                    'key_file' => $config['key_file'],
                ],
            ]);

        } catch (\Throwable $exception) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}