<?php

namespace App\Controllers;

use App\Services\AudioService;
use App\Services\DownloadUrlService;
use App\Utils\Config;

class AudioController
{
    public function __construct(private ?AudioService $audioService = null)
    {
        $this->audioService ??= new AudioService();
    }

    public function store(): void
    {
        header('Content-Type: application/json');

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        $url = $input['url'] ?? null;

        $result = $this->audioService->store($url);

        http_response_code($result['status']);

        echo json_encode($result['body']);
    }

    public function show(string $id): void
    {
        $audio = $this->audioService->find($id);
        if (!$audio) {
            http_response_code(202);

            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (str_contains($accept, 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'status' => 'processing',
                    'message' => 'O áudio ainda está sendo processado. Tente novamente em alguns instantes.'
                ]);
                return;
            }

            require_once __DIR__ . '/../Views/Default/processing.php';
            return;
        }

        $downloadUrlService = new DownloadUrlService();
        $signedUrl = $downloadUrlService->generateUrl($audio->storagePath);

        if (empty($signedUrl)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível gerar a URL de download.'
            ]);
            return;
        }

        // Define se é para baixar ou ouvir (ex: http://localhost:8080/v1/audio/ID?download=1)
        $isDownload = isset($_GET['download']) && $_GET['download'] === '1';
        $disposition = $isDownload ? 'attachment' : 'inline';

        // Nome do arquivo base
        $filename = basename($audio->storagePath);

        // Define o MimeType correto para tocar no navegador
        $mimeType = $audio->mimeType ?: 'audio/wav';

        $extensionMap = [
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
        ];

        $extension = $extensionMap[$mimeType] ?? null;

        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);

        $filename = $filenameWithoutExtension . '.' . $extension;

        // Limpar qualquer saída anterior (BOM, espaços, etc)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header_remove();

        // Cabeçalhos HTTP
        header('HTTP/1.1 200 OK');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        // Le o arquivo do Google Storage e envia direto pro cliente
        $stream = @fopen($signedUrl, 'rb');
        if ($stream) {
            fpassthru($stream);
            fclose($stream);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Erro ao transmitir o áudio.']);
        }
        exit;
    }

    public function status(string $id): void
    {
        $audio = $this->audioService->find($id);
        if (!$audio) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Áudio não encontrado.'
            ]);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $audio->id,
                'status' => $audio->status,
                'mime_type' => $audio->mimeType,
                'file_size' => $audio->fileSize,
                'created_at' => $audio->createdAt,
                'updated_at' => $audio->updatedAt,
            ]
        ]);
    }

    public function localDownload(string $id): void
    {
        $audio = $this->audioService->find($id);
        if (!$audio) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Áudio não encontrado.'
            ]);
            return;
        }

        $basePath = Config::get('storage.local.path', __DIR__ . '/../../storage');
        $fullPath = $basePath . '/' . ltrim($audio->storagePath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Arquivo físico não encontrado no servidor.'
            ]);
            return;
        }

        header('Content-Type: ' . ($audio->mimeType ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
    }

    public function index()
    {
        // Define o código HTTP 200 de sucesso
        http_response_code(200);

        // Se o seu index.php for um template PHP simples
        require_once __DIR__ . '/../Views/Default/index.php';
        exit;
    }
}