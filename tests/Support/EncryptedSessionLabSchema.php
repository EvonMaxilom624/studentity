<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\ReadPreference;
use MongoDB\Model\IndexInfo;

/** Additive isolated schema only. Never edits the accepted business catalogue or repairs unknown metadata. */
final class EncryptedSessionLabSchema
{
    private const INDEXES = ['_id_' => ['_id' => 1], 'ix_transport_staged' => ['lifecycle' => 1, 'lease_until' => 1], 'ix_transport_expiry' => ['expires_at' => 1]];

    public static function ensure(Database $database): void
    {
        if (! app()->environment('testing') || $database->getDatabaseName() !== 'studentity_test_session_activation') {
            throw new \InvalidArgumentException('Exact activation lab database required.');
        }
        if (! in_array($database->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Local activation lab required.');
        }
        $validator = self::validator();
        $infos = iterator_to_array($database->listCollections(['filter' => ['name' => 'envelopes_v1']]), false);
        if (! $infos) {
            $database->createCollection('envelopes_v1', ['validator' => $validator, 'validationLevel' => 'strict', 'validationAction' => 'error']);
            $infos = iterator_to_array($database->listCollections(['filter' => ['name' => 'envelopes_v1']]), false);
        }
        if ($infos[0]->getType() !== 'collection') {
            throw new \RuntimeException('Ordinary activation collection required.');
        }
        $options = $infos[0]->getOptions();
        self::assertOptions($options);
        $expected = self::INDEXES;
        $collection = $database->selectCollection('envelopes_v1');
        $seen = [];
        foreach ($collection->listIndexes() as $index) {
            $name = $index->getName();
            self::assertIndex($index);
            $seen[] = $name;
        }
        foreach ($expected as $name => $key) {
            if (! in_array($name, $seen, true)) {
                $collection->createIndex($key, ['name' => $name]);
            }
        }
    }

    public static function assertOptions(array $options): void
    {
        if (array_diff(array_keys($options), ['validator', 'validationLevel', 'validationAction']) || ($options['validationLevel'] ?? null) !== 'strict' || ($options['validationAction'] ?? null) !== 'error' || self::typed(['validator' => $options['validator'] ?? null]) !== self::typed(['validator' => self::validator()])) {
            throw new \RuntimeException('Activation lab metadata mismatch; no automatic repair.');
        }
    }

    public static function assertIndex(IndexInfo $index): void
    {
        $expected = self::INDEXES;
        $name = $index->getName();
        // The pinned driver's public metadata snapshot exposes every option, including
        // unknown modifiers. Reject them rather than silently changing session semantics.
        $metadata = $index->__debugInfo();
        $allowed = ['v', 'key', 'name'];
        if ($name === '_id_' && ($metadata['unique'] ?? false) === true) {
            $allowed[] = 'unique';
        }
        if (array_diff(array_keys($metadata), $allowed) || ! isset($expected[$name]) || $index->getKey() !== $expected[$name]) {
            throw new \RuntimeException('Activation lab index mismatch; no automatic repair.');
        }
    }

    public static function validator(): array
    {
        $uuid = '\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z';
        $fields = [
            '_id' => ['bsonType' => 'string', 'pattern' => '^[0-9a-f]{64}$'],
            'lifecycle' => ['bsonType' => 'string', 'enum' => ['staged', 'live', 'revoked']],
            'payload' => ['bsonType' => 'binData'],
            'generation' => ['bsonType' => 'long', 'minimum' => 0, 'maximum' => new Int64('9223372036854775807')],
            'revision' => ['bsonType' => 'long', 'minimum' => 0, 'maximum' => new Int64('9223372036854775807')],
            'owner' => ['bsonType' => 'string', 'pattern' => '^(?:|[0-9a-f]{32})$'],
            'lease_until' => ['bsonType' => 'date'], 'created_at' => ['bsonType' => 'date'], 'updated_at' => ['bsonType' => 'date'], 'expires_at' => ['bsonType' => 'date'],
            'issuance_operation_id' => ['bsonType' => 'string', 'pattern' => $uuid], 'schema_version' => ['bsonType' => 'int', 'enum' => [1]],
        ];

        return ['$jsonSchema' => ['bsonType' => 'object', 'additionalProperties' => false, 'required' => array_values(array_diff(array_keys($fields), ['payload'])), 'properties' => $fields,
            'oneOf' => [
                ['properties' => ['lifecycle' => ['enum' => ['staged']]], 'not' => ['required' => ['payload']]],
                ['properties' => ['lifecycle' => ['enum' => ['live']]], 'required' => ['payload']],
                ['properties' => ['lifecycle' => ['enum' => ['revoked']], 'owner' => ['enum' => ['']], 'lease_until' => ['enum' => [new UTCDateTime(0)]]], 'not' => ['required' => ['payload']]],
            ],
        ]];
    }

    private static function typed(array $value): string
    {
        return CanonicalAuthorityJson::encode(json_decode(Document::fromPHP($value)->toCanonicalExtendedJSON(), false, 32, JSON_THROW_ON_ERROR));
    }
}
