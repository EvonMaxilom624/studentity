<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use MongoDB\BSON\Document;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

use function MongoDB\with_transaction;

/** Explicit synthetic lab install only. No accepted database, boot install or metadata repair. */
final class HandlerLabDatabase
{
    public const CONSUMER = 'studentity_test_fnd04_handler_v1';

    public const PROVIDER = 'studentity_test_fnd04_provider_v1';

    public static function guard(Database $db, ?string $target = null): void
    {
        if (! app()->environment('testing') || ! in_array($db->getDatabaseName(), $target === null ? [self::CONSUMER, self::PROVIDER] : [$target], true) || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Exact local handler lab required.');
        }
    }

    public static function definitions(Database $db): array
    {
        self::guard($db);
        $wire = str_replace("\r\n", "\n", file_get_contents(base_path('database/mongodb/proposals/fnd04_handler_lab_v1.json')));
        if (hash('sha256', $wire) !== '1358f9cee09b1b11cf72010de2ac7adbb294aef3499900772ccf1f73cec4079b') {
            throw new \RuntimeException('Handler lab schema version mismatch.');
        }
        $d = Document::fromJSON($wire)->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $defs = [];
        foreach ($d['targets'][$db->getDatabaseName()] as $c) {
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
        $defs = self::definitions($db);
        $missing = [];
        foreach ($db->listCollections() as $c) {
            if (! isset($defs[$c->getName()])) {
                throw new \RuntimeException('Unexpected handler lab collection.');
            }
        }
        foreach ($defs as $name => $def) {
            $infos = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
            if (! $infos) {
                $missing[$name] = $def;

                continue;
            }
            if ($infos[0]->getType() !== 'collection') {
                throw new \RuntimeException('Ordinary lab collection required.');
            }
            $indexes = [];
            foreach ($db->selectCollection($name)->listIndexes() as $i) {
                $indexes[] = $i->__debugInfo();
            }
            OutboxLabDatabase::assertMetadata($infos[0]->getOptions(), $indexes, $def);
        }
        if ($missing && ! $install) {
            throw new \RuntimeException('Handler lab not installed.');
        }
        foreach ($missing as $name => $def) {
            $db->createCollection($name, $def['options']);
            foreach ($def['indexes'] as $i) {
                $keys = $i['keys'];
                unset($i['keys']);
                $db->selectCollection($name)->createIndex($keys, $i);
            }
        }
    }

    public static function now(Database $db, array $options): UTCDateTime
    {
        return iterator_to_array($db->aggregate([['$documents' => [(object) []]], ['$project' => ['now' => '$$NOW']]], $options), false)[0]['now'];
    }

    public static function transaction(Database $db, \Closure $work): mixed
    {
        self::assertReady($db);
        $s = $db->getManager()->startSession();
        $result = null;
        try {
            with_transaction($s, function ($session) use ($work, &$result) {
                $result = $work(['session' => $session]);
            },
                ['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'maxCommitTimeMS' => 5000]);

            return $result;
        } catch (\Throwable) {
            throw new \RuntimeException('Handler lab transaction unavailable or conflicting.');
        } finally {
            $s->endSession();
        }
    }
}
