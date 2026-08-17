# Meta4Services Cloud Services SDK

SDK PHP para integração com a Cloud Services API, focado em operações de arquivos e armazenamento.

## Requisitos

- PHP 8.1+
- Composer
- Guzzle HTTP

## Instalação

```bash
composer require meta4services/cloud-services-sdk
```

## Exemplo rápido

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Meta4Services\CloudServices\CloudServicesClient;
use Meta4Services\CloudServices\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://test.api.cloudservices.appsmold.com.br/api/v1',
    defaultPageSize: 25,
    defaultStoragePath: 'documents',
    timeout: 60.0,
    connectTimeout: 10.0,
);

$client = new CloudServicesClient(
    email: 'usuario@digidata.com.br',
    password: 'senha-segura',
    storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
    config: $config,
);

$client->login();
$files = $client->listFiles();

foreach ($files->items() as $file) {
    echo $file->uuid() . ' - ' . $file->name() . PHP_EOL;
}
```

## Login e sessão

```php
$tokenSet = $client->login();
```

Esse retorno contém o access token, refresh token, expiração e os dados do usuário.

## Upload de arquivo

```php
$result = $client->upload(
    file: '/tmp/documento.pdf',
    storagePath: 'invoices/2026',
    displayName: 'documento.pdf',
    metadata: ['system' => 'ERP', 'document_id' => 123],
);

echo $result->fileUuid();
```

## Consultar arquivo

```php
$file = $client->getFile('a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2');
```

## Listar arquivos

```php
$files = $client->listFiles();
foreach ($files->items() as $file) {
    echo $file->name() . PHP_EOL;
}
```

## Listar versões

```php
$versions = $client->listVersions('a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2');
```

## Download de arquivo

```php
$download = $client->getDownload('a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2');
```

## Download de versão específica

```php
$download = $client->getDownload(
    fileUuid: 'a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2',
    version: 2,
);
```

## Download para destino local

```php
$client->downloadTo(
    fileUuid: 'a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2',
    destination: '/tmp/documento.pdf',
);
```

## Exclusão

```php
$client->deleteFile('a7f9d8e4-b067-4ec6-92de-7f6b0b77bcf2');
```

## Configuração por ambiente

Para evitar credenciais fixas no código, prefira variáveis de ambiente:

```php
$baseUrl = getenv('CLOUD_SERVICES_BASE_URL') ?: 'https://test.api.cloudservices.appsmold.com.br/api/v1';
$email = getenv('CLOUD_SERVICES_EMAIL');
$password = getenv('CLOUD_SERVICES_PASSWORD');
$storageUuid = getenv('CLOUD_SERVICES_STORAGE_UUID');
```

## Tratamento de erros

As exceptions da SDK derivam de `CloudServicesException` e trazem metadados HTTP quando disponíveis:

```php
try {
    $client->login();
} catch (\Throwable $e) {
    if ($e instanceof \Meta4Services\CloudServices\Exceptions\CloudServicesException) {
        echo $e->httpStatusCode();
        echo $e->apiMessage();
    }

    throw $e;
}
```

## Segurança

- nunca registre senhas;
- nunca registre access tokens ou refresh tokens;
- use HTTPS em produção;
- mantenha `storage_path` como valor lógico da API e não como path local do sistema;
- prefira variáveis de ambiente para credenciais.main, master

## Desenvolvimento

```bash
composer test
composer analyse
composer lint
```

