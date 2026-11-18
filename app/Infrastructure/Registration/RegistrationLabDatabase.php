<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use MongoDB\BSON\Document;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

use function MongoDB\with_transaction;

/** Version 1 additive lab installation ONLY; no application migration/boot hook or repair. */
final class RegistrationLabDatabase
{
    public const NAME = 'studentity_test_registration_activation_v1';

    public const ACCOUNT_COLLECTIONS = ['users', 'user_emails', 'credentials', 'notice_acknowledgments', 'official_notice_versions', 'audit_events', 'security_decision_journal'];

    public static function guard(Database $db): void
    {
        if (! app()->environment('testing') || $db->getDatabaseName() !== self::NAME || ! in_array($db->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Exact local registration lab required.');
        }
    }

    public static function definitions(): array
    {
        $collections = self::proposalCollections(file_get_contents(base_path('database/mongodb/proposals/iam01_registration_v1.json')));
        $defs = [];
        foreach ($collections as $c) {
            $defs[$c['name']] = ['options' => $c['options'], 'indexes' => $c['indexes']];
        }
        $catalogue = json_decode(file_get_contents(base_path('database/mongodb/collection_catalogue.json')), true, 128, JSON_THROW_ON_ERROR);
        foreach ($catalogue['collections'] as $c) {
            if (in_array($c['name'], self::ACCOUNT_COLLECTIONS, true)) {
                // The accepted catalogue contains two names for the same notice-ack index.
                // Match the accepted migration's single physical index; never alter the catalogue.
                $indexes = [];
                $seen = [];
                foreach ($c['indexes'] as $i) {
                    $shape = $i;
                    unset($shape['name']);
                    $signature = self::typed($shape);
                    if (isset($seen[$signature])) {
                        continue;
                    }$seen[$signature] = true;
                    $indexes[] = $i;
                }
                $defs[$c['name']] = ['options' => ['validator' => $c['validator'], 'validationLevel' => 'strict', 'validationAction' => 'error'], 'indexes' => $indexes];
            }
        }

        return $defs;
    }

    public static function proposalCollections(string $wire): array
    {
        // Git normalizes the tracked JSON to LF. Normalize only line endings, never index key order.
        $wire = str_replace("\r\n", "\n", $wire);
        if (strtoupper(hash('sha256', $wire)) !== '5FD9F2FFBB28D51AD8919C6B63D78D24447FA7FE4C5282C3E14E3D72294330A1') {
            throw new \RuntimeException('Registration schema proposal version mismatch.');
        }

        return Document::fromJSON($wire)->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array'])['collections'];
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
        // Validate every existing collection before creating anything. Existing missing/wrong indexes are never repaired.
        $defs = self::definitions();
        $missing = [];
        foreach ($db->listCollections() as $info) {
            if (! isset($defs[$info->getName()])) {
                throw new \RuntimeException('Unexpected registration lab collection.');
            }
        }
        foreach ($defs as $name => $def) {
            $infos = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
            if (! $infos) {
                $missing[$name] = $def;

                continue;
            }
            if ($infos[0]->getType() !== 'collection') {
                throw new \RuntimeException('Ordinary registration collection required.');
            }
            $metadata = [];
            foreach ($db->selectCollection($name)->listIndexes() as $i) {
                $metadata[] = $i->__debugInfo();
            }
            self::assertMetadata($infos[0]->getOptions(), $metadata, $def);
        }
        if ($missing && ! $install) {
            throw new \RuntimeException('Registration schema is not installed.');
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

    public static function assertMetadata(array $options, array $indexes, array $definition): void
    {
        if (self::typed($options) !== self::typed($definition['options'])) {
            throw new \RuntimeException('Registration metadata mismatch; no automatic repair.');
        }
        $expected = ['_id_' => ['name' => '_id_', 'key' => ['_id' => 1]]];
        foreach ($definition['indexes'] as $i) {
            $key = $i['keys'];
            unset($i['keys']);
            $expected[$i['name']] = $i + ['key' => $key];
        }
        $seen = [];
        foreach ($indexes as $meta) {
            unset($meta['v']);
            $name = $meta['name'] ?? '';
            // Compound key order is semantically significant, unlike canonical object key order.
            if (! isset($expected[$name]) || isset($seen[$name]) || $meta['key'] !== $expected[$name]['key'] || self::typed($meta) !== self::typed($expected[$name])) {
                throw new \RuntimeException('Registration index mismatch; no automatic repair.');
            }
            $seen[$name] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new \RuntimeException('Registration indexes missing; no automatic repair.');
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
            throw new \RuntimeException('Registration transaction unavailable or conflicting.');
        } finally {
            $session->endSession();
        }
    }
}
