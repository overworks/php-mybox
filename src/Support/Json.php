<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Support;

/**
 * JSON encoding and decoding narrowed to what this SDK exchanges: objects.
 *
 * `json_decode(..., true)` yields `array<mixed, mixed>`, and a JSON object
 * whose keys look numeric decodes to integer keys. These helpers normalise
 * that back to string-keyed arrays so the rest of the SDK can rely on the
 * shape without casting.
 *
 * @internal
 */
final class Json
{
    /**
     * Decodes a JSON object.
     *
     * @return array<string, mixed>|null Null when the input is empty, invalid
     *                                   JSON, or a value other than an object.
     */
    public static function decodeObject(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        return self::asObject(json_decode($json, true));
    }

    /**
     * Normalises an already-decoded value into a string-keyed array.
     *
     * @return array<string, mixed>|null Null when the value is not an array.
     */
    public static function asObject(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * @param  array<string, mixed> $value
     * @throws \JsonException
     */
    public static function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
