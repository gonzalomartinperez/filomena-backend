<?php

namespace App\Traits;

trait EnumsTrait
{
    /**
     * Devuelve la lista de valores (strings) del enum.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        static $cache = [];

        return $cache[static::class]
            ??= array_map(
                fn (self $case) => $case->value,
                self::cases()
            );
    }
}
