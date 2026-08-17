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
use PHPUnit\Framework\TestCase;

final class CloudServicesClientTest extends TestCase
{
    public function testClientUsesConfiguredBaseUrlForAuthenticationRequests(): void
    {
        $history = [];
        $container = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 900,
                    'token_type' => 'Bearer',
                    'user' => ['uuid' => 'user-uuid'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));
        $container->push(Middleware::history($history));

        $client = new CloudServicesClient(
            email: 'user@example.com',
            password: 'secret',
            storageUuid: '4f7a3dc0-2f0b-4df5-9e6a-7a7c5f5a6c31',
            config: new ClientConfig(baseUrl: 'https://example.com/api/v1'),
            httpClient: new HttpClient(['handler' => $container]),
        );

        $client->login();

        $this->assertSame('https://example.com/api/v1/auth/login', $history[0]['request']->getUri()->__toString());
    }
}
