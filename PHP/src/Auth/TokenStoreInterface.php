<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Auth;

interface TokenStoreInterface
{
    public function read(): ?TokenSet;

    public function write(TokenSet $tokenSet): void;

    public function clear(): void;
}
