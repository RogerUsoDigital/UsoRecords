<?php

namespace App\Services\Storage;

use App\Http\HttpClient;
use Psr\Http\Message\StreamInterface;
use Google\Cloud\Storage\StorageClient;
use Exception;

final class GcsStorageDriver implements StorageDriverInterface
{
    private string $bucket;
    private ?string $keyFile;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'] ?? 'usorecords-prod';
        $this->keyFile = $config['key_file'] ?? null;
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
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Não foi possível obter o token de acesso ao Google Cloud Storage.',
            ];
        }

        $url = sprintf('https://storage.googleapis.com/upload/storage/v1/b/%s/o', urlencode($this->bucket));
        $objectName = ltrim($path, '/');

        try {
            $client = new HttpClient();

            $response = $client->post($url, [
                'query' => [
                    'uploadType' => 'media',
                    'name' => $objectName,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => $mimeType,
                ],
                'body' => $stream,
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            error_log(
                'GCS upload exception: ' . $exception->getMessage()
            );

            return [
                'success' => false,
                'status' => 502,
                'message' => 'Erro ao comunicar com o Google Cloud Storage.',
            ];
        }

        $status = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($status < 200 || $status >= 300) {
            error_log(
                'GCS upload failed [' . $status . ']: ' . $body
            );

            $error = json_decode($body, true);

            return [
                'success' => false,
                'status' => $status,
                'message' => $error['error']['message']
                    ?? 'O Google Cloud Storage recusou o upload.',
                'data' => [
                    'storage_status' => $status,
                    'storage_response' => $error,
                ],
            ];
        }

        $data = json_decode($body, true);

        return [
            'success' => true,
            'status' => $status,
            'message' => 'Upload realizado com sucesso.',
            'data' => [
                'object' => $data['name'] ?? $objectName,
                'bucket' => $data['bucket'] ?? $this->bucket,
                'size' => $data['size'] ?? null,
                'generation' => $data['generation'] ?? null,
            ],
        ];
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
        if (
            $this->accessToken !== null &&
            $this->tokenExpiresAt !== null &&
            $this->tokenExpiresAt > time()
        ) {
            return $this->accessToken;
        }

        error_log('GCS AUTH: iniciando autenticação');

        $creds = $this->loadCredentials();

        if (!$creds) {
            error_log('GCS AUTH: não foi possível carregar credentials.json');

            return null;
        }

        error_log('GCS AUTH: credentials carregadas');
        error_log(
            'GCS AUTH: client_email=' .
            ($creds['client_email'] ?? 'NÃO INFORMADO')
        );
        error_log(
            'GCS AUTH: project_id=' .
            ($creds['project_id'] ?? 'NÃO INFORMADO')
        );

        $privateKey = $creds['private_key'] ?? '';
        $clientEmail = $creds['client_email'] ?? '';

        if (!$privateKey) {
            error_log('GCS AUTH: private_key não encontrada');

            return null;
        }

        if (!$clientEmail) {
            error_log('GCS AUTH: client_email não encontrado');

            return null;
        }

        $now = time();

        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);

        $claim = json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/devstorage.read_write',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);

        if ($header === false || $claim === false) {
            error_log('GCS AUTH: falha ao gerar JSON do JWT');

            return null;
        }

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlClaim = $this->base64UrlEncode($claim);

        $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;

        $signature = '';

        if (
            !openssl_sign(
                $signatureInput,
                $signature,
                $privateKey,
                OPENSSL_ALGO_SHA256
            )
        ) {
            error_log(
                'GCS AUTH: openssl_sign falhou: ' .
                openssl_error_string()
            );

            return null;
        }

        error_log('GCS AUTH: JWT assinado com sucesso');

        $base64UrlSignature = $this->base64UrlEncode($signature);

        $jwt = $signatureInput . '.' . $base64UrlSignature;

        try {
            $gClient = new \GuzzleHttp\Client();

            $response = $gClient->request(
                'POST',
                'https://oauth2.googleapis.com/token',
                [
                    'form_params' => [
                        'grant_type' =>
                            'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt,
                    ],
                    'http_errors' => false,
                ]
            );

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            error_log('GCS AUTH: OAuth status=' . $status);

            if ($status !== 200) {
                error_log(
                    'GCS AUTH: OAuth response=' . $body
                );

                return null;
            }

            $data = json_decode($body, true);

            if (!is_array($data)) {
                error_log('GCS AUTH: resposta OAuth inválida');

                return null;
            }

            $this->accessToken = $data['access_token'] ?? null;

            if (!$this->accessToken) {
                error_log(
                    'GCS AUTH: access_token não veio na resposta OAuth'
                );

                return null;
            }

            $expiresIn = (int) ($data['expires_in'] ?? 3600);

            $this->tokenExpiresAt = $now + $expiresIn - 60;

            error_log('GCS AUTH: access token obtido com sucesso');

            return $this->accessToken;

        } catch (\Throwable $e) {
            error_log(
                'GCS AUTH: exception=' . $e->getMessage()
            );

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

    public function testUpload(): array
    {
        try {
            $token = $this->getAccessToken();

            if (!$token) {
                return [
                    'success' => false,
                    'step' => 'getAccessToken',
                    'message' => 'Não foi possível obter o access token.'
                ];
            }

            $stream = \GuzzleHttp\Psr7\Utils::streamFor(
                'USORecords GCS OK - ' . date('Y-m-d H:i:s')
            );

            $url = sprintf(
                'https://storage.googleapis.com/upload/storage/v1/b/%s/o',
                urlencode($this->bucket)
            );

            $client = new \GuzzleHttp\Client();

            $response = $client->request('POST', $url, [
                'query' => [
                    'uploadType' => 'media',
                    'name' => 'teste/usorecords.txt',
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $stream,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            return [
                'success' => $status >= 200 && $status < 300,
                'step' => 'upload',
                'http_status' => $status,
                'response' => json_decode($body, true) ?? $body,
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
