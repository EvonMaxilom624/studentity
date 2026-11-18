<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** JCS subset for protocol objects: UTF-16 key order, JSON strings and safe integers; no floats. */
final class CanonicalAuthorityJson
{
    public static function encode(array|\stdClass $value): string
    {
        try {
            return json_encode(self::normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        } catch (\JsonException) {
            throw new ReceiptContractViolation('Invalid canonical JSON.');
        }
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass || (is_array($value) && ! array_is_list($value))) {
            $properties = (array) $value;
            uksort($properties, static fn ($a, $b) => strcmp(mb_convert_encoding((string) $a, 'UTF-16BE', 'UTF-8'), mb_convert_encoding((string) $b, 'UTF-16BE', 'UTF-8')));
            $ordered = new \stdClass;
            foreach ($properties as $key => $item) {
                $ordered->{(string) $key} = self::normalize($item);
            }

            return $ordered;
        }
        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }
        if (is_int($value) && ($value < -9007199254740991 || $value > 9007199254740991)) {
            throw new ReceiptContractViolation('Unsafe JSON integer.');
        }
        if ($value !== null && ! is_string($value) && ! is_bool($value) && ! is_int($value)) {
            throw new ReceiptContractViolation('Unsupported canonical JSON value.');
        }

        return $value;
    }

    /** Canonical wire required: catches duplicate members before they can be silently accepted. */
    public static function decodeObject(string $wire): array
    {
        if (strlen($wire) > 16384) {
            throw new ReceiptContractViolation('Authority object exceeds protocol limit.');
        }
        try {
            $object = json_decode($wire, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ReceiptContractViolation('Invalid authority JSON.');
        }
        if (! $object instanceof \stdClass || ! hash_equals(self::encode($object), $wire)) {
            throw new ReceiptContractViolation('Canonical authority object required.');
        }

        return (array) $object;
    }
}
