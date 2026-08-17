<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Exceptions;

use Exception;

class CloudServicesException extends Exception
{
    protected ?int $httpStatusCode = null;
    protected ?string $apiMessage = null;
    protected ?string $requestId = null;
    protected array $sanitizedResponse = [];

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function httpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function apiMessage(): ?string
    {
        return $this->apiMessage;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function sanitizedResponse(): array
    {
        return $this->sanitizedResponse;
    }

    public function withHttpMetadata(
        ?int $statusCode,
        ?string $apiMessage,
        ?string $requestId,
        array $sanitizedResponse,
    ): self {
        $this->httpStatusCode = $statusCode;
        $this->apiMessage = $apiMessage;
        $this->requestId = $requestId;
        $this->sanitizedResponse = $sanitizedResponse;

        return $this;
    }
}
