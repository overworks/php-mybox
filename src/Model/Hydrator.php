<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Support\Json;

/**
 * Type-safe accessors for decoded JSON payloads, shared by every model's
 * `fromArray()`.
 *
 * A required field that is missing or of the wrong type means the API returned
 * something this SDK version does not understand, so it raises a
 * {@see TransportException} rather than silently coercing to a default.
 *
 * @internal
 */
final class Hydrator
{
    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            throw self::missing($key, 'string', $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            throw self::missing($key, 'integer', $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (!is_bool($value)) {
            throw self::missing($key, 'boolean', $value);
        }

        return $value;
    }

    /**
     * Parses an ISO-8601 timestamp such as `2026-08-11T09:00:00+09:00`.
     *
     * @param array<string, mixed> $data
     */
    public static function dateTime(array $data, string $key): \DateTimeImmutable
    {
        $value = self::nullableDateTime($data, $key);

        if ($value === null) {
            throw self::missing($key, 'date-time string', $data[$key] ?? null);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableDateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Resolves a required backed enum case.
     *
     * @template T of \BackedEnum
     * @param array<string, mixed> $data
     * @param class-string<T>      $enum
     * @return T
     */
    public static function enum(array $data, string $key, string $enum): \BackedEnum
    {
        $value = $data[$key] ?? null;
        $case = is_string($value) ? $enum::tryFrom($value) : null;

        if ($case === null) {
            throw self::missing($key, 'one of ' . implode('|', array_column($enum::cases(), 'value')), $value);
        }

        return $case;
    }

    /**
     * Resolves an optional backed enum case, returning null for values this SDK version
     * does not know about so that a newly added MYBOX category cannot break
     * deserialisation of an otherwise valid response.
     *
     * @template T of \BackedEnum
     * @param array<string, mixed> $data
     * @param class-string<T>      $enum
     * @return T|null
     */
    public static function nullableEnum(array $data, string $key, string $enum): ?\BackedEnum
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $enum::tryFrom($value) : null;
    }

    /**
     * Reads a nested object, returning an empty array when absent.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        return Json::asObject($data[$key] ?? null) ?? [];
    }

    /**
     * Reads a list of objects, skipping any entry that is not an object.
     *
     * @param  array<string, mixed>       $data
     * @return list<array<string, mixed>>
     */
    public static function objectList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $object = Json::asObject($item);

            if ($object !== null) {
                $items[] = $object;
            }
        }

        return $items;
    }

    private static function missing(string $key, string $expected, mixed $actual): TransportException
    {
        return new TransportException(sprintf(
            'Unexpected MYBOX response: field "%s" should be a %s, got %s.',
            $key,
            $expected,
            get_debug_type($actual),
        ));
    }
}
