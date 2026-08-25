<?php

use App\Controllers\TestController;
use App\Controllers\AudioController;
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

if ($method === 'GET' && preg_match('#^/v1/audio/local-download/([0-9A-Z]{26})$#', $uri, $matches)) {
    (new AudioController(new AudioService()))->localDownload($matches[1]);
    exit;
}

http_response_code(404);

header('Content-Type: application/json');

echo json_encode([
    'success' => false,
    'message' => 'Rota não encontrada'
]);