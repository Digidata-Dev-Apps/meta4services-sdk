<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Meta4Services\CloudServices\CloudServicesClient;
use Meta4Services\CloudServices\Config\ClientConfig;

$baseUrl = getenv('CLOUD_SERVICES_BASE_URL') ?: 'https://test.api.cloudservices.appsmold.com.br/api/v1';
$email = getenv('CLOUD_SERVICES_EMAIL') ?: 'ecoplena@digidata.com.br';
$password = getenv('CLOUD_SERVICES_PASSWORD') ?: '##ecoplena';
$storageUuid = getenv('CLOUD_SERVICES_STORAGE_UUID') ?: '61c04222-28f0-4cb4-9449-af4be446cd43';

$config = new ClientConfig(
    baseUrl: $baseUrl,
    defaultPageSize: 25,
    defaultStoragePath: 'documents',
    timeout: 60.0,
    connectTimeout: 10.0,
);

$client = new CloudServicesClient(
    email: $email,
    password: $password,
    storageUuid: $storageUuid,
    config: $config,
);

try {
    $token = $client->login();
    echo 'Token OK: ' . $token->accessToken() . PHP_EOL;

    $files = $client->listFiles();
    echo 'Arquivos retornados: ' . count($files->items()) . PHP_EOL;
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    if ($e instanceof \Meta4Services\CloudServices\Exceptions\CloudServicesException) {
        echo 'Status HTTP: ' . ($e->httpStatusCode() ?? 'n/a') . PHP_EOL;
        echo 'Mensagem da API: ' . ($e->apiMessage() ?? 'n/a') . PHP_EOL;
    }
}
