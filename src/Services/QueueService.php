<?php

namespace App\Services;

use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;

class QueueService
{
    private string $projectId;
    private string $location;
    private string $queueName;
    private string $serviceAccountEmail;

    public function __construct()
    {
        // Variáveis que você configurará no painel do Cloud Run
        $this->projectId = getenv('GCP_PROJECT_ID');
        $this->location = getenv('GCP_LOCATION') ?: 'us-central1';
        $this->queueName = getenv('GCP_QUEUE_NAME') ?: 'audio-processing-queue';
        $this->serviceAccountEmail = getenv('GCP_SERVICE_ACCOUNT_EMAIL');
    }

    public function dispatchAudioProcessing(array $payload): void
    {
        $client = new CloudTasksClient();
        
        // Monta o endereço exato da fila dentro do GCP
        $queuePath = $client->queueName($this->projectId, $this->location, $this->queueName);

        // A rota oculta "worker" que criaremos na sua API para processar o download
        $workerUrl = rtrim(getenv('APP_URL'), '/') . '/v1/worker/audio-process';

        // Prepara a requisição POST que o Cloud Tasks fará para o seu worker 
        $httpRequest = (new HttpRequest())
            ->setUrl($workerUrl)
            ->setHttpMethod(HttpMethod::POST)
            ->setBody(json_encode($payload))
            ->putHeaders('Content-Type', 'application/json');

        // Segurança OIDC: O token garante que o POST veio do Cloud Tasks autenticado
        if (!empty($this->serviceAccountEmail)) {
            $oidcToken = (new OidcToken())->setServiceAccountEmail($this->serviceAccountEmail);
            $httpRequest->setOidcToken($oidcToken);
        }

        $task = (new Task())->setHttpRequest($httpRequest);

        try {
            // Envia a tarefa para o Google Cloud
            $client->createTask($queuePath, $task);
        } finally {
            $client->close();
        }
    }
}