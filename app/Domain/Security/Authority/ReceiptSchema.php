<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** Closed version-1 scalar contracts. These are not a credential or authority provider. */
final class ReceiptSchema
{
    public const COMMAND = ['purpose', 'schemaVersion', 'operationId', 'authorityNamespace', 'subjectUserId', 'sessionDigest', 'securityEpoch', 'authorizationDigest', 'action', 'expectedIssuerGeneration', 'expectedCheckpointRef', 'correlationId'];

    public const RECEIPT = ['purpose', 'schemaVersion', 'operationId', 'receiptId', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'subjectUserId', 'sessionDigest', 'action', 'requestFingerprint', 'correlationId', 'securityEpoch', 'subjectVersion', 'checkpointRef', 'committedAt', 'result', 'proof'];

    public static function validate(array $data, bool $receipt): void
    {
        $keys = array_keys($data);
        $expected = $receipt ? self::RECEIPT : self::COMMAND;
        sort($keys);
        sort($expected);
        if ($keys !== $expected || $data['schemaVersion'] !== 1) {
            throw new ReceiptContractViolation('Unknown authority object shape.');
        }
        foreach ($data as $key => $value) {
            if ($key !== 'schemaVersion' && (! is_string($value) || $value === '' || strlen($value) > ($key === 'proof' ? 2048 : 256))) {
                throw new ReceiptContractViolation('Invalid authority field type or size.');
            }
        }
        if ($data['purpose'] !== 'studentity.session-revocation.'.($receipt ? 'receipt' : 'command').'.v1' || $data['action'] !== 'session.revoke') {
            throw new ReceiptContractViolation('Invalid authority purpose or action.');
        }
        foreach (['operationId', 'subjectUserId', 'correlationId', ...($receipt ? ['receiptId'] : [])] as $field) {
            if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $data[$field])) {
                throw new ReceiptContractViolation('Invalid authority UUID.');
            }
        }
        foreach (['sessionDigest', $receipt ? 'requestFingerprint' : 'authorizationDigest'] as $field) {
            if (! preg_match('/\A[0-9a-f]{64}\z/D', $data[$field])) {
                throw new ReceiptContractViolation('Invalid authority digest.');
            }
        }
        foreach (['securityEpoch', ...($receipt ? ['subjectVersion'] : [])] as $field) {
            $v = $data[$field];
            if (! preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $v) || strlen($v) > 19 || (strlen($v) === 19 && strcmp($v, '9223372036854775807') > 0)) {
                throw new ReceiptContractViolation('Authority version out of range.');
            }
        }
        if (! preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $data['authorityNamespace'])) {
            throw new ReceiptContractViolation('Invalid authority namespace.');
        }
        foreach ($receipt ? ['issuerGeneration', 'signingKeyId', 'checkpointRef'] : ['expectedIssuerGeneration', 'expectedCheckpointRef'] as $field) {
            if (! preg_match('/\A[A-Za-z0-9:._\/#%-]+\z/D', $data[$field])) {
                throw new ReceiptContractViolation('Invalid authority reference.');
            }
        }
        if ($receipt) {
            if ($data['result'] !== 'revoked' || ! preg_match('/\A[A-Za-z0-9_-]+\z/D', $data['proof'])) {
                throw new ReceiptContractViolation('Not a committed revocation receipt.');
            }
            self::time($data['committedAt']);
        }
    }

    public static function time(string $value): \DateTimeImmutable
    {
        if (! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z\z/D', $value)) {
            throw new ReceiptContractViolation('Invalid authority timestamp.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v\Z', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:s.v\Z') !== $value) {
            throw new ReceiptContractViolation('Invalid authority timestamp.');
        }

        return $date;
    }
}
