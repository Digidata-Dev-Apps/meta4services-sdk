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

final class AuthRefreshTest extends TestCase
{
    public function testRefreshTokenReplacesExpiredAccessToken(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'access_token' => 'first-access',
                    'refresh_token' => 'first-refresh',
                    'expires_in' => 900,
                    'token_type' => 'Bearer',
                    'user' => ['uuid' => 'user-uuid'],
                ],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'access_token' => 'refreshed-access',
                    'refresh_token' => 'refreshed-refresh',
                    'expires_in' => 900,
                    'token_type' => 'Bearer',
                    'user' => ['uuid' => 'user-uuid'],
                ],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [],
            ], JSON_THROW_ON_ERROR)),
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

        $client->login();
        $client->refreshToken();
        $client->listFiles();

        $this->assertSame('/api/v1/auth/login', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/auth/refresh', $history[1]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/files', $history[2]['request']->getUri()->getPath());
    }
}
