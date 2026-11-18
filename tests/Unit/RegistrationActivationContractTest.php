<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Registration\ActivationCommand;
use App\Domain\Registration\ActivationDecision;
use App\Domain\Registration\ActivationLookup;
use App\Domain\Registration\RegistrationClaim;
use App\Domain\Registration\VerifiedActivationAbsence;
use App\Domain\Registration\VerifiedActivationDecision;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\RegistrationActivationFixtures;

final class RegistrationActivationContractTest extends TestCase
{
    use RegistrationActivationFixtures;

    public function test_canonical_claim_command_and_historical_decision_round_trip_without_synthesizing_issued_state(): void
    {
        $claim = new RegistrationClaim($this->claimData());
        $command = $this->command($claim);
        $decision = $this->decision($command);
        self::assertSame($claim->canonical(), RegistrationClaim::fromJson($claim->wire())->canonical());
        self::assertSame($command->fingerprint(), ActivationCommand::fromJson($command->wire())->fingerprint());
        self::assertSame($decision->wire(), ActivationDecision::fromJson($decision->wire())->wire());
        $verified = VerifiedActivationDecision::verify($command, $decision, $this->policy(), $this->policy(true));
        self::assertSame('allowed', $verified->decision->data['result']);
        self::assertSame('reserved', $verified->allocation->allocation()->state);
        self::assertSame($decision->fingerprint(), VerifiedActivationDecision::verify($command, $decision, $this->policy(), $this->policy(true))->decision->fingerprint());
        self::assertSame('committed', $this->resolveLookup(ActivationLookup::committed($claim->data['operationId'], $verified))['status']);
    }

    #[DataProvider('decisionAttacks')]
    public function test_validly_signed_decision_with_wrong_binding_or_causality_is_rejected(string $field, string $value): void
    {
        $command = $this->command();
        $decision = $this->decision($command, [$field => $value]);
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationDecision::verify($command, $decision, $this->policy(), $this->policy(true));
    }

    public static function decisionAttacks(): array
    {
        return [['operationId', '22222222-2222-4222-8222-222222222222'], ['userId', '22222222-2222-4222-8222-222222222222'],
            ['intentFingerprint', str_repeat('d', 64)], ['sealedPayloadHash', str_repeat('d', 64)], ['noticeBundleFingerprint', str_repeat('d', 64)],
            ['claimFingerprint', str_repeat('d', 64)], ['requestFingerprint', str_repeat('d', 64)], ['allocationFingerprint', str_repeat('d', 64)],
            ['systemId', 'synthetic-other'], ['correlationId', '22222222-2222-4222-8222-222222222222'], ['authorityNamespace', 'other'], ['issuerGeneration', 'admission-0'], ['signingKeyId', 'untrusted'],
            ['committedAt', '2026-11-18T00:00:01.000Z'], ['committedAt', '2026-11-18T00:05:00.000Z'], ['committedAt', '2026-11-18T00:11:00.000Z']];
    }

    public function test_different_claim_child_ids_cannot_reuse_original_admission(): void
    {
        $original = $this->command();
        $data = $this->claimData();
        $data['childIds']['emailId'] = '88888888-8888-4888-8888-888888888888';
        $changed = $this->command(new RegistrationClaim($data));
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationDecision::verify($changed, $this->decision($original), $this->policy(), $this->policy(true));
    }

    public function test_forged_nested_allocation_is_rejected_even_when_admission_signature_is_valid(): void
    {
        $data = $this->command()->data;
        $data['allocation']['proof'] = 'forged';
        $command = new ActivationCommand($data);
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationDecision::verify($command, $this->decision($command), $this->policy(), $this->policy(true));
    }

    public function test_retired_reservation_is_verifiable_history_but_cannot_prepare_a_new_command(): void
    {
        $claim = new RegistrationClaim($this->claimData());
        $verified = VerifiedAllocationReservation::verify($claim, $this->reservation($claim, ['state' => 'retired', 'revision' => '2']), $this->policy(true));
        self::assertSame('retired', $verified->allocation()->state);
        $this->expectException(ReceiptContractViolation::class);
        ActivationCommand::prepare($claim, $verified, 'synthetic-registration', 'admission-1', '2026-11-18T00:00:02.000Z');
    }

