<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoDriverException;

/**
 * Phase M0 initial MongoDB collections, BSON validators and indexes.
 * No student/academic/production data or reference records are seeded.
 * No destructive down(): disposal must be independently approved.
 */
return new class extends Migration {
    protected $connection = 'mongodb';

    public function up(): void
    {
        $uri = config('database.connections.mongodb.dsn');
        $databaseName = config('database.connections.mongodb.database');
        if (!is_string($uri) || $uri === '' || !is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('MONGODB_URI and MONGODB_DATABASE must be configured.');
        }
        if (!str_starts_with($databaseName, 'studentity_dev') && !str_starts_with($databaseName, 'studentity_test')) {
            throw new RuntimeException('M0 migration is restricted to isolated studentity_dev* or studentity_test* databases.');
        }
        $cataloguePath = base_path('database/mongodb/collection_catalogue.json');
        $catalogue = json_decode(file_get_contents($cataloguePath), true, 512, JSON_THROW_ON_ERROR);
        $db = (new Client($uri))->selectDatabase($databaseName);
        // Collection creation must occur before indexes; schema is migration-managed, not dynamically pushed on app boot.
        foreach ($catalogue['collections'] as $spec) {
            $name = $spec['name'];
            $existing = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false);
            if (count($existing) === 0) {
                $db->createCollection($name, [
                    'validator' => $spec['validator'],
                    'validationLevel' => 'strict',
                    'validationAction' => 'error',
                ]);
            } else {
                $db->command([
                    'collMod' => $name,
                    'validator' => $spec['validator'],
                    'validationLevel' => 'strict',
                    'validationAction' => 'error',
                ]);
            }
            $collection = $db->selectCollection($name);
            $seenKeys = [];
            foreach ($spec['indexes'] as $index) {
                $keys = $index['keys'];
                $keyJson = json_encode($keys);
                if (isset($seenKeys[$keyJson])) {
                    continue;
                }
                $seenKeys[$keyJson] = true;
                $options = ['name' => $index['name']];
                foreach (['unique', 'partialFilterExpression', 'expireAfterSeconds'] as $option) {
                    if (array_key_exists($option, $index)) {
                        $options[$option] = $index[$option];
                    }
                }
                try {
                    $collection->createIndex($keys, $options);
                } catch (\MongoDB\Driver\Exception\CommandException $e) {
                    if (!str_contains($e->getMessage(), 'already exists with a different name')) {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: legal-hold-aware destruction requires separately authorized records process.
    }
};
