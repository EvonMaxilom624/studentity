<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** Closed bounded scalar snapshots before canonicalization; rejects objects/cycles in scalar slots. */
final class AuthorityContractFields
{
    public static function object(mixed $value): array
    {
        if (! is_array($value) && ! $value instanceof \stdClass) {
            throw new ReceiptContractViolation('Authority object required.');
        }

        return (array) $value;
    }

    public static function keys(array $data, array $fields): void
    {
        $keys = array_keys($data);
        sort($keys);
        sort($fields);
        if ($keys !== $fields) {
            throw new ReceiptContractViolation('Unknown authority contract shape.');
        }
    }

    public static function scalars(array $data, array $fields, array $nullable = [], array $booleans = []): array
    {
        self::keys($data, $fields);
        $copy = [];
        foreach ($data as $key => $value) {
            if ($key === 'schemaVersion') {
                if ($value !== 1) {
                    throw new ReceiptContractViolation('Unsupported authority contract version.');
                }
            } elseif (in_array($key, $booleans, true)) {
                if (! is_bool($value)) {
                    throw new ReceiptContractViolation('Invalid authority boolean.');
                }
            } elseif ($value === null && in_array($key, $nullable, true)) {
            } elseif (! is_string($value) || $value === '' || strlen($value) > ($key === 'proof' ? 2048 : 256)) {
                throw new ReceiptContractViolation('Invalid authority scalar.');
            }
            $copy[$key] = $value;
        }

        return $copy;
    }

    public static function purpose(array $data, string $purpose): void
    {
        if ($data['purpose'] !== $purpose) {
            throw new ReceiptContractViolation('Authority purpose mismatch.');
        }
    }

    public static function uuid(string $v): void
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $v)) {
            throw new ReceiptContractViolation('Invalid authority UUID.');
        }
    }

    public static function digest(string $v): void
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $v)) {
            throw new ReceiptContractViolation('Invalid authority fingerprint.');
        }
    }

    public static function reference(string $v): void
    {
        if (! preg_match('/\A[\x21-\x7e]{1,256}\z/D', $v)) {
            throw new ReceiptContractViolation('Invalid authority reference.');
        }
    }

    public static function issuer(array $d, bool $command = false): void
    {
        if (! preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $d['authorityNamespace'])) {
            throw new ReceiptContractViolation('Invalid authority namespace.');
        }
        self::reference($d[$command ? 'expectedIssuerGeneration' : 'issuerGeneration']);
        if (! $command) {
            self::reference($d['signingKeyId']);
            if (! preg_match('/\A[A-Za-z0-9_-]+\z/D', $d['proof'])) {
                throw new ReceiptContractViolation('Invalid authority proof encoding.');
            }
        }
    }

    public static function revision(string $v): void
    {
        if (! preg_match('/\A[1-9][0-9]*\z/D', $v) || strlen($v) > 19 || (strlen($v) === 19 && strcmp($v, '9223372036854775807') > 0)) {
            throw new ReceiptContractViolation('Invalid authority revision.');
        }
    }
}
