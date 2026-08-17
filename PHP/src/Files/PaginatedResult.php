<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Files;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class PaginatedResult implements IteratorAggregate
{
    /**
     * @param array<int, T> $items
     */
    public function __construct(
        private array $items,
        private int $currentPage,
        private int $lastPage,
        private int $perPage,
        private int $total,
    ) {
    }

    /**
     * @return array<int, T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
