<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Auth;

final class TokenSet
{
    public function __construct(
        private string $accessToken,
        private string $refreshToken,
        private int $expiresIn,
        private string $tokenType,
        private ?string $userUuid = null,
    ) {
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function expiresIn(): int
    {
        return $this->expiresIn;
    }

    public function tokenType(): string
    {
        return $this->tokenType;
    }

    public function userUuid(): ?string
    {
        return $this->userUuid;
    }

    public function withRefreshedAccessToken(
        string $accessToken,
        ?string $refreshToken = null,
        ?int $expiresIn = null,
    ): self {
        return new self(
            accessToken: $accessToken,
            refreshToken: $refreshToken ?? $this->refreshToken,
            expiresIn: $expiresIn ?? $this->expiresIn,
            tokenType: $this->tokenType,
            userUuid: $this->userUuid,
        );
    }
}
