<?php

declare(strict_types=1);

namespace Meta4Services\CloudServices\Support;

use InvalidArgumentException;
use JsonException;

final class Json
{
    /**
     * @param mixed $value
     */
    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Não foi possível serializar os dados para JSON.', 0, $exception);
        }
    }

    /**
     * @return mixed
     */
    public static function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Resposta inválida em JSON.', 0, $exception);
        }
    }
}
