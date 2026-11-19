<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobItemOutcome;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use MongoDB\BSON\Int64;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OutboxFixtures;
use Tests\TestCase;

final class OutboxSchemaBoundaryTest extends TestCase
{
    use OutboxFixtures;

    #[DataProvider('metadataAttacks')]
    public function test_closed_metadata_rejects_ttl_weak_validation_missing_and_reordered_indexes(string $attack): void
    {
        $d = OutboxLabDatabase::definitions()['audit_events'];
        $options = $d['options'];
        $indexes = [['name' => '_id_', 'key' => ['_id' => 1]]];
        foreach ($d['indexes'] as $i) {
            $keys = $i['keys'];
            unset($i['keys']);
            $indexes[] = $i + ['key' => $keys];
        }
        OutboxLabDatabase::assertMetadata($options, $indexes, $d);
        switch ($attack) {
            case 'weak':$options['validationAction'] = 'warn';
                break;
            case 'missing':array_pop($indexes);
                break;
            case 'ttl':$indexes[1]['expireAfterSeconds'] = 10;
                break;
            case 'order':$indexes[2]['key'] = array_reverse($indexes[2]['key'], true);
                break;
            case 'unique':$indexes[2]['unique'] = false;
                break;
            case 'extra':$indexes[] = ['name' => 'unknown', 'key' => ['action' => 1]];
                break;
        }
        $this->expectException(\RuntimeException::class);
        OutboxLabDatabase::assertMetadata($options, $indexes, $d);
    }

    public static function metadataAttacks(): array
    {
        return [['weak'], ['missing'], ['ttl'], ['order'], ['unique'], ['extra']];
    }

    public function test_read_only_documents_match_proves_closed_job_and_outcome_shape(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $r->enqueue($c);
        $job = $this->db()->background_jobs->findOne(['_id' => $c->data['operationId']]);
        $job['revision'] = new Int64((string) $job['revision']);
        $job['audit_sequence'] = new Int64((string) $job['audit_sequence']);
        $match = OutboxLabDatabase::definitions()['background_jobs']['options']['validator'];
        $check = fn ($candidate, $validator) => count(iterator_to_array($this->db()->aggregate([['$documents' => [$candidate]], ['$match' => $validator]]), false));
        self::assertSame(1, $check($job, $match));
        foreach (['email', 'policy', 'state', 'long'] as $attack) {
            $bad = $job;
            switch ($attack) {
                case 'email':$bad['payload_redacted']['email'] = 'synthetic@example.invalid';
                    break;
                case 'policy':$bad['policy_ref'] = 'synthetic@example.invalid';
                    break;
                case 'state':$bad['status'] = 'running';
                    break;
                case 'long':$bad['revision'] = 0;
                    break;
            }self::assertSame(0, $check($bad, $match));
        }
        $lease = $r->claim($c->data['operationId']);
        $r->record($lease, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
        $result = $this->db()->job_results->findOne(['job_id' => $c->data['operationId']]);
        $validator = OutboxLabDatabase::definitions()['job_results']['options']['validator'];
        self::assertSame(1, $check($result, $validator));
        $result['error_code'] = 'synthetic_failure';
        self::assertSame(0, $check($result, $validator));
    }
}
