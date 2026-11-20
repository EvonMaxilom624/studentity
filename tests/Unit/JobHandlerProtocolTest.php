<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\VerifiedJobItemLookup;
use App\Domain\Jobs\VerifiedJobItemReceipt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\HandlerProtocolFixtures;

final class JobHandlerProtocolTest extends TestCase
{
    use HandlerProtocolFixtures;

    private function verifyLookup($response = null, $query = null, $policy = null): VerifiedJobItemLookup
    {
        return VerifiedJobItemLookup::verify($this->itemCommand(), $query ?? $this->protocolQuery(), $response ?? $this->lookup(), $policy ?? $this->policy(), $this->at('00:00:00.000'), $this->at('00:00:59.000'), $this->at('00:02:00.000'));
    }

    private function resolve($cap, $query = null, $policy = null, string $fresh = '00:00:59.000'): array
    {
        return $cap->resolveFor($this->itemCommand(), $query ?? $this->protocolQuery(), $policy ?? $this->policy(), $this->at('00:00:00.000'), $this->at($fresh), $this->at('00:02:00.000'));
    }

    public function test_deterministic_original_item_and_canonical_receipt_round_trip(): void
    {
        $c = $this->itemCommand();
        self::assertSame($this->job()->data['items'][0]['resultId'], $c->data['itemOperationId']);
        self::assertSame($c->wire(), JobItemCommand::forItem($this->job(), array_reverse($this->job()->data['items'][0], true), 'synthetic-handler', 'handler-1')->wire());
        self::assertSame($c->fingerprint(), JobItemCommand::fromJson($c->wire())->fingerprint());
        $cap = VerifiedJobItemReceipt::verify($c, $this->receipt(), $this->policy(), $this->at('00:00:00.000'), $this->at('00:02:00.000'));
        self::assertSame('succeeded', $cap->outcomeFor($c, $this->policy(), $this->at('00:00:00.000'), $this->at('00:02:00.000'))->result);
        self::assertSame([], get_object_vars($cap));
    }

    #[DataProvider('receiptAttacks')]
    public function test_validly_signed_receipt_with_wrong_original_binding_or_time_denies(string $field, string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VerifiedJobItemReceipt::verify($this->itemCommand(), $this->receipt([$field => $value]), $this->policy(), $this->at('00:00:00.000'), $this->at('00:02:00.000'));
    }

    public static function receiptAttacks(): array
    {
        $uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $hash = str_repeat('f', 64);

        return array_merge(array_map(fn ($f) => [$f, $uuid], ['jobOperationId', 'itemOperationId', 'targetId', 'correlationId', 'requestedBy']), array_map(fn ($f) => [$f, $hash], ['jobFingerprint', 'itemFingerprint', 'requestFingerprint']), [['authorityNamespace', 'different-handler'], ['issuerGeneration', 'handler-2'], ['signingKeyId', 'unknown-key'], ['committedAt', '2026-11-18T23:59:59.999Z'], ['committedAt', '2026-11-19T00:02:00.001Z']]);
    }

    #[DataProvider('lookupStates')]
    public function test_all_lookup_states_have_closed_verified_consumption(string $status): void
    {
        $cap = $this->verifyLookup($this->lookup($status));
        $r = $this->resolve($cap);
        self::assertSame($status, $r['status']);
        self::assertSame($status === 'committed', $r['receipt'] !== null);
        self::assertSame([], get_object_vars($cap));
    }

    public static function lookupStates(): array
    {
        return [['unknown'], ['committed'], ['not-committed']];
    }

    #[DataProvider('lookupAttacks')]
    public function test_signed_lookup_wrong_query_scope_clock_or_ledger_denies(string $field, mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->verifyLookup($this->lookup('not-committed', [$field => $value]));
    }

    public static function lookupAttacks(): array
    {
        return [['nonce', str_repeat('f', 64)], ['queryFingerprint', str_repeat('f', 64)], ['requestFingerprint', str_repeat('f', 64)], ['itemOperationId', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], ['jobOperationId', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], ['checkedAt', '2026-11-19T00:00:59.999Z'], ['checkedAt', '2026-11-19T00:02:00.001Z'], ['historyComplete', false], ['signingKeyId', 'untrusted'], ['issuerGeneration', 'handler-2']];
    }

    public function test_cached_cap_cannot_change_query_or_use_stale_matching_challenge(): void
    {
        $cap = $this->verifyLookup($this->lookup('not-committed'));
        $error = null;
        try {
            $this->resolve($cap, $this->protocolQuery(str_repeat('b', 64)));
        } catch (\InvalidArgumentException $e) {
            $error = $e;
        }self::assertNotNull($error);
        $this->expectException(\InvalidArgumentException::class);
        $this->resolve($cap, fresh: '00:01:00.001');
    }

    public function test_current_outer_key_does_not_hide_revoked_nested_receipt_key(): void
    {
        $cap = $this->verifyLookup();
        $policy = $this->policy(fn ($body, $proof, $key) => $key === 'lookup-key' && hash_equals(hash_hmac('sha256', $body, 'synthetic-handler-proof:'.$key), $proof));
        $this->expectException(\InvalidArgumentException::class);
        $this->resolve($cap, policy: $policy);
    }

    public function test_receipt_consumption_reverifies_current_policy(): void
    {
        $c = $this->itemCommand();
        $cap = VerifiedJobItemReceipt::verify($c, $this->receipt(), $this->policy(), $this->at('00:00:00.000'), $this->at('00:02:00.000'));
        $this->expectException(\InvalidArgumentException::class);
        $cap->outcomeFor($c, $this->policy(static fn () => false), $this->at('00:00:00.000'), $this->at('00:02:00.000'));
    }

