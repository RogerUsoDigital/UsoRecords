<?php

namespace App\Services;

use App\Validators\SourceUrlValidator;
use App\Utils\IdGenerator;
use App\Models\Audio;
use App\Services\QueueService;
use App\Repositories\AudioRepository;

class AudioService
{
    public function __construct(
        private ?SourceUrlValidator $urlValidator = null,
        private ?AudioDownloadService $downloadService = null,
        private ?StorageService $storageService = null,
        private ?BigQueryService $bigQueryService = null,
        private ?QueueService $queueService = null
    ) {
        $this->urlValidator ??= new SourceUrlValidator();
        $this->downloadService ??= new AudioDownloadService();
        $this->storageService ??= new StorageService();
        $this->bigQueryService ??= new BigQueryService();
        $this->queueService ??= new QueueService();
    }

    public function store(?string $url): array
    {
        // 1. Validação da URL (Permanece igual, precisamos garantir que é válida antes de aceitar)
        $validation = $this->urlValidator->validate($url);
        if (!$validation['valid']) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => $validation['message'],
                ],
            ];
        }

        $url = trim($url);

        // 2. Gerar ID do áudio imediatamente
        $id = IdGenerator::generate();

        // 3. Montar as URLs que o cliente vai usar no futuro
        $host = getenv('APP_URL') ?: 'http://localhost:8080';
        $downloadUrl = "{$host}/v1/audio/{$id}";

        // 4. Enviar para a Fila
        try {
            $this->queueService->dispatchAudioProcessing([
                'id' => $id,
                'url' => $url
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao enviar para a fila: ' . $e->getMessage());
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Não foi possível enfileirar o áudio para processamento.',
                ],
            ];
        }

        // 5. Retornar resposta Imediata de Sucesso
        return [
            'status' => 200, // 202 Accepted: Padrão REST para "Aceito, mas processando"
            'body' => [
                'download_url' => $downloadUrl . "?download=1",
            ],
        ];
    }

    public function find(string $id): ?Audio
    {
        return $this->bigQueryService->findById($id);
    }

    public function processAudioJob(array $payload): array
    {
        $id = $payload['id'] ?? null;
        $url = $payload['url'] ?? null;

        if (!$id || !$url) {
            return ['status' => 400, 'message' => 'Faltam dados na requisição.'];
        }

        try {
            // 1. Baixar áudio
            $downloadResult = $this->downloadService->download($url);
            if (!$downloadResult['success']) {
                throw new \Exception($downloadResult['message'] ?? 'Falha ao baixar áudio.');
            }

            $stream = $downloadResult['data']['stream'];
            $mimeType = $downloadResult['data']['mime_type'];
            $fileSize = $downloadResult['data']['file_size'];

            // 2. Salvar no Storage
            $datePath = date('Y/m/d');
            $storagePath = "audio/{$datePath}/{$id}";
            
            $storageResult = $this->storageService->upload($storagePath, $stream, $mimeType);
            if (!$storageResult['success']) {
                throw new \Exception($storageResult['message'] ?? 'Falha no Storage.');
            }

            // 3. Salvar metadados atualizando o status para 'stored'
            $audio = new Audio(
                id: $id,
                sourceUrl: $url,
                storagePath: $storagePath,
                fileName: $id,
                mimeType: $mimeType,
                fileSize: $fileSize,
                status: 'stored', // Arquivo finalmente salvo!
                createdAt: date('Y-m-d H:i:s'),
                updatedAt: date('Y-m-d H:i:s'),
                expiresAt: null
            );

            $saveSuccess = $this->bigQueryService->save($audio);
            if (!$saveSuccess) {
                throw new \Exception('Falha ao salvar no BigQuery.');
            }

            // Retornar 200 faz a fila entender que a tarefa acabou com sucesso
            return ['status' => 200, 'message' => 'Processado com sucesso'];

        } catch (\Throwable $e) {
            error_log("Worker Erro [ID: {$id}]: " . $e->getMessage());
            // Retornar status de erro (ex: 500) faz o Cloud Tasks tentar novamente mais tarde
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
