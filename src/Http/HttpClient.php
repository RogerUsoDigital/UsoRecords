<?php

namespace App\Http;

use GuzzleHttp\Client;

final class HttpClient
{
    private Client $client;

    public function __construct(array $options = [])
    {
        $this->client = new Client(array_replace([
            'timeout' => $this->envFloat('HTTP_TIMEOUT', 120.0),
            'connect_timeout' => $this->envFloat('HTTP_CONNECT_TIMEOUT', 10.0),
            'allow_redirects' => false,
            'http_errors' => false,
        ], $options));
    }

    public function get(string $url, array $options = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->client->request('GET', $url, $options);
    }
    
    public function post(string $url, array $options = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->client->request('POST', $url, $options);
    }

    private function envFloat(string $name, float $default): float
    {
        $value = getenv($name);
        return $value !== false && is_numeric($value) && (float) $value > 0
            ? (float) $value
            : $default;
    }
}
