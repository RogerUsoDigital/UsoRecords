<?php

use App\Controllers\TestController;
use App\Controllers\AudioController;
use App\Controllers\GcsTestController;
use App\Services\AudioService;
use App\Middleware\AuthMiddleware;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && $uri === '/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'API online'
    ]);
    exit;
}

if ($method === 'GET' && $uri === '/teste') {
    (new TestController())->index();
    exit;
}

if ($method === 'GET' && $uri === '/teste-gcs') {
    (new GcsTestController())->testUpload();
    exit;
}

if ($method === 'POST' && $uri === '/v1/records/audio') {
    (new AuthMiddleware())->handle();
    (new AudioController(new AudioService()))->store();
    exit;
}

if ($method === 'GET' && preg_match('#^/v1/audio/([0-9A-Z]{26})$#', $uri, $matches)) {
    (new AudioController(new AudioService()))->show($matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/v1/audio/([0-9A-Z]{26})/status$#', $uri, $matches)) {
    (new AudioController(new AudioService()))->status($matches[1]);
    exit;
}

// Rota do Worker (Invisível para usuários, chamada apenas pelo Cloud Tasks)
if ($method === 'POST' && $uri === '/v1/worker/audio-process') {
    
    // Trava anti-hacker: Verifica se a requisição realmente veio da fila do GCP
    if (!isset($_SERVER['HTTP_X_CLOUDTASKS_QUEUENAME'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas o Cloud Tasks pode acessar esta rota.']);
        exit;
    }

    // Lê os dados (ID e URL) que enviamos lá no QueueService
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Instancia o serviço e processa o download/upload em background
    $audioService = new AudioService();
    $result = $audioService->processAudioJob($payload);
    
    // Responde ao Cloud Tasks para ele saber se deu certo (200) ou se deve tentar de novo
    http_response_code($result['status']);
    echo json_encode(['message' => $result['message']]);
    exit;
}

// if ($method === 'GET' && preg_match('#^/v1/audio/local-download/([0-9A-Z]{26})$#', $uri, $matches)) {
//     (new AudioController(new AudioService()))->localDownload($matches[1]);
//     exit;
// }

http_response_code(404);

header('Content-Type: application/json');

echo json_encode([
    'success' => false,
    'message' => 'Rota não encontrada'
]);