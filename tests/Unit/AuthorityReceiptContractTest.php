<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\ReceiptAuthorityLab;

final class AuthorityReceiptContractTest extends TestCase
{
    use AuthorityReceiptFixtures;

    public function test_canonical_command_has_independently_pinned_jcs_bytes_and_fingerprint(): void
    {
        $command = new RevocationCommand($this->commandData());
        $expected = '{"action":"session.revoke","authorityNamespace":"synthetic-authority","authorizationDigest":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","correlationId":"33333333-3333-4333-8333-333333333333","expectedCheckpointRef":"checkpoint-0","expectedIssuerGeneration":"generation-1","operationId":"11111111-1111-4111-8111-111111111111","purpose":"studentity.session-revocation.command.v1","schemaVersion":1,"securityEpoch":"4","sessionDigest":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","subjectUserId":"22222222-2222-4222-8222-222222222222"}';
        self::assertSame($expected, $command->canonical());
        self::assertSame('1add097165400fc1bd655319e952e39bd16185fed1105df9a2b92f187c882045', $command->fingerprint());
    }

    public function test_jcs_uses_utf16_property_order_and_preserves_string_escaping(): void
    {
        $value = (object) ['דּ' => 7, '😀' => 6, '€' => 5, 'ö' => 4, "\u{0080}" => 3, '1' => 2, "\r" => 1];
        self::assertSame('{"\r":1,"1":2,"'."\u{0080}".'":3,"ö":4,"€":5,"😀":6,"דּ":7}', CanonicalAuthorityJson::encode($value));
        self::assertSame('{"s":"a\\\\b\n\"/"}', CanonicalAuthorityJson::encode(['s' => "a\\b\n\"/"]));
    }

    #[DataProvider('invalidEpochs')]
    public function test_invalid_or_overflowing_epoch_is_rejected(mixed $epoch): void
    {
        $this->expectException(ReceiptContractViolation::class);
        new RevocationCommand(array_replace($this->commandData(), ['securityEpoch' => $epoch]));
    }

    public static function invalidEpochs(): array
    {
        return [['04'], ['-1'], ['9223372036854775808'], [4]];
    }

    public function test_bson_long_maximum_survives_as_a_decimal_string(): void
    {
        $command = new RevocationCommand(array_replace($this->commandData(), ['securityEpoch' => '9223372036854775807']));
        self::assertStringContainsString('"securityEpoch":"9223372036854775807"', $command->canonical());
    }

    public function test_unknown_fields_are_not_silently_removed_from_the_signed_command(): void
    {
        $this->expectException(ReceiptContractViolation::class);
        new RevocationCommand($this->commandData() + ['untrustedEndpoint' => 'https://invalid.test']);
    }

    #[DataProvider('unsafeNumbers')]
    public function test_unsupported_numeric_canonicalization_fails_closed(mixed $number): void
    {
        $this->expectException(ReceiptContractViolation::class);
        CanonicalAuthorityJson::encode(['n' => $number]);
    }

    public static function unsafeNumbers(): array
    {
        return [[1.5], [9007199254740992]];
    }

