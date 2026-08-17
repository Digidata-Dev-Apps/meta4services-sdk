<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

final class UploadResult
{
    public function __construct(
        public readonly string $fileUuid,
        public readonly string $status,
        public readonly ?File $file = null,
    ) {
    }

    public function isCompleted(): bool
    {
        return strtolower($this->status) === 'completed' || strtolower($this->status) === 'success';
    }

    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }
}