    #[DataProvider('contradictions')]
    public function test_status_and_nested_receipt_cannot_contradict_each_other(string $status, bool $nested): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lookup($status, ['receipt' => $nested ? $this->receipt()->data : null]);
    }

    public static function contradictions(): array
    {
        return [['committed', false], ['not-committed', true]];
    }

    public function test_nested_receipt_cannot_commit_after_lookup_observation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->verifyLookup($this->lookup('committed', ['receipt' => $this->receipt(['committedAt' => '2026-11-19T00:01:01.001Z'])->data]));
    }

    public function test_closed_wire_rejects_secrets_duplicates_and_oversize_and_detaches_aliases(): void
    {
        $data = $this->lookup()->data;
        $receipt = $data['receipt'];
        $data['receipt'] = &$receipt;
        $raw = new JobItemLookupResponse($data);
        $wire = $raw->wire();
        $receipt['result'] = 'failed';
        self::assertSame($wire, $raw->wire());
        $dupError = null;
        try {
            JobItemLookupResponse::fromJson('{"purpose":"x","purpose":"y"}');
        } catch (\InvalidArgumentException $e) {
            $dupError = $e;
        }
        self::assertInstanceOf(\InvalidArgumentException::class, $dupError);
        $oversizeError = null;
        try {
            JobItemLookupResponse::fromJson(str_repeat(' ', 16385));
        } catch (\InvalidArgumentException $e) {
            $oversizeError = $e;
        }
        self::assertInstanceOf(\InvalidArgumentException::class, $oversizeError);
        $data = $this->itemCommand()->data;
        $data['otp'] = '123456';
        $this->expectException(\InvalidArgumentException::class);
        new JobItemCommand($data);
    }

    public function test_member_item_must_match_original_closed_job_item(): void
    {
        $item = $this->job()->data['items'][0];
        $item['auditId'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $this->expectException(\InvalidArgumentException::class);
        JobItemCommand::forItem($this->job(), $item, 'synthetic-handler', 'handler-1');
    }

    public function test_proof_callback_failures_are_strict_and_sanitized(): void
    {
        foreach ([static fn () => 1, static function () {
            throw new \RuntimeException('synthetic-secret');
        }] as $verify) {
            $error = null;
            try {
                $this->verifyLookup(policy: $this->policy($verify));
            } catch (\InvalidArgumentException $e) {
                $error = $e;
            }self::assertNotNull($error);
            self::assertStringNotContainsString('synthetic-secret', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    #[DataProvider('queryAttacks')]
    public function test_caller_query_must_match_original_command_before_response_processing(string $field, string $value): void
    {
        $d = $this->protocolQuery()->data;
        $d[$field] = $value;
        $this->expectException(\InvalidArgumentException::class);
        $this->verifyLookup($this->lookup('unknown'), new JobItemLookupQuery($d));
    }

    public static function queryAttacks(): array
    {
        return [['requestFingerprint', str_repeat('b', 64)], ['jobOperationId', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], ['expectedIssuerGeneration', 'handler-2']];
    }

    #[DataProvider('invalidWindows')]
    public function test_trusted_window_bounds_and_original_creation_order_are_required(string $original, string $fresh, string $observed): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VerifiedJobItemLookup::verify($this->itemCommand(), $this->protocolQuery(), $this->lookup(), $this->policy(), $this->at($original), $this->at($fresh), $this->at($observed));
    }

    public static function invalidWindows(): array
    {
        return [['00:03:00.000', '00:00:59.000', '00:02:00.000'], ['00:00:00.000', '00:03:00.000', '00:02:00.000'], ['00:01:00.001', '00:00:59.000', '00:02:00.000']];
    }

    public function test_unsigned_unknown_state_cannot_smuggle_evidenced_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobItemLookupResponse(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => 'unknown', 'receipt' => $this->receipt()->data]);
    }

    public function test_policy_observation_horizon_still_applies_when_caller_window_is_later(): void
    {
        $r = $this->lookup('not-committed', ['checkedAt' => '2026-11-19T00:11:00.000Z']);
        $this->expectException(\InvalidArgumentException::class);
        VerifiedJobItemLookup::verify($this->itemCommand(), $this->protocolQuery(), $r, $this->policy(), $this->at('00:00:00.000'), $this->at('00:00:59.000'), $this->at('00:12:00.000'));
    }

    public function test_protocol_artifact_matches_implemented_closed_field_sets(): void
    {
        $d = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/proposals/fnd04_handler_protocol_v1.json'), true, 128, JSON_THROW_ON_ERROR);
        foreach (['JobItemCommand' => $this->itemCommand()->data, 'JobItemReceipt' => $this->receipt()->data, 'JobItemLookupQuery' => $this->protocolQuery()->data] as $name => $fields) {
            self::assertEqualsCanonicalizing($d['contracts'][$name]['fields'], array_keys($fields));
            self::assertSame($d['contracts'][$name]['purpose'], $fields['purpose']);
        }
        self::assertEqualsCanonicalizing($d['contracts']['JobItemLookupResponse']['evidencedFields'], array_keys($this->lookup()->data));
        self::assertEqualsCanonicalizing($d['contracts']['JobItemLookupResponse']['unknownFields'], array_keys($this->lookup('unknown')->data));
    }
}
