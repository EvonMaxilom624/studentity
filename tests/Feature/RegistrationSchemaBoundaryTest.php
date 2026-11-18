<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Registration\RegistrationLabDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RegistrationSchemaBoundaryTest extends TestCase
{
    #[DataProvider('metadataAttacks')]
    public function test_closed_metadata_rejects_weak_validation_and_changed_indexes_without_repair(string $attack): void
    {
        $d = RegistrationLabDatabase::definitions()['registration_intents_v1'];
        $options = $d['options'];
        $indexes = [['name' => '_id_', 'key' => ['_id' => 1]]];
        foreach ($d['indexes'] as $i) {
            $key = $i['keys'];
            unset($i['keys']);
            $indexes[] = $i + ['key' => $key];
        }
        RegistrationLabDatabase::assertMetadata($options, $indexes, $d);
        switch ($attack) {
            case 'weak':$options['validationLevel'] = 'moderate';
                break;
            case 'unknown':$options['capped'] = true;
                break;
            case 'missing':array_pop($indexes);
                break;
            case 'ttl':$indexes[1]['expireAfterSeconds'] = 1;
                break;
            case 'order':$indexes[1]['key'] = array_reverse($indexes[1]['key'], true);
                break;
            case 'partial':$indexes[1]['partialFilterExpression'] = ['state' => 'pending'];
                break;
        }
        $this->expectException(\RuntimeException::class);
        RegistrationLabDatabase::assertMetadata($options, $indexes, $d);
    }

    public static function metadataAttacks(): array
    {
        return [['weak'], ['unknown'], ['missing'], ['ttl'], ['order'], ['partial']];
    }

    public function test_schema_pin_ignores_wire_line_endings_but_rejects_changed_schema(): void
    {
        $wire = file_get_contents(base_path('database/mongodb/proposals/iam01_registration_v1.json'));
        $lf = str_replace("\r\n", "\n", $wire);
        self::assertSame(RegistrationLabDatabase::typed(RegistrationLabDatabase::proposalCollections($lf)), RegistrationLabDatabase::typed(RegistrationLabDatabase::proposalCollections(str_replace("\n", "\r\n", $lf))));
        $d = json_decode($wire, true, 128, JSON_THROW_ON_ERROR);
        $d['collections'][0]['options']['validationAction'] = 'warn';
        $this->expectException(\RuntimeException::class);
        RegistrationLabDatabase::proposalCollections(json_encode($d, JSON_THROW_ON_ERROR));
    }
}
