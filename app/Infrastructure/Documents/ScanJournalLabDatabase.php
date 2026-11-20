<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

use function MongoDB\with_transaction;

final class ScanJournalLabDatabase
{
    public const NAME = 'studentity_test_fnd06_scan_journal_v1';

    public static function guard(Database $db): void
    {
        if (! app()->environment('testing') || $db->getDatabaseName() !== self::NAME || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['localhost', '127.0.0.1'], true)) {
            throw new \InvalidArgumentException('Exact local scan-journal lab required.');
        }
    }

    private static function check(Database $db, bool $install): void
    {
        self::guard($db);
        $wire = str_replace("\r\n", "\n", file_get_contents(base_path('database/mongodb/proposals/fnd06_scan_journal_v1.json')));
        if (hash('sha256', $wire) !== '6661a72c7aa3b1aa2db21b08043d0c4d2977ed790ebe52091d98c5600c57047f') {
            throw new \RuntimeException('Scan journal schema mismatch.');
        }
        $defs = [];
        foreach (json_decode($wire, true, 64, JSON_THROW_ON_ERROR)['collections'] as $d) {
            $defs[$d['name']] = $d;
        }$missing = $defs;
        foreach ($db->listCollections() as $info) {
            $name = $info->getName();
            if (! isset($defs[$name]) || $info->getType() !== 'collection') {
                throw new \RuntimeException('Unexpected journal metadata.');
            }$idx = [];
            foreach ($db->selectCollection($name)->listIndexes() as $i) {
                $idx[] = $i->__debugInfo();
            }OutboxLabDatabase::assertMetadata($info->getOptions(), $idx, $defs[$name]);
            unset($missing[$name]);
        }
        if ($missing && ! $install) {
            throw new \RuntimeException('Scan journal not installed.');
        }foreach ($missing as $name => $d) {
            $db->createCollection($name, $d['options']);
            foreach ($d['indexes'] as $i) {
                $keys = $i['keys'];
                unset($i['keys']);
                $db->selectCollection($name)->createIndex($keys, $i);
            }
        }
    }

    public static function install(Database $db): void
    {
        self::check($db, true);
    }

    public static function assertReady(Database $db): void
    {
        self::check($db, false);
    }

    public static function transaction(Database $db, \Closure $work): mixed
    {
        self::assertReady($db);
        $s = $db->getManager()->startSession();
        $result = null;
        try {
            with_transaction($s, function ($session) use ($work, &$result) {
                $result = $work(['session' => $session]);
            }, ['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'maxCommitTimeMS' => 5000]);

            return $result;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Scan journal transaction unavailable; retain evidence.');
        } finally {
            $s->endSession();
        }
    }
}