    public function test_verified_receipt_maps_stable_original_correlation_and_bson_time_without_claiming_actor(): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $receipt = $authority->revoke($command);
        $verified = $authority->verify($command, $receipt);
        self::assertSame($command->data['operationId'], $verified->journal()['_id']);
        self::assertSame('anchored', $verified->journal()['external_anchor_status']);
        self::assertSame($command->data['correlationId'], $verified->audit()['correlation_id']);
        self::assertArrayNotHasKey('actor_user_id', $verified->audit());
        self::assertSame($command->data['subjectUserId'], $verified->audit()['event_redacted']->subject_user_id);
        self::assertSame($verified->audit()['event_hash']->getData(), $authority->verify($command, $authority->lookup($command->data['operationId'])['receipt'])->audit()['event_hash']->getData());
    }

    #[DataProvider('wrongBindings')]
    public function test_even_validly_signed_receipts_with_wrong_binding_are_rejected(string $field, string $value): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $data = $authority->revoke($command)->data;
        $data[$field] = $value;
        $receipt = $authority->sign($data);
        $this->expectException(ReceiptContractViolation::class);
        $authority->verify($command, $receipt);
    }

    public static function wrongBindings(): array
    {
        return [
            ['subjectUserId', '44444444-4444-4444-8444-444444444444'], ['operationId', '44444444-4444-4444-8444-444444444444'],
            ['sessionDigest', str_repeat('c', 64)], ['requestFingerprint', str_repeat('c', 64)], ['securityEpoch', '5'],
            ['correlationId', '44444444-4444-4444-8444-444444444444'], ['issuerGeneration', 'generation-2'],
            ['authorityNamespace', 'other-authority'], ['signingKeyId', 'other-key'],
        ];
    }

    public function test_forged_proof_cannot_produce_a_verified_receipt_or_journal_mapping(): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $data = $authority->revoke($command)->data;
        $data['proof'] = str_repeat('0', 64);
        $this->expectException(ReceiptContractViolation::class);
        $authority->verify($command, new RevocationReceipt($data));
    }

    public function test_duplicate_json_properties_and_noncanonical_wire_are_rejected(): void
    {
        $authority = new ReceiptAuthorityLab;
        $wire = $authority->revoke(new RevocationCommand($this->commandData()))->wire();
        self::assertSame($wire, RevocationReceipt::fromJson($wire)->wire());
        foreach ([' '.$wire, str_replace('"schemaVersion":1', '"schemaVersion":1,"schemaVersion":1', $wire)] as $bad) {
            try {
                RevocationReceipt::fromJson($bad);
                self::fail('Untrusted wire was accepted.');
            } catch (ReceiptContractViolation) {
                self::assertTrue(true);
            }
        }
    }

    public function test_pending_result_and_invalid_timestamp_cannot_be_acknowledged(): void
    {
        $authority = new ReceiptAuthorityLab;
        $data = $authority->revoke(new RevocationCommand($this->commandData()))->data;
        foreach ([['result' => 'pending'], ['committedAt' => '2026-02-30T00:00:00.123Z'], ['committedAt' => '2026-11-18T00:00:00.123456Z'], ['committedAt' => "2026-11-18\0T00:00:00.123Z"]] as $bad) {
            try {
                new RevocationReceipt(array_replace($data, $bad));
                self::fail('Invalid acknowledgment accepted.');
            } catch (ReceiptContractViolation) {
                self::assertTrue(true);
            }
        }
    }

    public function test_idempotent_retry_returns_original_receipt_and_conflicting_reuse_changes_nothing(): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $receipt = $authority->revoke($command);
        self::assertSame($receipt->wire(), $authority->revoke(new RevocationCommand($this->commandData()))->wire());
        try {
            $authority->revoke(new RevocationCommand(array_replace($this->commandData(), ['securityEpoch' => '5'])));
            self::fail('Conflicting operation was accepted.');
        } catch (ReceiptContractViolation) {
            self::assertSame($receipt->wire(), $authority->lookup($command->data['operationId'])['receipt']->wire());
        }
    }

    public function test_lost_ack_is_resolved_by_the_same_operation_without_repeating_the_change(): void
    {
        $authority = new ReceiptAuthorityLab;
        $authority->loseNextAck = true;
        $command = new RevocationCommand($this->commandData());
        try {
            $authority->revoke($command);
            self::fail('Expected lost acknowledgment.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic acknowledgment lost.', $exception->getMessage());
        }
        $status = $authority->lookup($command->data['operationId']);
        self::assertSame('committed', $status['status']);
        self::assertSame($status['receipt']->wire(), $authority->revoke($command)->wire());
        $authority->unavailable = true;
        self::assertSame('unknown', $authority->lookup($command->data['operationId'])['status']);
    }

    public function test_future_commit_is_rejected_even_with_a_valid_proof(): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $data = $authority->revoke($command)->data;
        $data['committedAt'] = '2026-11-19T00:00:00.123Z';
        $this->expectException(ReceiptContractViolation::class);
        $authority->verify($command, $authority->sign($data));
    }

    public function test_proof_adapter_failure_is_sanitized_and_produces_no_verified_mapping(): void
    {
        $authority = new ReceiptAuthorityLab;
        $command = new RevocationCommand($this->commandData());
        $this->expectException(ReceiptContractViolation::class);
        $this->expectExceptionMessage('Authority receipt proof unavailable.');
        VerifiedRevocationReceipt::verify($command, $authority->revoke($command), 'synthetic-authority', 'generation-1', ['lab-key'],
            static fn () => throw new \RuntimeException('synthetic-secret-provider-message'), new \DateTimeImmutable('2026-11-18T00:00:01.000Z'));
    }

    public function test_distinct_operations_on_an_already_denied_target_keep_both_receipts(): void
    {
        $authority = new ReceiptAuthorityLab;
        $first = new RevocationCommand($this->commandData());
        $receipt = $authority->revoke($first);
        $second = new RevocationCommand(array_replace($this->commandData(), ['operationId' => '44444444-4444-4444-8444-444444444444']));
        self::assertSame('revoked', $authority->revoke($second)->data['result']);
        self::assertSame($receipt->wire(), $authority->lookup($first->data['operationId'])['receipt']->wire());
        self::assertSame('definitivelyNotCommitted', $authority->lookup('55555555-5555-4555-8555-555555555555')['status']);
    }

    public function test_caller_references_cannot_mutate_command_fingerprint(): void
    {
        $data = $this->commandData();
        $subject = $data['subjectUserId'];
        $data['subjectUserId'] = &$subject;
        $command = new RevocationCommand($data);
        $fingerprint = $command->fingerprint();
        $subject = '44444444-4444-4444-8444-444444444444';
        self::assertSame('22222222-2222-4222-8222-222222222222', $command->data['subjectUserId']);
        self::assertSame($fingerprint, $command->fingerprint());
    }

    public function test_caller_references_cannot_mutate_verified_receipt_mapping(): void
    {
        $authority = new ReceiptAuthorityLab;
        $originalCommand = new RevocationCommand($this->commandData());
        $signed = $authority->revoke($originalCommand)->data;
        $receiptSubject = $signed['subjectUserId'];
        $signed['subjectUserId'] = &$receiptSubject;
        $receipt = new RevocationReceipt($signed);
        $verified = $authority->verify($originalCommand, $receipt);
        $hash = $verified->journal()['event_hash']->getData();
        $receiptSubject = '44444444-4444-4444-8444-444444444444';
        self::assertSame('22222222-2222-4222-8222-222222222222', $verified->journal()['subject_user_id']);
        self::assertSame($hash, $verified->journal()['event_hash']->getData());
    }
}
