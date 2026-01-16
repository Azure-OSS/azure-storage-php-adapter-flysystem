<?php

declare(strict_types=1);

namespace AzureOss\Storage\BlobFlysystem\Support;

/**
 * @internal
 */
final class ConfigArrayParser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function parseIntFromArray(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        if (! is_int($data[$key])) {
            throw new \RuntimeException(sprintf('%s must be an int.', $key));
        }

        return $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function parseArrayFromArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new \RuntimeException(sprintf('%s must be an array.', $key));
        }

        /** @phpstan-ignore-next-line */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function parseStringFromArray(array $data, string $key, string $contextPrefix = ''): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            $fullKey = $contextPrefix !== '' ? $contextPrefix.$key : $key;
            throw new \RuntimeException(sprintf('%s must be a string.', $fullKey));
        }

        return $value;
    }
}
