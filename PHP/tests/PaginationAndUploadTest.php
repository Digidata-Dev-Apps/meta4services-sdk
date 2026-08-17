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
use Meta4Services\CloudServices\Files\PaginatedResult;
use Meta4Services\CloudServices\Files\UploadResult;
use PHPUnit\Framework\TestCase;

final class PaginationAndUploadTest extends TestCase
{
    public function testUploadBuildsUploadResultFromCreatedResponse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cloudservices');
        file_put_contents($tmp, 'payload');

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'uuid' => '11111111-1111-4111-8111-111111111111',
                    'status' => 'completed',
                    'original_name' => 'payload.txt',
                    'display_name' => 'payload.txt',
                    'mime_type' => 'text/plain',
                    'extension' => 'txt',
                    'size' => ['bytes' => 6],
                    'versions_count' => 1,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => $stack]),
        );

        $result = $client->upload($tmp, displayName: 'payload.txt');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $result->fileUuid);
        $this->assertTrue($result->isCompleted());

        unlink($tmp);
    }

    public function testListFilesReturnsPaginatedResult(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [[
                    'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    'original_name' => 'file.pdf',
                    'display_name' => 'file.pdf',
                    'mime_type' => 'application/pdf',
                    'extension' => 'pdf',
                    'size' => ['bytes' => 512],
                    'status' => 'completed',
                    'versions_count' => 1,
                ]],
                'meta' => [
                    'current_page' => 2,
                    'last_page' => 3,
                    'per_page' => 15,
                    'total' => 42,
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

        $result = $client->listFiles();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame(2, $result->currentPage());
        $this->assertSame(3, $result->lastPage());
        $this->assertSame(15, $result->perPage());
        $this->assertSame(42, $result->total());
        $this->assertCount(1, $result->items());
    }
}
