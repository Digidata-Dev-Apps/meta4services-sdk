<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

final class DownloadUrl
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $expiresAt = null,
        public readonly ?int $version = null,
    ) {
    }
}