    public function test_revision_is_exact_through_bson_long_maximum(): void
    {
        $claim = new RegistrationClaim($this->claimData());
        $receipt = $this->reservation($claim, ['revision' => '9223372036854775807']);
        self::assertSame(PHP_INT_MAX, VerifiedAllocationReservation::verify($claim, $receipt, $this->policy(true))->allocation()->revision);
        $this->expectException(ReceiptContractViolation::class);
        $this->reservation($claim, ['revision' => '9223372036854775808']);
    }

    #[DataProvider('reservationAttacks')]
    public function test_valid_signature_cannot_hide_wrong_allocation_scope_or_time(string $field, string $value): void
    {
        $claim = new RegistrationClaim($this->claimData());
        $raw = $this->reservation($claim, [$field => $value]);
        $this->expectException(ReceiptContractViolation::class);
        VerifiedAllocationReservation::verify($claim, $raw, $this->policy(true));
    }

    public static function reservationAttacks(): array
    {
        return [['operationId', '22222222-2222-4222-8222-222222222222'], ['userId', '22222222-2222-4222-8222-222222222222'], ['intentFingerprint', str_repeat('d', 64)], ['authorityNamespace', 'other'], ['issuerGeneration', 'issuer-0'], ['signingKeyId', 'other'], ['committedAt', '2026-11-17T23:59:59.999Z'], ['committedAt', '2026-11-18T00:11:00.000Z']];
    }

    public function test_rejection_after_deadline_remains_verifiable_history_and_child_credential_id_can_be_present(): void
    {
        $data = $this->claimData();
        $data['childIds']['credentialId'] = '88888888-8888-4888-8888-888888888888';
        $command = $this->command(new RegistrationClaim($data));
        $decision = $this->decision($command, ['result' => 'rejected', 'committedAt' => '2026-11-18T00:06:00.000Z']);
        self::assertSame('rejected', VerifiedActivationDecision::verify($command, $decision, $this->policy(), $this->policy(true))->decision->data['result']);
        self::assertSame($data['childIds']['credentialId'], ActivationCommand::fromJson($command->wire())->data['claim']['childIds']['credentialId']);
    }

