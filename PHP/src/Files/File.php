<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

final class File
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $originalName,
        public readonly string $displayName,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly array $size,
        public readonly string $path,
        public readonly string $status,
        public readonly int $versionsCount,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }
}
