<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Config;

use InvalidArgumentException;
use Meta4Services\CloudServices\Auth\TokenStoreInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ClientConfig
{
    private const DEFAULT_BASE_URL = 'https://test.api.cloudservices.appsmold.com.br/api/v1';

    private string $baseUrl;
    private int $defaultPageSize;
    private ?string $defaultStoragePath;
    private float $timeout;
    private float $connectTimeout;
    private LoggerInterface $logger;
    private ?TokenStoreInterface $tokenStore;

    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $defaultPageSize = 25,
        ?string $defaultStoragePath = null,
        float $timeout = 60.0,
        float $connectTimeout = 10.0,
        ?LoggerInterface $logger = null,
        ?TokenStoreInterface $tokenStore = null,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->defaultPageSize = $this->validatePositiveInt('defaultPageSize', $defaultPageSize);
        $this->defaultStoragePath = $this->normalizeStoragePath($defaultStoragePath);
        $this->timeout = $this->validatePositiveFloat('timeout', $timeout);
        $this->connectTimeout = $this->validatePositiveFloat('connectTimeout', $connectTimeout);
        $this->logger = $logger ?? new NullLogger();
        $this->tokenStore = $tokenStore;
    }

    public static function production(string $baseUrl = 'https://api.cloudservices.appsmold.com.br/api/v1'): self
    {
        return new self(baseUrl: $baseUrl);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function defaultPageSize(): int
    {
        return $this->defaultPageSize;
    }

    public function defaultStoragePath(): ?string
    {
        return $this->defaultStoragePath;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function tokenStore(): ?TokenStoreInterface
    {
        return $this->tokenStore;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            throw new InvalidArgumentException('A base URL da API não pode estar vazia.');
        }

        $normalized = rtrim($trimmed, '/');
        if (! str_contains($normalized, '://')) {
            throw new InvalidArgumentException('A base URL da API deve ser uma URL válida.');
        }

        if (str_ends_with(strtolower($normalized), '/api/v1')) {
            return $normalized;
        }

        $candidate = $normalized . '/api/v1';
        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A base URL informada é inválida.');
        }

        return $candidate;
    }

    private function normalizeStoragePath(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = trim($value);
        if (
            $normalized === '.'
            || $normalized === '..'
            || preg_match('#^(?:[A-Za-z]:|[\\/]|~)#', $normalized) === 1
        ) {
            throw new InvalidArgumentException(
                'storage_path deve representar um valor lógico da API e não um path local do sistema.'
            );
        }

        return $normalized;
    }

    private function validatePositiveInt(string $name, int $value): int
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s deve ser maior que zero.', $name));
        }

        return $value;
    }

    private function validatePositiveFloat(string $name, float $value): float
    {
        if ($value <= 0.0) {
            throw new InvalidArgumentException(sprintf('%s deve ser maior que zero.', $name));
        }

        return $value;
    }
}
