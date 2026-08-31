<?php

namespace App\Services;

use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\CreateTaskRequest;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;

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
        $cloudRunUrl = rtrim(getenv('CLOUD_RUN_URL'), '/');
        $workerUrl = $cloudRunUrl . '/v1/worker/audio-process';

        $headers = array('Content-Type' => 'application/json');

        // Prepara a requisição POST que o Cloud Tasks fará para o seu worker 
        $httpRequest = (new HttpRequest())
            ->setUrl($workerUrl)
            ->setHttpMethod(HttpMethod::POST)
            ->setBody(json_encode($payload))
            ->setHeaders($headers);

        // Segurança OIDC: O token garante que o POST veio do Cloud Tasks autenticado
        if (!empty($this->serviceAccountEmail)) {
            $oidcToken = (new OidcToken())->setServiceAccountEmail($this->serviceAccountEmail);
            $httpRequest->setOidcToken($oidcToken);
        }

        // Agendamento: 5 minutos de delay
        $scheduledTime = new Timestamp();
        $scheduledTime->setSeconds(time() + (5 * 60));

        $task = (new Task())
        ->setHttpRequest($httpRequest)
        ->setScheduleTime($scheduledTime);

        try {
            // A forma moderna e exigida pelas versões novas do SDK do Cloud Tasks
            $request = (new CreateTaskRequest())
                ->setParent($queuePath)
                ->setTask($task);

            $client->createTask($request);
        } finally {
            $client->close();
        }
    }
}