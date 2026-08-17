<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Auth;

final class MemoryTokenStore implements TokenStoreInterface
{
    private ?TokenSet $tokenSet = null;

    public function read(): ?TokenSet
    {
        return $this->tokenSet;
    }

    public function write(TokenSet $tokenSet): void
    {
        $this->tokenSet = $tokenSet;
    }

    public function clear(): void
    {
        $this->tokenSet = null;
    }
}
