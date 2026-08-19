<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices;

use finfo;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Meta4Services\CloudServices\Auth\AuthenticatedUser;
use Meta4Services\CloudServices\Auth\TokenSet;
use Meta4Services\CloudServices\Auth\TokenStoreInterface;
use Meta4Services\CloudServices\Config\ClientConfig;
use Meta4Services\CloudServices\Exceptions\ApiException;
use Meta4Services\CloudServices\Exceptions\AuthenticationException;
use Meta4Services\CloudServices\Exceptions\AuthorizationException;
use Meta4Services\CloudServices\Exceptions\CloudServicesException;
use Meta4Services\CloudServices\Exceptions\ConflictException;
use Meta4Services\CloudServices\Exceptions\NotFoundException;
use Meta4Services\CloudServices\Exceptions\RateLimitException;
use Meta4Services\CloudServices\Exceptions\TransportException;
use Meta4Services\CloudServices\Exceptions\ValidationException;
use Meta4Services\CloudServices\Files\DownloadUrl;
use Meta4Services\CloudServices\Files\File;
use Meta4Services\CloudServices\Files\FileListQuery;
use Meta4Services\CloudServices\Files\FileVersion;
use Meta4Services\CloudServices\Files\PaginatedResult;
use Meta4Services\CloudServices\Files\UploadResult;
use Meta4Services\CloudServices\Support\Json;
use Meta4Services\CloudServices\Support\Uuid;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class CloudServicesClient
{
    private const USER_AGENT = 'cloudservices-php-sdk';

    private HttpClient $httpClient;
    private ClientConfig $config;
    private string $email;
    private string $password;
    private string $storageUuid;
    private ?TokenStoreInterface $tokenStore;
    private ?TokenSet $tokenSet = null;
    private bool $isRefreshing = false;

    public function __construct(
        string $email,
        string $password,
        string $storageUuid,
        ?ClientConfig $config = null,
        ?HttpClient $httpClient = null,
        ?TokenStoreInterface $tokenStore = null,
    ) {
        if ($email === '') {
            throw new ValidationException('Email da autenticação não pode estar vazio.');
        }

        if ($password === '') {
            throw new ValidationException('Senha da autenticação não pode estar vazia.');
        }

        Uuid::ensureValid($storageUuid);

        $this->config = $config ?? new ClientConfig();
        $this->tokenStore = $tokenStore ?? $this->config->tokenStore();
        $this->tokenSet = $this->tokenStore?->read();
        $this->email = $email;
        $this->password = $password;
        $this->storageUuid = $storageUuid;
        $this->httpClient = $httpClient ?? new HttpClient([
            'base_uri' => $this->config->baseUrl(),
            'timeout' => $this->config->timeout(),
            'connect_timeout' => $this->config->connectTimeout(),
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function login(): TokenSet
    {
        try {
            $response = $this->httpClient->post($this->buildAbsoluteUri('/auth/login'), [
                'http_errors' => false,
                'json' => [
                    'email' => $this->email,
                    'password' => $this->password,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw $this->mapToException($response);
            }

            $decoded = $this->decodeJson($response);
            $data = $decoded['data'] ?? [];

            if (! isset($data['access_token'], $data['refresh_token'], $data['expires_in'])) {
                throw new AuthenticationException('Resposta de login inválida.');
            }

            $this->tokenSet = new TokenSet(
                accessToken: (string) $data['access_token'],
                refreshToken: (string) $data['refresh_token'],
                expiresIn: (int) $data['expires_in'],
                tokenType: (string) ($data['token_type'] ?? 'Bearer'),
                userUuid: isset($data['user']['uuid']) ? (string) $data['user']['uuid'] : null,
            );

            if ($this->tokenStore !== null) {
                $this->tokenStore->write($this->tokenSet);
            }

            return $this->tokenSet;
        } catch (GuzzleException $exception) {
            throw new TransportException(
                'Falha ao autenticar no serviço.',
                0,
                $exception instanceof \Exception ? $exception : null,
            );
        }
    }

    public function refreshToken(): TokenSet
    {
        if ($this->tokenSet === null || trim($this->tokenSet->refreshToken()) === '') {
            throw new AuthenticationException('Não existe refresh token válido para renovar a sessão.');
        }

        if ($this->isRefreshing) {
            throw new AuthenticationException('Refresh de token já está em andamento.');
        }

        $this->isRefreshing = true;

        try {
            $response = $this->httpClient->post($this->buildAbsoluteUri('/auth/refresh'), [
                'http_errors' => false,
                'json' => [
                    'refresh_token' => $this->tokenSet->refreshToken(),
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                throw $this->mapToException($response);
            }

            $decoded = $this->decodeJson($response);
            $data = $decoded['data'] ?? [];

            if (! isset($data['access_token'])) {
                throw new AuthenticationException('Resposta de refresh inválida.');
            }

            $newRefreshToken = $data['refresh_token'] ?? $this->tokenSet->refreshToken();
            $this->tokenSet = $this->tokenSet->withRefreshedAccessToken(
                accessToken: (string) $data['access_token'],
                refreshToken: (string) $newRefreshToken,
                expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            );

            if ($this->tokenStore !== null) {
                $this->tokenStore->write($this->tokenSet);
            }

            return $this->tokenSet;
        } catch (GuzzleException $exception) {
            throw new TransportException(
                'Falha ao renovar o token de autenticação.',
                0,
                $exception instanceof \Exception ? $exception : null,
            );
        } finally {
            $this->isRefreshing = false;
        }
    }

    /**
     * @return PaginatedResult<File>
     */
    public function listFiles(?FileListQuery $query = null): PaginatedResult
    {
        $parameters = [];
        if ($query !== null) {
            foreach ($query->toArray() as $key => $value) {
                if ($key === 'page' || $key === 'perPage') {
                    $parameters[$key] = $value;
                    continue;
                }

                if ($value !== null && $value !== '') {
                    $parameters[$key] = $value;
                }
            }
        }

        $response = $this->request('GET', '/files', ['query' => $parameters]);
        $decoded = $this->decodeJson($response);

        return $this->mapPaginatedResult($decoded);
    }

    public function getFile(string $fileUuid): File
    {
        Uuid::ensureValid($fileUuid);

        $response = $this->request('GET', sprintf('/files/%s', $fileUuid));
        $decoded = $this->decodeJson($response);
        return $this->mapFile($decoded['data'] ?? $decoded);
    }

    public function listVersions(string $fileUuid): array
    {
        Uuid::ensureValid($fileUuid);

        $response = $this->request('GET', sprintf('/files/%s/versions', $fileUuid));
        $decoded = $this->decodeJson($response);
        $items = $decoded['data'] ?? [];

        $versions = [];
        foreach ($items as $item) {
            $versions[] = $this->mapFileVersion($item);
        }

        return $versions;
    }

    public function getDownload(string $fileUuid, ?int $version = null): array
    {
        Uuid::ensureValid($fileUuid);

        $query = [];
        if ($version !== null) {
            $query['version'] = $version;
        }

        $response = $this->request('GET', sprintf('/files/%s/download', $fileUuid), ['query' => $query]);
        $decoded = $this->decodeJson($response);

        return $decoded['data'] ?? $decoded;
    }

    public function deleteFile(string $fileUuid): void
    {
        Uuid::ensureValid($fileUuid);
        $this->request('DELETE', sprintf('/files/%s', $fileUuid));
    }

    public function upload(
        string $file,
        ?string $storagePath = null,
        ?string $displayName = null,
        ?array $metadata = null,
    ): UploadResult {
        if (! is_file($file)) {
            throw new ValidationException(sprintf('Arquivo inválido: %s', $file));
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file);

        if ($mimeType === false || $mimeType === '') {
            throw new ValidationException(sprintf(
                'Não foi possível identificar o tipo do arquivo: %s',
                $file
            ));
        }

        $filename = $displayName !== null && $displayName !== ''
            ? basename($displayName)
            : basename($file);

        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $extension = match ($mimeType) {
                'application/pdf' => 'pdf',
                'image/jpg' => 'jpg',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                'text/plain' => 'txt',
                'text/csv' => 'csv',
                'application/json' => 'json',
                'application/zip' => 'zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                default => null,
            };

            if ($extension !== null) {
                $filename .= '.' . $extension;
            }
        }

        $stream = fopen($file, 'rb');

        if ($stream === false) {
            throw new ValidationException(sprintf(
                'Não foi possível abrir o arquivo: %s',
                $file
            ));
        }

        $payload = [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $stream,
                    'filename' => $filename,
                    'headers' => [
                        'Content-Type' => $mimeType,
                    ],
                ],
                [
                    'name' => 'storage_uuid',
                    'contents' => $this->storageUuid,
                ],
            ],
        ];

        if ($displayName !== null && $displayName !== '') {
            $payload['multipart'][] = [
                'name' => 'display_name',
                'contents' => $displayName,
            ];
        }

        if ($metadata !== null) {
            $payload['multipart'][] = [
                'name' => 'metadata',
                'contents' => Json::encode($metadata),
            ];
        }

        $resolvedStoragePath = $storagePath ?? $this->config->defaultStoragePath();

        if ($resolvedStoragePath !== null && $resolvedStoragePath !== '') {
            $payload['multipart'][] = [
                'name' => 'storage_path',
                'contents' => $resolvedStoragePath,
            ];
        }

        $response = $this->request('POST', '/files', $payload);
        $decoded = $this->decodeJson($response);

        return $this->mapUploadResult($decoded['data'] ?? $decoded);
    }

    public function downloadTo(string $fileUuid, string $destination, ?int $version = null): void
    {
        $download = $this->getDownload($fileUuid, $version);
        if (! isset($download['url'])) {
            throw new ValidationException('A resposta do download não contém uma URL assinada.');
        }

        $url = $download['url'];
        $stream = (new HttpClient())->request('GET', $url, [
            'stream' => true,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
            ],
        ]);
        $body = $stream->getBody();

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new ValidationException(sprintf('Destino inválido para download: %s', $destination));
        }

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                continue;
            }
            fwrite($handle, $chunk);
        }

        fclose($handle);
    }

    private function buildAbsoluteUri(string $uri): string
    {
        return rtrim($this->config->baseUrl(), '/') . '/' . ltrim($uri, '/');
    }

    private function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $requestOptions = $options;
        $requestOptions['http_errors'] = false;
        $requestOptions['headers'] = $requestOptions['headers'] ?? [];
        $requestOptions['headers']['Accept'] = 'application/json';

        if (! isset($requestOptions['multipart'])) {
            $requestOptions['headers']['Content-Type'] = 'application/json';
        }

        if ($this->tokenSet !== null) {
            $requestOptions['headers']['Authorization'] = 'Bearer ' . $this->tokenSet->accessToken();
        }

        $absoluteUri = $this->buildAbsoluteUri($uri);

        try {
            $response = $this->httpClient->request($method, $absoluteUri, $requestOptions);
            if ($response->getStatusCode() === 401 && $this->tokenSet !== null && ! $this->isRefreshing) {
                $this->refreshToken();
                $requestOptions['headers']['Authorization'] = 'Bearer ' . $this->tokenSet->accessToken();
                return $this->httpClient->request($method, $absoluteUri, $requestOptions);
            }

            if ($response->getStatusCode() >= 400) {
                throw $this->mapToException($response);
            }

            return $response;
        } catch (GuzzleException $exception) {
            throw new TransportException(
                'Falha na comunicação com a API.',
                0,
                $exception instanceof \Exception ? $exception : null,
            );
        } catch (CloudServicesException $exception) {
            throw $exception;
        }
    }

    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        try {
            $decoded = Json::decode($body);
        } catch (InvalidArgumentException $exception) {
            throw new ValidationException('A resposta da API não é um JSON válido.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new ValidationException('A resposta da API não está no formato esperado.');
        }

        return $decoded;
    }

    private function mapToException(ResponseInterface $response): CloudServicesException
    {
        $statusCode = $response->getStatusCode();
        $rawBody = (string) $response->getBody();
        $decoded = [];

        try {
            $decoded = Json::decode($rawBody);
            if (! is_array($decoded)) {
                $decoded = [];
            }
        } catch (InvalidArgumentException) {
            $decoded = [];
        }

        $apiMessage = $this->extractApiMessage($decoded, $statusCode);
        $requestId = $response->getHeaderLine('X-Request-Id');
        $sanitized = $this->sanitizeResponse($decoded);

        return match ($statusCode) {
            401 => (new AuthenticationException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            403 => (new AuthorizationException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            404 => (new NotFoundException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            409 => (new ConflictException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            422 => (new ValidationException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            429 => (new RateLimitException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
            default => (new ApiException($apiMessage))->withHttpMetadata(
                $statusCode,
                $apiMessage,
                $requestId !== '' ? $requestId : null,
                $sanitized,
            ),
        };
    }

    private function extractApiMessage(array $payload, int $statusCode): string
    {
        $message = $payload['message'] ?? $payload['error'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return 'A API retornou um erro HTTP ' . $statusCode . '.';
    }

    private function sanitizeResponse(array $payload): array
    {
        $sanitized = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/(password|token|authorization|secret|key)/i', $key) === 1) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeResponse($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function mapUploadResult(array $data): UploadResult
    {
        $fileUuid = (string) ($data['uuid'] ?? '');
        $status = (string) ($data['status'] ?? 'completed');
        $file = isset($data['uuid']) ? $this->mapFile($data) : null;

        return new UploadResult(
            fileUuid: $fileUuid,
            status: $status,
            file: $file,
        );
    }

    /**
     * @return PaginatedResult<File>
     */
    private function mapPaginatedResult(array $payload): PaginatedResult
    {
        $items = $payload['data'] ?? [];
        $meta = $payload['meta'] ?? [];

        $mappedItems = [];
        foreach ($items as $item) {
            $mappedItems[] = $this->mapFile($item);
        }

        return new PaginatedResult(
            items: $mappedItems,
            currentPage: (int) ($meta['current_page'] ?? 1),
            lastPage: (int) ($meta['last_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($mappedItems)),
            total: (int) ($meta['total'] ?? count($mappedItems)),
        );
    }

    private function mapFile(array $data): File
    {
        $size = $data['size'] ?? [];
        return new File(
            uuid: (string) ($data['uuid'] ?? ''),
            originalName: (string) ($data['original_name'] ?? ''),
            displayName: (string) ($data['display_name'] ?? $data['original_name'] ?? ''),
            mimeType: (string) ($data['mime_type'] ?? ''),
            extension: (string) ($data['extension'] ?? ''),
            size: is_array($size) ? $size : [],
            path: (string) ($data['current_version']['storage_path'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            versionsCount: (int) ($data['versions_count'] ?? 0),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    private function mapFileVersion(array $data): FileVersion
    {
        return new FileVersion(
            uuid: (string) ($data['uuid'] ?? ''),
            version: (int) ($data['version'] ?? 0),
            storagePath: isset($data['storage_path']) ? (string) $data['storage_path'] : null,
            checksumSha256: isset($data['checksum_sha256']) ? (string) $data['checksum_sha256'] : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
