<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** Bounded, closed wire contracts. Positions are decimal strings, never JSON numbers. */
final class AuthorityStreamSchema
{
    public const ZERO_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    private const COMMON = ['purpose', 'schemaVersion', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'nonce', 'issuedAt', 'proof'];

    private const CHECKPOINT = ['checkpointRef', 'position', 'chainHash', 'minimumPosition'];

    private const PAGE = ['anchorRef', 'fromRef', 'fromPosition', 'fromHash', 'toRef', 'toPosition', 'toHash', 'entries'];

    private const ENTRY = ['position', 'previousHash', 'entryHash', 'commandWire', 'receiptWire'];

    public static function snapshot(array $data, bool $page): array
    {
        self::keys($data, [...self::COMMON, ...($page ? self::PAGE : self::CHECKPOINT)]);
        $copy = [];
        foreach ($data as $key => $value) {
            if ($key === 'entries') {
                if (! is_array($value) || ! array_is_list($value) || count($value) > 8) {
                    throw new ReceiptContractViolation('Invalid bounded authority entries.');
                }
                $entries = [];
                foreach ($value as $entry) {
                    if (! is_array($entry)) {
                        throw new ReceiptContractViolation('Invalid authority entry shape.');
                    }
                    self::keys($entry, self::ENTRY);
                    $detached = [];
                    foreach ($entry as $field => $scalar) {
                        self::string($scalar, in_array($field, ['commandWire', 'receiptWire'], true) ? 16384 : 256);
                        $detached[$field] = $scalar;
                    }
                    self::position($detached['position']);
                    self::hex($detached['previousHash']);
                    self::hex($detached['entryHash']);
                    $entries[] = $detached;
                }
                $copy[$key] = $entries;
            } elseif ($key === 'schemaVersion') {
                if ($value !== 1) {
                    throw new ReceiptContractViolation('Unsupported authority stream version.');
                }
                $copy[$key] = $value;
            } else {
                self::string($value, $key === 'proof' ? 2048 : 256);
                $copy[$key] = $value;
            }
        }
        if ($copy['purpose'] !== 'studentity.authority.'.($page ? 'change-page' : 'checkpoint').'.v1' || ! preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $copy['authorityNamespace'])) {
            throw new ReceiptContractViolation('Invalid authority stream purpose or namespace.');
        }
        foreach (['issuerGeneration', 'signingKeyId', ...($page ? ['anchorRef', 'fromRef', 'toRef'] : ['checkpointRef'])] as $field) {
            if (! preg_match('/\A[A-Za-z0-9:._\/#%-]+\z/D', $copy[$field])) {
                throw new ReceiptContractViolation('Invalid authority stream reference.');
            }
        }
        foreach ($page ? ['fromPosition', 'toPosition'] : ['position', 'minimumPosition'] as $field) {
            self::position($copy[$field]);
        }
        foreach (['nonce', ...($page ? ['fromHash', 'toHash'] : ['chainHash'])] as $field) {
            self::hex($copy[$field]);
        }
        ReceiptSchema::time($copy['issuedAt']);
        if (! preg_match('/\A[A-Za-z0-9_-]+\z/D', $copy['proof'])) {
            throw new ReceiptContractViolation('Invalid authority stream proof encoding.');
        }
        if (strlen(CanonicalAuthorityJson::encode($copy)) > 262144) {
            throw new ReceiptContractViolation('Authority stream exceeds protocol limit.');
        }

        return $copy;
    }

    public static function decode(string $wire): array
    {
        if (strlen($wire) > 262144) {
            throw new ReceiptContractViolation('Authority stream exceeds protocol limit.');
        }
        try {
            $object = json_decode($wire, false, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ReceiptContractViolation('Invalid authority stream JSON.');
        }
        if (! $object instanceof \stdClass || ! hash_equals(CanonicalAuthorityJson::encode($object), $wire)) {
            throw new ReceiptContractViolation('Canonical authority stream required.');
        }
        $data = (array) $object;
        if (isset($data['entries']) && is_array($data['entries'])) {
            $data['entries'] = array_map(static fn ($entry) => $entry instanceof \stdClass ? (array) $entry : $entry, $data['entries']);
        }

        return $data;
    }

    public static function compare(string $a, string $b): int
    {
        return (strlen($a) <=> strlen($b)) ?: strcmp($a, $b);
    }

    public static function next(string $position): string
    {
        self::position($position);
        if ($position === '9223372036854775807') {
            throw new ReceiptContractViolation('Authority position exhausted.');
        }
        // No integer/float conversion: exact on every supported PHP platform.
        for ($i = strlen($position) - 1; $i >= 0; $i--) {
            if ($position[$i] !== '9') {
                $position[$i] = chr(ord($position[$i]) + 1);

                return $position;
            }
            $position[$i] = '0';
        }

        return '1'.$position;
    }

    private static function keys(array $data, array $expected): void
    {
        $keys = array_keys($data);
        sort($keys);
        sort($expected);
        if ($keys !== $expected) {
            throw new ReceiptContractViolation('Unknown authority stream shape.');
        }
    }

    private static function string(mixed $value, int $maximum): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > $maximum) {
            throw new ReceiptContractViolation('Invalid authority stream scalar.');
        }
    }

    private static function position(string $value): void
    {
        if (! preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) || self::compare($value, '9223372036854775807') > 0) {
            throw new ReceiptContractViolation('Invalid authority stream position.');
        }
    }

    private static function hex(string $value): void
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $value)) {
            throw new ReceiptContractViolation('Invalid authority stream digest or challenge.');
        }
    }
}
