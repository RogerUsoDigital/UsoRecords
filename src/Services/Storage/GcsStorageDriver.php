<?php

namespace App\Services\Storage;

use App\Http\HttpClient;
use Psr\Http\Message\StreamInterface;
use Google\Cloud\Storage\StorageClient;
use Exception;

final class GcsStorageDriver implements StorageDriverInterface
{
    private StorageClient $client;
    private string $bucketName;

    public function __construct(array $config)
    {
        $this->bucketName = $config['bucket'] ?? 'usorecords';
        
        $clientConfig = [];
        
        // Se houver um arquivo configurado (ex: ambiente local), usamos ele.
        // Em produção, se for null ou vazio, o SDK tentará usar as 
        // Application Default Credentials (ADC) automaticamente.
        if (!empty($config['key_file']) && file_exists($config['key_file'])) {
            $clientConfig['keyFilePath'] = $config['key_file'];
        }

        $this->client = new StorageClient($clientConfig);
    }

    /**
     * @return array{
     *     success: bool,
     *     status: int|null,
     *     message: string|null,
     *     data?: array
     * }
     */
    public function upload(string $path, StreamInterface $stream, string $mimeType): array 
    {
        $objectName = ltrim($path, '/');

        try {
            $bucket = $this->client->bucket($this->bucketName);
            
            $object = $bucket->upload($stream, [
                'name' => $objectName,
                'metadata' => [
                    'contentType' => $mimeType,
                ]
            ]);

            $info = $object->info();

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Upload realizado com sucesso.',
                'data' => [
                    'object' => $info['name'] ?? $objectName,
                    'bucket' => $info['bucket'] ?? $this->bucketName,
                    'size' => $info['size'] ?? null,
                    'generation' => $info['generation'] ?? null,
                ],
            ];
        } catch (\Throwable $exception) {
            error_log('GCS upload exception: ' . $exception->getMessage());

            return [
                'success' => false,
                'status' => 502,
                'message' => 'Erro ao comunicar com o Google Cloud Storage: ' . $exception->getMessage(),
            ];
        }
    }

    public function getSignedUrl(string $path, int $expiresMinutes): string
    {
        try {
            $objectName = ltrim($path, '/');
            $bucket = $this->client->bucket($this->bucketName);
            $object = $bucket->object($objectName);

            $expiresAt = new \DateTimeImmutable('+' . $expiresMinutes . ' minutes');

            // Nota: se rodando via ADC sem chave privada (GCP Server),
            // certifique-se que a Service Account possui a role: 
            // "Service Account Token Creator" (iam.serviceAccounts.signBlob)
            return $object->signedUrl($expiresAt);
        } catch (\Throwable $e) {
            error_log('GCS Signed URL exception: ' . $e->getMessage());
            return '';
        }
    }

    public function delete(string $path): bool
    {
        try {
            $objectName = ltrim($path, '/');
            $bucket = $this->client->bucket($this->bucketName);
            $object = $bucket->object($objectName);
            
            if ($object->exists()) {
                $object->delete();
            }
            
            return true;
        } catch (\Throwable $e) {
            error_log('GCS delete exception: ' . $e->getMessage());
            return false;
        }
    }

    public function testUpload(): array
    {
        try {
            $bucket = $this->client->bucket($this->bucketName);
            
            $content = 'USORecords GCS OK - ' . date('Y-m-d H:i:s');
            $objectName = 'teste/usorecords.txt';

            $object = $bucket->upload($content, [
                'name' => $objectName,
                'metadata' => [
                    'contentType' => 'text/plain',
                ]
            ]);

            return [
                'success' => true,
                'step' => 'upload',
                'http_status' => 200,
                'response' => $object->info(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'step' => 'exception',
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }
    }
}
