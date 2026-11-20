<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Jobs\JobItemOutcome;
use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Security\Authority\ReceiptContractViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceJobContractTest extends TestCase
{
    private function data(): array
    {
        return ['purpose' => 'studentity.job.command.v1', 'schemaVersion' => 1, 'operationId' => '11111111-1111-4111-8111-111111111111', 'correlationId' => '22222222-2222-4222-8222-222222222222', 'enqueueAuditId' => '33333333-3333-4333-8333-333333333333', 'finishAuditId' => '44444444-4444-4444-8444-444444444444', 'requestedBy' => null, 'jobType' => 'synthetic.reference-check.v1', 'items' => [['targetId' => '55555555-5555-4555-8555-555555555555', 'resultId' => '66666666-6666-4666-8666-666666666666', 'auditId' => '77777777-7777-4777-8777-777777777777']]];
    }

    public function test_closed_command_round_trip_detaches_nested_references(): void
    {
        $data = $this->data();
        $items = $data['items'];
        $data['items'] = &$items;
        $job = new ReferenceJobCommand($data);
        $before = $job->wire();
        $items[0]['targetId'] = 'changed';
        self::assertSame($before, $job->wire());
        self::assertSame($job->fingerprint(), ReferenceJobCommand::fromJson($job->wire())->fingerprint());
        self::assertSame(hash('sha256', $job->wire()), $job->fingerprint());
    }

    #[DataProvider('attacks')]
    public function test_unknown_secret_fields_invalid_types_and_ambiguous_ids_are_rejected(string $attack): void
    {
        $d = $this->data();
        switch ($attack) {
            case 'secret':$d['password'] = 'synthetic-secret';
                break;
            case 'email':$d['items'][0]['email'] = 'synthetic@example.invalid';
                break;
            case 'actor':$d['requestedBy'] = 'synthetic@example.invalid';
                break;
            case 'type':$d['schemaVersion'] = '1';
                break;
            case 'handler':$d['jobType'] = 'App\\UnsafeHandler';
                break;
            case 'empty':$d['items'] = [];
                break;
            case 'many':$d['items'] = array_fill(0, 101, $d['items'][0]);
                break;
            case 'targets':$d['items'][] = $d['items'][0];
                break;
            case 'child':$d['items'][0]['resultId'] = $d['finishAuditId'];
                break;
            case 'object':$d['items'][0]['targetId'] = (object) ['value' => 'x'];
                break;
        }
        $this->expectException(\InvalidArgumentException::class);
        new ReferenceJobCommand($d);
    }

    public static function attacks(): array
    {
        return array_map(static fn ($v) => [$v], ['secret', 'email', 'actor', 'type', 'handler', 'empty', 'many', 'targets', 'child', 'object']);
    }

    public function test_wire_rejects_duplicate_members_and_oversize_with_parser_failure(): void
    {
        // Oversize by source length, otherwise-valid JSON: rejects at
        // CanonicalAuthorityJson::decodeObject length check (intended parser
        // cause) with ReceiptContractViolation, not JSON syntax or InvalidArgumentException.
        $this->expectException(ReceiptContractViolation::class);
        $this->expectExceptionMessage('Authority object exceeds protocol limit.');
        ReferenceJobCommand::fromJson(str_repeat('x', 16385));
    }

    public function test_wire_rejects_non_canonical_duplicate_members_with_parser_failure(): void
    {
        // Valid-by-json_decode JSON, but non-canonical (duplicate 'purpose' member).
        // decodeObject re-encodes and requires source == re-encode, so duplicate
        // members fail the hash_equals check (intended canonicalness parser cause)
        // with ReceiptContractViolation('Canonical authority object required.').
        $this->expectException(ReceiptContractViolation::class);
        $this->expectExceptionMessage('Canonical authority object required.');
        ReferenceJobCommand::fromJson('{"purpose":"a","purpose":"b"}');
    }

    public function test_parser_duplicate_member_rejected_before_shape_key_contract(): void
    {
        $d = $this->data();
        $d['items'][0]['resultId'] = $d['finishAuditId'];
        $d['purpose'] = 'secrets.ambiguous.v1';
        $d['schemaVersion'] = 1;
        $d['operationId'] = $d['operationId'];
        $d['correlationId'] = $d['correlationId'];
        $d['enqueueAuditId'] = $d['enqueueAuditId'];
        $d['finishAuditId'] = $d['finishAuditId'];
        $d['requestedBy'] = null;
        $d['jobType'] = 'synthetic.reference-check.v1';
        $d['items'] = [['targetId' => $d['items'][0]['targetId'], 'resultId' => '66666666-6666-4666-8666-666666666666', 'auditId' => $d['items'][0]['auditId']]];
        // Valid-by-construct payload: all keys present, no unknown keys.
        $this->expectException(ReceiptContractViolation::class);
        new ReferenceJobCommand($d);
    }

    #[DataProvider('badOutcomes')]
    public function test_outcome_pairs_are_closed_and_coherent(string $result, string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobItemOutcome($result, $code);
    }

    public static function badOutcomes(): array
    {
        return [['succeeded', 'synthetic_failure'], ['failed', 'none'], ['skipped', 'none'], ['unknown', 'none'], ['failed', 'raw-secret']];
    }

    public function test_policy_requires_explicit_closed_synthetic_limits(): void
    {
        $p = new JobPolicy('synthetic-job-policy-v1', 3, 60000);
        self::assertSame(3, $p->maxAttempts);
        self::assertSame(60000, $p->leaseMilliseconds);
        $this->expectException(\InvalidArgumentException::class);
        new JobPolicy('unapproved-policy', 100, 0);
    }
}
