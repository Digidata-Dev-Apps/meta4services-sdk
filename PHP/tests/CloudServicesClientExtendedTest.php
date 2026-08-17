<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Meta4Services\CloudServices\CloudServicesClient;
use Meta4Services\CloudServices\Config\ClientConfig;
use Meta4Services\CloudServices\Exceptions\AuthenticationException;
use Meta4Services\CloudServices\Files\File;
use Meta4Services\CloudServices\Files\FileVersion;
use PHPUnit\Framework\TestCase;

final class CloudServicesClientExtendedTest extends TestCase
{
    public function testGetFileMapsResponseIntoFileDto(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'uuid' => '11111111-1111-4111-8111-111111111111',
                    'original_name' => 'report.pdf',
                    'display_name' => 'report.pdf',
                    'mime_type' => 'application/pdf',
                    'extension' => 'pdf',
                    'size' => ['bytes' => 2048],
                    'status' => 'completed',
                    'versions_count' => 2,
                    'created_at' => '2026-08-01T10:00:00Z',
                    'updated_at' => '2026-08-02T10:30:00Z',
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => HandlerStack::create($mock)]),
        );

        $file = $client->getFile('11111111-1111-4111-8111-111111111111');

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('report.pdf', $file->displayName);
        $this->assertSame('application/pdf', $file->mimeType);
        $this->assertSame(2, $file->versionsCount);
        $this->assertNotNull($file->createdAt);
    }

    public function testListVersionsMapsMultipleVersions(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    [
                        'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                        'version' => 1,
                        'storage_path' => 'documents/invoice-01.pdf',
                        'checksum_sha256' => 'abc123',
                        'size' => 512,
                        'created_at' => '2026-08-01T00:00:00Z',
                    ],
                    [
                        'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                        'version' => 2,
                        'storage_path' => 'documents/invoice-02.pdf',
                        'checksum_sha256' => 'def456',
                        'size' => 1024,
                        'created_at' => '2026-08-02T00:00:00Z',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => HandlerStack::create($mock)]),
        );

        $versions = $client->listVersions('11111111-1111-4111-8111-111111111111');

        $this->assertCount(2, $versions);
        $this->assertContainsOnlyInstancesOf(FileVersion::class, $versions);
        $this->assertSame(2, $versions[1]->version);
        $this->assertSame('documents/invoice-02.pdf', $versions[1]->storagePath);
    }

    public function testGetDownloadReturnsSignedUrlPayload(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'url' => 'https://example.com/download?token=abc',
                    'expires_at' => '2026-08-03T12:00:00Z',
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => HandlerStack::create($mock)]),
        );

        $download = $client->getDownload('11111111-1111-4111-8111-111111111111', version: 3);

        $this->assertSame('https://example.com/download?token=abc', $download['url']);
        $this->assertSame('2026-08-03T12:00:00Z', $download['expires_at']);
    }

    public function testDeleteFileUsesDeleteRequestWithoutBody(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => $stack]),
        );

        $client->deleteFile('11111111-1111-4111-8111-111111111111');

        $this->assertSame('DELETE', $history[0]['request']->getMethod());
        $this->assertSame(
            '/api/v1/files/11111111-1111-4111-8111-111111111111',
            $history[0]['request']->getUri()->getPath(),
        );
    }

    public function testAuthenticationExceptionsExposeHttpMetadata(): void
    {
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Credenciais inválidas.',
                'error' => 'unauthorized',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => HandlerStack::create($mock)]),
        );

        try {
            $client->listFiles();
            $this->fail('Expected AuthenticationException to be thrown.');
        } catch (AuthenticationException $exception) {
            $this->assertSame(401, $exception->httpStatusCode());
            $this->assertSame('Credenciais inválidas.', $exception->apiMessage());
        }
    }
}
