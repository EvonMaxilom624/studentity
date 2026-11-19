<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Infrastructure\Jobs\OutboxLabDatabase;
use MongoDB\Database;
use MongoDB\Driver\ReadPreference;

final class ScanCommandLabDatabase
{
    public const NAME = 'studentity_test_fnd06_scan_commands_v1';

    public static function guard(Database $db): void
    {
        if (! app()->environment('testing') || $db->getDatabaseName() !== self::NAME || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['localhost', '127.0.0.1'], true)) {
            throw new \InvalidArgumentException('Exact local scan-command lab required.');
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

    private static function check(Database $db, bool $install): void
    {
        self::guard($db);
        $wire = str_replace("\r\n", "\n", file_get_contents(base_path('database/mongodb/proposals/fnd06_scan_commands_v1.json')));
        if (hash('sha256', $wire) !== '699855c3350254b63bf5352872f0c2ad6af14981bedb3bf85771a053280e63fa') {
            throw new \RuntimeException('Scan command schema mismatch.');
        }$d = json_decode($wire, true, 64, JSON_THROW_ON_ERROR);
        $found = false;
        foreach ($db->listCollections() as $info) {
            if ($info->getName() !== 'scan_commands' || $info->getType() !== 'collection') {
                throw new \RuntimeException('Unexpected scan-command collection.');
            }$idx = [];
            foreach ($db->scan_commands->listIndexes() as $i) {
                $idx[] = $i->__debugInfo();
            }OutboxLabDatabase::assertMetadata($info->getOptions(), $idx, ['options' => $d['options'], 'indexes' => []]);
            $found = true;
        }
        if (! $found) {
            if (! $install) {
                throw new \RuntimeException('Scan command lab not installed.');
            }$db->createCollection('scan_commands', $d['options']);
        }
    }
}
