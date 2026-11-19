<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use MongoDB\BSON\Document;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

use function MongoDB\with_transaction;

/** Explicit isolated v2 installation only; never called from boot or a repository constructor. */
final class OutboxLabDatabase
{
    public const NAME = 'studentity_test_fnd04_outbox_v2';

    public static function guard(Database $db): void
    {
        if (! app()->environment('testing') || $db->getDatabaseName() !== self::NAME || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Exact local outbox lab required.');
        }
    }

    public static function definitions(): array
    {
        $wire = str_replace("\r\n", "\n", file_get_contents(base_path('database/mongodb/proposals/fnd04_outbox_v2.json')));
        if (hash('sha256', $wire) !== '8f966ba15f518cb8ce0ca9f1c50e27e98a54cbd4ed322760c3dd77de452573aa') {
            throw new \RuntimeException('Outbox schema version mismatch.');
        }
        $d = Document::fromJSON($wire)->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $defs = [];
        foreach ($d['collections'] as $c) {
            $defs[$c['name']] = ['options' => $c['options'], 'indexes' => $c['indexes']];
        }

        return $defs;
    }

    public static function install(Database $db): void
    {
        self::check($db, true);
    }

    public static function assertReady(Database $db): void
    {
        self::check($db, false);
    }

    private static function check(Database $db, bool $install): void
    {
        self::guard($db);
        $defs = self::definitions();
        $missing = [];
        foreach ($db->listCollections() as $info) {
            if (! isset($defs[$info->getName()])) {
                throw new \RuntimeException('Unexpected outbox collection.');
            }
        }
        foreach ($defs as $name => $def) {
            $infos = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
            if (! $infos) {
                $missing[$name] = $def;

                continue;
            }
            if ($infos[0]->getType() !== 'collection') {
                throw new \RuntimeException('Ordinary outbox collection required.');
            }
            $indexes = [];
            foreach ($db->selectCollection($name)->listIndexes() as $i) {
                $indexes[] = $i->__debugInfo();
            }self::assertMetadata($infos[0]->getOptions(), $indexes, $def);
        }
        if ($missing && ! $install) {
            throw new \RuntimeException('Outbox schema not installed.');
        }
        foreach ($missing as $name => $def) {
            $db->createCollection($name, $def['options']);
            foreach ($def['indexes'] as $i) {
                $key = $i['keys'];
                unset($i['keys']);
                $db->selectCollection($name)->createIndex($key, $i);
            }
        }
    }

    public static function assertMetadata(array $options, array $indexes, array $def): void
    {
        if (self::typed($options) !== self::typed($def['options'])) {
            throw new \RuntimeException('Outbox metadata mismatch; no automatic repair.');
        }
        $expected = ['_id_' => ['name' => '_id_', 'key' => ['_id' => 1]]];
        foreach ($def['indexes'] as $i) {
            $key = $i['keys'];
            unset($i['keys']);
            $expected[$i['name']] = $i + ['key' => $key];
        }
        $seen = [];
        foreach ($indexes as $i) {
            unset($i['v']);
            $name = $i['name'] ?? '';
            if (! isset($expected[$name]) || isset($seen[$name]) || $i['key'] !== $expected[$name]['key'] || self::typed($i) !== self::typed($expected[$name])) {
                throw new \RuntimeException('Outbox indexes mismatch; no automatic repair.');
            }$seen[$name] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new \RuntimeException('Outbox indexes missing; no automatic repair.');
        }
    }

    public static function typed(array $data): string
    {
        return CanonicalAuthorityJson::encode(json_decode(Document::fromPHP($data)->toCanonicalExtendedJSON(), false, 128, JSON_THROW_ON_ERROR));
    }

    public static function transaction(Database $db, \Closure $work): mixed
    {
        self::assertReady($db);
        $session = $db->getManager()->startSession();
        $result = null;
        try {
            with_transaction($session, function ($s) use ($work, &$result) {
                $result = $work(['session' => $s]);
            }, ['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'maxCommitTimeMS' => 5000]);

            return $result;
        } catch (\Throwable) {
            throw new \RuntimeException('Outbox transaction unavailable or conflicting.');
        } finally {
            $session->endSession();
        }
    }
}
