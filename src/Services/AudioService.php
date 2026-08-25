<?php

namespace App\Services;

use App\Validators\SourceUrlValidator;
use App\Utils\IdGenerator;
use App\Models\Audio;
use App\Repositories\AudioRepository;

class AudioService
{
    public function __construct(
        private ?SourceUrlValidator $urlValidator = null,
        private ?AudioDownloadService $downloadService = null,
        private ?StorageService $storageService = null,
        private ?AudioRepository $audioRepository = null
    ) {
        $this->urlValidator ??= new SourceUrlValidator();
        $this->downloadService ??= new AudioDownloadService();
        $this->storageService ??= new StorageService();
        $this->audioRepository ??= new AudioRepository();
    }

    public function store(?string $url): array
    {
        // 1. Validação da URL
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

        // 2. Gerar ID do áudio
        $id = IdGenerator::generate();

        // 3. Baixar áudio
        $downloadResult = $this->downloadService->download($url);
        if (!$downloadResult['success']) {
            return [
                'status' => $downloadResult['status'],
                'body' => [
                    'success' => false,
                    'message' => $downloadResult['message'] ?? 'Falha ao baixar o áudio.',
                ],
            ];
        }

        $downloadData = $downloadResult['data'];
        $stream = $downloadData['stream'];
        $mimeType = $downloadData['mime_type'];
        $fileSize = $downloadData['file_size'];

        // 4. Salvar no Storage
        $datePath = date('Y/m/d');
        $storagePath = "audio/{$datePath}/{$id}";

        $uploadSuccess = $this->storageService->upload($storagePath, $stream, $mimeType);

        if (!$uploadSuccess) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Falha ao salvar o áudio no armazenamento.',
                ],
            ];
        }

        // 5. Salvar metadados
        $host = getenv('APP_URL') ?: 'http://localhost:8080';
        $downloadUrl = "{$host}/v1/audio/{$id}";

        $audio = new Audio(
            id: $id,
            sourceUrl: $url,
            storagePath: $storagePath,
            fileName: $id,
            mimeType: $mimeType,
            fileSize: $fileSize,
            status: 'stored',
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
            expiresAt: null
        );

        $saveSuccess = $this->audioRepository->save($audio);

        if (!$saveSuccess) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Falha ao salvar metadados do áudio.',
                ],
            ];
        }

        // 6. Retornar resposta de sucesso
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'id' => $id,
                    'status' => 'stored',
                    'download_url' => $downloadUrl,
                ],
            ],
        ];
    }

    public function find(string $id): ?Audio
    {
        return $this->audioRepository->findById($id);
    }
}
