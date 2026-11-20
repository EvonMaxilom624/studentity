<?php

declare(strict_types=1);

use App\Infrastructure\Verification\LocalVerificationTarget;
use MongoDB\Client;
use MongoDB\Driver\ReadPreference;

require dirname(__DIR__).'/vendor/autoload.php';

// Standalone read-only metadata inspection; never boot Dotenv or run migrations.
try {
    $uri = getenv('MONGODB_URI');
    if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' || ! LocalVerificationTarget::allows($uri)) {
        throw new RuntimeException;
    }
    $client = new Client($uri, ['serverSelectionTimeoutMS' => 5000]);
    $hello = iterator_to_array($client->selectDatabase('admin', ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->command(['hello' => 1]), false)[0];
    if (! LocalVerificationTarget::allowsTopology($hello, $client->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost())) {
        throw new RuntimeException;
    }
    $catalogue = json_decode(file_get_contents(dirname(__DIR__).'/database/mongodb/collection_catalogue.json'), true, 512, JSON_THROW_ON_ERROR);
    $specs = $catalogue['collections'];
    if (count($specs) !== 62) {
        throw new RuntimeException;
    }
    $normal = static fn ($value) => json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $report = ['readOnly' => true, 'databases' => []];
    foreach (['studentity_dev_m0', 'studentity_test_m0'] as $name) {
        $db = $client->selectDatabase($name);
        $live = [];
        foreach ($db->listCollections() as $collection) {
            $live[$collection->getName()] = $normal($collection->getOptions());
        }
        $expectedNames = [...array_column($specs, 'name'), 'migrations'];
        $names = array_keys($live);
        sort($names);
        sort($expectedNames);
        if ($names !== $expectedNames) {
            throw new RuntimeException;
        }
        $indexCount = 0;
        $physicalIndexes = 0;
        foreach ($specs as $spec) {
            $options = $live[$spec['name']];
            if (($options['validator'] ?? null) != $spec['validator'] || ($options['validationLevel'] ?? null) !== 'strict' || ($options['validationAction'] ?? null) !== 'error') {
                throw new RuntimeException;
            }
            $indexes = iterator_to_array($db->selectCollection($spec['name'])->listIndexes(), false);
            $seen = [];
            foreach ($spec['indexes'] as $expected) {
                $key = json_encode($expected['keys'], JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $matches = array_values(array_filter($indexes, static fn ($index) => $index->getKey() === $expected['keys']));
                if (count($matches) !== 1 || $matches[0]->getName() !== $expected['name']) {
                    throw new RuntimeException;
                }
                foreach (['unique', 'partialFilterExpression', 'expireAfterSeconds'] as $option) {
                    $default = $option === 'unique' ? false : null;
                    $actual = isset($matches[0][$option]) ? $normal($matches[0][$option]) : $default;
                    if ($actual != ($expected[$option] ?? $default)) {
                        throw new RuntimeException;
                    }
                }
            }
            $implicitId = isset($seen[json_encode(['_id' => 1])]) ? 0 : 1;
            $ids = array_values(array_filter($indexes, static fn ($index) => $index->getName() === '_id_' && $index->getKey() === ['_id' => 1]));
            if (count($ids) !== 1 || count($indexes) !== count($seen) + $implicitId) {
                throw new RuntimeException;
            }
            $indexCount += count($seen);
            $physicalIndexes += count($indexes);
        }
        if ($db->migrations->countDocuments() !== 1 || $db->migrations->countDocuments(['migration' => '2026_11_16_000000_create_studentity_collections', 'batch' => 1]) !== 1) {
            throw new RuntimeException;
        }
        $report['databases'][] = ['database' => $name, 'collections' => count($specs), 'indexes' => $indexCount, 'physicalCatalogueCollectionIndexes' => $physicalIndexes];
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Read-only MongoDB catalogue check unavailable or mismatched.\n");
    exit(1);
}
