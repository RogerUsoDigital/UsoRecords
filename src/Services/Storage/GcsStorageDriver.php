<?php

namespace App\Services\Storage;

use App\Http\HttpClient;
use Psr\Http\Message\StreamInterface;
use Exception;

final class GcsStorageDriver implements StorageDriverInterface
{
    private ?string $projectId;
    private string $bucket;
    private string $audioPath;
    private ?string $keyFile;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(array $config)
    {
        $this->projectId = $config['project_id'] ?? null;
        $this->bucket = $config['bucket'] ?? 'usorecords-prod';
        $this->audioPath = rtrim($config['audio_path'] ?? 'audio', '/');
        $this->keyFile = $config['key_file'] ?? null;
    }

    public function upload(string $path, StreamInterface $stream, string $mimeType): bool
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $url = sprintf('https://storage.googleapis.com/upload/storage/v1/b/%s/o', urlencode($this->bucket));
        $objectName = ltrim($path, '/');

        $client = new HttpClient();
        $response = $client->get($url, [
            'method' => 'POST',
            'query' => [
                'uploadType' => 'media',
                'name' => $objectName,
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => $mimeType,
            ],
            'body' => $stream,
        ]);

        return $response->getStatusCode() === 200;
    }

    public function getSignedUrl(string $path, int $expiresMinutes): string
    {
        $creds = $this->loadCredentials();
        if (!$creds) {
            return '';
        }

        $privateKey = $creds['private_key'] ?? '';
        $clientEmail = $creds['client_email'] ?? '';

        if (!$privateKey || !$clientEmail) {
            return '';
        }

        $method = 'GET';
        $host = 'storage.googleapis.com';
        $canonicalUri = '/' . $this->bucket . '/' . ltrim($path, '/');

        $dateTime = gmdate('Ymd\THis\Z');
        $date = substr($dateTime, 0, 8);
        $expiresSeconds = $expiresMinutes * 60;

        $credential = $clientEmail . '/' . $date . '/auto/storage/goog4_request';

        $queryParams = [
            'X-Goog-Algorithm' => 'GOOG4-RSA-SHA256',
            'X-Goog-Credential' => $credential,
            'X-Goog-Date' => $dateTime,
            'X-Goog-Expires' => (string) $expiresSeconds,
            'X-Goog-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $canonicalQueryString = http_build_query($queryParams);
        $canonicalHeaders = "host:" . $host . "\n";
        $signedHeaders = "host";

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeaders,
            'UNSIGNED-PAYLOAD'
        ]);

        $stringToSign = implode("\n", [
            'GOOG4-RSA-SHA256',
            $dateTime,
            $date . '/auto/storage/goog4_request',
            hash('sha256', $canonicalRequest)
        ]);

        $signature = '';
        if (!openssl_sign($stringToSign, $signature, $privateKey, 'SHA256')) {
            return '';
        }

        $signatureHex = bin2hex($signature);

        return 'https://' . $host . $canonicalUri . '?' . $canonicalQueryString . '&X-Goog-Signature=' . $signatureHex;
    }

    public function delete(string $path): bool
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $objectName = ltrim($path, '/');
        $url = sprintf('https://storage.googleapis.com/storage/v1/b/%s/o/%s', urlencode($this->bucket), urlencode($objectName));

        $client = new HttpClient();
        // Since HttpClient only wraps GET request, we can instantiate Guzzle client directly or call GCS delete.
        // Let's use standard Guzzle client or custom request
        $gClient = new \GuzzleHttp\Client();
        try {
            $response = $gClient->request('DELETE', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'http_errors' => false,
            ]);
            return $response->getStatusCode() === 204;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt > time()) {
            return $this->accessToken;
        }

        $creds = $this->loadCredentials();
        if (!$creds) {
            return null;
        }

        $privateKey = $creds['private_key'] ?? '';
        $clientEmail = $creds['client_email'] ?? '';

        if (!$privateKey || !$clientEmail) {
            return null;
        }

        $now = time();
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claim = json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/devstorage.read_write',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlClaim = $this->base64UrlEncode($claim);

        $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
            return null;
        }

        $base64UrlSignature = $this->base64UrlEncode($signature);
        $jwt = $signatureInput . '.' . $base64UrlSignature;

        try {
            $gClient = new \GuzzleHttp\Client();
            $response = $gClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $body = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $body['access_token'] ?? null;
            $this->tokenExpiresAt = $now + ($body['expires_in'] ?? 3600) - 60; // 60s buffer
            return $this->accessToken;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function loadCredentials(): ?array
    {
        if (!$this->keyFile || !file_exists($this->keyFile)) {
            return null;
        }
        $content = file_get_contents($this->keyFile);
        return json_decode($content, true) ?: null;
    }
}
