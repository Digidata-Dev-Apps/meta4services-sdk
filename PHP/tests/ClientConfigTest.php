<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Tests;

use InvalidArgumentException;
use Meta4Services\CloudServices\Config\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    public function testDefaultBaseUrlUsesCloudServicesApiV1(): void
    {
        $config = new ClientConfig();

        $this->assertSame('https://test.api.cloudservices.appsmold.com.br/api/v1', $config->baseUrl());
        $this->assertSame(25, $config->defaultPageSize());
    }

    public function testCustomBaseUrlCanBeProvided(): void
    {
        $config = new ClientConfig(baseUrl: 'https://example.com/api/v1');

        $this->assertSame('https://example.com/api/v1', $config->baseUrl());
    }

    public function testInvalidPageSizeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig(defaultPageSize: 0);
    }

    public function testDefaultStoragePathIsPreserved(): void
    {
        $config = new ClientConfig(defaultStoragePath: 'documents');

        $this->assertSame('documents', $config->defaultStoragePath());
    }
}
