<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

final class FileVersion
{
    public function __construct(
        public readonly string $uuid,
        public readonly int $version,
        public readonly ?string $storagePath = null,
        public readonly ?string $checksumSha256 = null,
        public readonly ?int $size = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }
}