    public function test_forged_decision_and_changed_preparation_cannot_reuse_a_valid_proof(): void
    {
        $command = $this->command();
        $raw = $this->decision($command)->data;
        $raw['proof'] = 'forged';
        $error = null;
        try {
            VerifiedActivationDecision::verify($command, new ActivationDecision($raw), $this->policy(), $this->policy(true));
        } catch (ReceiptContractViolation $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(ReceiptContractViolation::class, $error);
        $data = $command->data;
        $data['preparedAt'] = '2026-11-18T00:00:02.001Z';
        $changed = new ActivationCommand($data);
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationDecision::verify($changed, $this->decision($command), $this->policy(), $this->policy(true));
    }

    public function test_command_snapshot_detaches_deep_references(): void
    {
        $data = $this->command()->data;
        $ref = $data['claim']['childIds']['emailId'];
        $data['claim']['childIds']['emailId'] = &$ref;
        $command = new ActivationCommand($data);
        $wire = $command->wire();
        $ref = 'forged';
        self::assertSame($wire, $command->wire());
    }

    public function test_proof_failure_nonboolean_and_callback_errors_never_verify(): void
    {
        $command = $this->command();
        $decision = $this->decision($command);
        foreach ([fn () => false, fn () => 1, fn () => throw new \RuntimeException('synthetic-secret')] as $callback) {
            $error = null;
            try {
                VerifiedActivationDecision::verify($command, $decision, $this->policy(false, $callback), $this->policy(true));
            } catch (ReceiptContractViolation $caught) {
                $error = $caught;
            }
            self::assertInstanceOf(ReceiptContractViolation::class, $error);
            self::assertFalse(str_contains($error->getMessage(), 'synthetic-secret'));
        }
    }

    public function test_snapshot_detaches_nested_aliases_and_rejects_recursive_values_before_encoding(): void
    {
        $data = $this->claimData();
        $email = $data['childIds']['emailId'];
        $data['childIds']['emailId'] = &$email;
        $claim = new RegistrationClaim($data);
        $wire = $claim->wire();
        $email = 'forged';
        self::assertSame($wire, $claim->wire());
        $data = $this->claimData();
        $data['childIds']['emailId'] = &$data;
        $this->expectException(ReceiptContractViolation::class);
        new RegistrationClaim($data);
    }

    #[DataProvider('invalidClaims')]
    public function test_closed_claim_and_stable_identity_rules_are_enforced(string $attack): void
    {
        $data = $this->claimData();
        switch ($attack) {
            case 'unknown':$data['password'] = 'synthetic-secret';
                break;
            case 'identity':$data['userId'] = $data['challengeId'];
                break;
            case 'deadline':$data['processingDeadline'] = $data['verifiedAt'];
                break;
            case 'ack-collision':$data['childIds']['privacyAckId'] = $data['childIds']['termsAckId'];
                break;
            case 'child-extra':$data['childIds']['untrusted'] = 'x';
                break;
            case 'child-missing':unset($data['childIds']['credentialId']);
                break;
            case 'time':$data['verifiedAt'] .= "\0";
                break;
            case 'version':$data['schemaVersion'] = '1';
                break;
        }
        $this->expectException(ReceiptContractViolation::class);
        new RegistrationClaim($data);
    }

    public static function invalidClaims(): array
    {
        return array_map(fn ($v) => [$v], ['unknown', 'identity', 'deadline', 'ack-collision', 'child-extra', 'child-missing', 'time', 'version']);
    }

    public function test_duplicate_wire_members_and_large_wire_are_rejected(): void
    {
        foreach (['{"schemaVersion":1,'.substr((new RegistrationClaim($this->claimData()))->wire(), 1), str_repeat(' ', 16385)] as $wire) {
            $error = null;
            try {
                RegistrationClaim::fromJson($wire);
            } catch (ReceiptContractViolation $caught) {
                $error = $caught;
            }
            self::assertInstanceOf(ReceiptContractViolation::class, $error);
        }
    }

    public function test_signed_noncommit_requires_nonce_complete_ledger_and_bound_operation(): void
    {
        $data = $this->absenceData();
        unset($data['proof']);
        $data['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($data), $this->proofKey(false));
        $proof = VerifiedActivationAbsence::verify($data, $data['operationId'], $data['nonce'], $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
        self::assertSame('not-committed', $this->resolveLookup(ActivationLookup::notCommitted($data['operationId'], $data['nonce'], $proof))['status']);
        self::assertSame('unknown', $this->resolveLookup(ActivationLookup::unknown())['status']);
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationAbsence::verify($data, $data['operationId'], hash('sha256', 'replayed-nonce'), $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
    }

    #[DataProvider('absenceAttacks')]
    public function test_noncommit_with_valid_proof_but_wrong_scope_time_or_generation_denies(string $attack): void
    {
        $data = $this->absenceData();
        switch ($attack) {
            case 'partial':$data['historyComplete'] = false;
                break;
            case 'stale':$data['checkedAt'] = '2026-11-18T00:00:02.000Z';
                break;
            case 'future':$data['checkedAt'] = '2026-11-18T00:11:00.000Z';
                break;
            case 'issuer':$data['issuerGeneration'] = 'admission-0';
                break;
            case 'operation':$data['operationId'] = '22222222-2222-4222-8222-222222222222';
                break;
        }
        unset($data['proof']);
        $data['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($data), $this->proofKey(false));
        $this->expectException(ReceiptContractViolation::class);
        VerifiedActivationAbsence::verify($data, $this->claimData()['operationId'], $this->absenceData()['nonce'], $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
    }

    public static function absenceAttacks(): array
    {
        return [['partial'], ['stale'], ['future'], ['issuer'], ['operation']];
    }

    #[DataProvider('claimReplacementPaths')]
    public function test_replacing_claim_time_cannot_create_an_impossible_prepared_command(string $path): void
    {
        $claimA = new RegistrationClaim($this->claimData());
        $allocation = VerifiedAllocationReservation::verify($claimA, $this->reservation($claimA), $this->policy(true));
        $data = $this->claimData();
        $data['verifiedAt'] = '2026-11-18T00:00:01.500Z';
        $this->expectException(ReceiptContractViolation::class);
        if ($path === 'prepare') {
            ActivationCommand::prepare(new RegistrationClaim($data), $allocation, 'synthetic-registration', 'admission-1', '2026-11-18T00:00:02.000Z');
        } else {
            $command = $this->command()->data;
            $command['claim'] = $data;
            ActivationCommand::fromJson(CanonicalAuthorityJson::encode($command));
        }
    }

    public static function claimReplacementPaths(): array
    {
        return [['prepare'], ['wire']];
    }

    public function test_already_verified_noncommit_result_cannot_be_consumed_under_a_new_query(): void
    {
        $d = $this->absenceData();
        unset($d['proof']);
        $d['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($d), $this->proofKey(false));
        $absence = VerifiedActivationAbsence::verify($d, $d['operationId'], $d['nonce'], $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
        $lookup = ActivationLookup::notCommitted($d['operationId'], $d['nonce'], $absence);
        $this->expectException(ReceiptContractViolation::class);
        $lookup->resolveFor($d['operationId'], hash('sha256', 'new-query'), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'), new \DateTimeImmutable('2026-11-18T00:10:00.000Z'));
    }

    public function test_lookup_result_cannot_be_rebound_to_another_operation_and_absence_wire_round_trips(): void
    {
        $d = $this->absenceData();
        unset($d['proof']);
        $d['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($d), $this->proofKey(false));
        $wire = CanonicalAuthorityJson::encode($d);
        $absence = VerifiedActivationAbsence::verifyJson($wire, $d['operationId'], $d['nonce'], $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
        self::assertSame($wire, $absence->wire());
        $error = null;
        try {
            ActivationLookup::notCommitted('22222222-2222-4222-8222-222222222222', $d['nonce'], $absence);
        } catch (ReceiptContractViolation $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(ReceiptContractViolation::class, $error);
        $command = $this->command();
        $decision = VerifiedActivationDecision::verify($command, $this->decision($command), $this->policy(), $this->policy(true));
        $this->expectException(ReceiptContractViolation::class);
        ActivationLookup::committed('22222222-2222-4222-8222-222222222222', $decision);
    }

    private function resolveLookup(ActivationLookup $lookup): array
    {
        $d = $this->absenceData();

        return $lookup->resolveFor($d['operationId'], $d['nonce'], new \DateTimeImmutable('2026-11-18T00:00:03.000Z'), new \DateTimeImmutable('2026-11-18T00:10:00.000Z'));
    }

    #[DataProvider('lookupObservationWindows')]
    public function test_verified_noncommit_cannot_be_consumed_outside_current_observation_window(string $earliest, string $latest): void
    {
        $d = $this->absenceData();
        unset($d['proof']);
        $d['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($d), $this->proofKey(false));
        $absence = VerifiedActivationAbsence::verify($d, $d['operationId'], $d['nonce'], $this->policy(), new \DateTimeImmutable('2026-11-18T00:00:03.000Z'));
        $lookup = ActivationLookup::notCommitted($d['operationId'], $d['nonce'], $absence);
        $this->expectException(ReceiptContractViolation::class);
        $lookup->resolveFor($d['operationId'], $d['nonce'], new \DateTimeImmutable($earliest), new \DateTimeImmutable($latest));
    }

    public static function lookupObservationWindows(): array
    {
        return [
            ['2026-11-18T00:00:05.000Z', '2026-11-18T00:10:00.000Z'],
            ['2026-11-18T00:00:01.000Z', '2026-11-18T00:00:03.000Z'],
            ['2026-11-18T00:00:05.000Z', '2026-11-18T00:00:03.000Z'],
        ];
    }
}
