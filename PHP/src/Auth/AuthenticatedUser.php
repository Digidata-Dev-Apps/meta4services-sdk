<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Auth;

final class AuthenticatedUser
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {
    }
}
