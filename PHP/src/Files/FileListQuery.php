<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

final class FileListQuery
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $tenant = null,
        public readonly ?string $storage = null,
        public readonly ?string $user = null,
        public readonly ?string $fileType = null,
        public readonly ?string $extension = null,
        public readonly ?int $sizeMin = null,
        public readonly ?int $sizeMax = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?int $page = null,
        public readonly ?int $perPage = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filters = [];

        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
