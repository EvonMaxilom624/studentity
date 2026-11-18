<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use MongoDB\BSON\Document;
use MongoDB\Database;
use MongoDB\Driver\ReadPreference;

/** Exact local synthetic schema, not an application migration or production registry. */
final class PrerequisiteDatabase
{
    public static function ensure(Database $db, string $name): void
    {
        if (! app()->environment('testing') || $db->getDatabaseName() !== 'studentity_test_identity_prerequisites' || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Exact local prerequisite database required.');
        }
        $uuid = ['bsonType' => 'string', 'pattern' => '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'];
        $hex = ['bsonType' => 'string', 'pattern' => '^[0-9a-f]{64}$'];
        $date = ['bsonType' => 'date'];
        $common = ['_id' => $uuid, 'schema_version' => ['bsonType' => 'int', 'enum' => [1]]];
        [$fields,$optional,$indexes] = match ($name) {
            'allocations_v1' => [$common + ['intent_fingerprint' => $hex, 'system_id' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 128], 'state' => ['bsonType' => 'string', 'enum' => ['reserved', 'issued', 'retired']], 'revision' => ['bsonType' => 'long', 'minimum' => 1], 'recorded_at' => $date], [], ['uq_permanent_id' => ['system_id' => 1]]],
            'registration_challenges_v1' => [$common + ['intent_id' => $uuid, 'intent_fingerprint' => $hex, 'purpose' => ['bsonType' => 'string', 'enum' => ['registration.email']], 'recipient_digest' => $hex, 'code_digest' => ['bsonType' => 'binData'], 'expires_at' => $date, 'created_at' => $date, 'attempts' => ['bsonType' => 'int', 'minimum' => 0], 'max_attempts' => ['bsonType' => 'int', 'minimum' => 1, 'maximum' => 10], 'consumed_at' => $date], ['consumed_at'], []],
            default => throw new \InvalidArgumentException('Unknown prerequisite collection.')
        };
        $validator = ['$jsonSchema' => ['bsonType' => 'object', 'additionalProperties' => false, 'required' => array_values(array_diff(array_keys($fields), $optional)), 'properties' => $fields]];
        $expected = ['validator' => $validator, 'validationLevel' => 'strict', 'validationAction' => 'error'];
        $infos = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
        if (! $infos) {
            $db->createCollection($name, $expected);
            $infos = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
        }
        if ($infos[0]->getType() !== 'collection' || self::typed($infos[0]->getOptions()) !== self::typed($expected)) {
            throw new \RuntimeException('Prerequisite schema mismatch; no repair.');
        }
        $collection = $db->selectCollection($name);
        $seen = [];
        foreach ($collection->listIndexes() as $index) {
            $info = $index->__debugInfo();
            $key = $index->getName();
            $seen[] = $key;
            $allowed = ['v', 'key', 'name', ...($key !== '_id_' ? ['unique'] : (($info['unique'] ?? false) === true ? ['unique'] : []))];
            $expectedKey = $key === '_id_' ? ['_id' => 1] : ($indexes[$key] ?? null);
            if (array_diff(array_keys($info), $allowed) || $index->getKey() !== $expectedKey || ($key !== '_id_' && ($info['unique'] ?? false) !== true)) {
                throw new \RuntimeException('Prerequisite indexes mismatch.');
            }
        }
        foreach ($indexes as $key => $fields) {
            if (! in_array($key, $seen, true)) {
                $collection->createIndex($fields, ['name' => $key, 'unique' => true]);
            }
        }
    }

    private static function typed(array $value): string
    {
        return CanonicalAuthorityJson::encode(json_decode(Document::fromPHP($value)->toCanonicalExtendedJSON(), false, 32, JSON_THROW_ON_ERROR));
    }
}
