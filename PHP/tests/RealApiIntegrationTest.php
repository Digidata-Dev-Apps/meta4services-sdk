<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Tests;

use Meta4Services\CloudServices\CloudServicesClient;
use Meta4Services\CloudServices\Config\ClientConfig;
use Meta4Services\CloudServices\Exceptions\AuthenticationException;
use Meta4Services\CloudServices\Files\PaginatedResult;
use PHPUnit\Framework\TestCase;

final class RealApiIntegrationTest extends TestCase
{
    private function buildClient(
        ?string $email = null,
        ?string $password = null,
        ?string $storageUuid = null,
    ): CloudServicesClient {
        $baseUrl = getenv('CLOUD_SERVICES_BASE_URL')
            ?: 'https://test.api.cloudservices.appsmold.com.br/api/v1';
        $email ??= getenv('CLOUD_SERVICES_EMAIL') ?: 'ecoplena@digidata.com.br';
        $password ??= getenv('CLOUD_SERVICES_PASSWORD') ?: '##ecoplena';
        $storageUuid ??= getenv('CLOUD_SERVICES_STORAGE_UUID') ?: '61c04222-28f0-4cb4-9449-af4be446cd43';

        return new CloudServicesClient(
            email: $email,
            password: $password,
            storageUuid: $storageUuid,
            config: new ClientConfig(
                baseUrl: $baseUrl,
                defaultPageSize: 25,
                defaultStoragePath: 'documents',
                timeout: 60.0,
                connectTimeout: 10.0,
            ),
        );
    }

    public function testLoginAgainstRealApi(): void
    {
        $client = $this->buildClient();

        $token = $client->login();

        $this->assertNotSame('', $token->accessToken());
        $this->assertNotSame('', $token->refreshToken());
        $this->assertGreaterThan(0, $token->expiresIn());
    }

    public function testListFilesAgainstRealApi(): void
    {
        $client = $this->buildClient();
        $client->login();

        $files = $client->listFiles();

        $this->assertInstanceOf(PaginatedResult::class, $files);
        $this->assertIsArray($files->items());
        $this->assertGreaterThanOrEqual(0, $files->total());
    }

    public function testGetFirstFileAgainstRealApi(): void
    {
        $client = $this->buildClient();
        $client->login();
        $files = $client->listFiles();

        if ($files->items() === []) {
            $this->markTestSkipped('A API real não retornou arquivos para o storage configurado.');
        }

        $first = $files->items()[0];
        $file = $client->getFile($first->uuid);

        $this->assertSame($first->uuid, $file->uuid);
        $this->assertNotSame('', $file->displayName);
    }

    public function testUploadListDownloadAndShowAgainstRealApi(): void
    {
        $client = $this->buildClient();
        $client->login();
        $fixture = __DIR__ . '/../examples/image_test.jpeg';
        $displayName = 'sdk-integration-' . bin2hex(random_bytes(8)) . '.jpeg';
        $destination = tempnam(sys_get_temp_dir(), 'cloudservices-download-');
        $uploadedFileUuid = null;

        $this->assertFileExists($fixture);
        $this->assertNotFalse($destination);

        try {
            $upload = $client->upload(
                file: $fixture,
                storagePath: 'sdk-integration-tests',
                displayName: $displayName,
                metadata: ['test' => 'php-sdk-integration'],
            );
            $uploadedFileUuid = $upload->fileUuid;

            $this->assertNotSame('', $uploadedFileUuid);

            $listedFiles = $client->listFiles();

            $this->assertInstanceOf(PaginatedResult::class, $listedFiles);
            $this->assertIsArray($listedFiles->items());

            $file = $client->getFile($uploadedFileUuid);

            $this->assertSame($uploadedFileUuid, $file->uuid);
            $this->assertSame($displayName, $file->displayName);

            $download = $client->getDownload($uploadedFileUuid);

            $this->assertArrayHasKey('url', $download);
            $this->assertNotSame('', $download['url']);

            $client->downloadTo($uploadedFileUuid, $destination);

            $this->assertFileEquals($fixture, $destination);
        } finally {
            if ($uploadedFileUuid !== null) {
                $client->deleteFile($uploadedFileUuid);
            }

            if ($destination !== false && file_exists($destination)) {
                unlink($destination);
            }
        }
    }

    public function testInvalidCredentialsFailAgainstRealApi(): void
    {
        $client = $this->buildClient(
            email: 'ecoplena@digidata.com.br',
            password: 'senha-incorreta',
            storageUuid: '61c04222-28f0-4cb4-9449-af4be446cd43',
        );

        $this->expectException(AuthenticationException::class);

        $client->login();
    }
}
