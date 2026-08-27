<?php

namespace App\Middleware;

use App\Utils\Config;

class AuthMiddleware
{
    public function handle(): void
    {
        $token = $this->getToken();

        $expectedToken = Config::get('app.api_token');

        if (empty($expectedToken)) {
            http_response_code(500);
            $this->json([
                'success' => false,
                'message' => 'Token da API não configurado no servidor.'
            ]);
            exit;
        }

        if (empty($token) || !hash_equals($expectedToken, $token)) {
            http_response_code(401);
            $this->json([
                'success' => false,
                'message' => 'Token de acesso inválido.'
            ]);
            exit;
        }
    }

    private function getToken(): ?string
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
