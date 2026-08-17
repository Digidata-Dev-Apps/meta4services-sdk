<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Support;

use InvalidArgumentException;

final class Uuid
{
    public static function validate(string $value): bool
    {
        $pattern = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}'
            . '-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';

        return preg_match($pattern, $value) === 1;
    }

    public static function ensureValid(string $value): string
    {
        if (! self::validate($value)) {
            throw new InvalidArgumentException(sprintf('UUID inválido: %s', $value));
        }

        return $value;
    }
}
