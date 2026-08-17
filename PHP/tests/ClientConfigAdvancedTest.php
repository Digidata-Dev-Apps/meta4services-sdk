<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Tests;

use InvalidArgumentException;
use Meta4Services\CloudServices\Config\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigAdvancedTest extends TestCase
{
    public function testBaseUrlDoesNotDuplicateApiVersionSuffix(): void
    {
        $config = new ClientConfig(baseUrl: 'https://example.com/api/v1');

        $this->assertSame('https://example.com/api/v1', $config->baseUrl());
    }

    public function testStoragePathCanBeDefaulted(): void
    {
        $config = new ClientConfig(defaultStoragePath: 'documents');

        $this->assertSame('documents', $config->defaultStoragePath());
    }

    public function testStoragePathRejectsLocalFilesystemPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig(defaultStoragePath: '/tmp/document.pdf');
    }
}
