<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identifiers\AllocationReservation;
use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Registration\ActivationCommand;
use App\Domain\Registration\ActivationDecision;
use App\Domain\Registration\RegistrationClaim;
use App\Domain\Security\Authority\AuthorityProofPolicy;

/** Synthetic proof fixtures only; no production algorithm, keys, issuer or currentness. */
trait RegistrationActivationFixtures
{
    private function claimData(): array
    {
        return ['purpose' => 'studentity.registration.claim.v1', 'schemaVersion' => 1,
            'operationId' => '11111111-1111-4111-8111-111111111111', 'intentId' => '11111111-1111-4111-8111-111111111111', 'userId' => '11111111-1111-4111-8111-111111111111',
            'intentFingerprint' => str_repeat('a', 64), 'sealedPayloadHash' => str_repeat('b', 64), 'noticeBundleFingerprint' => str_repeat('c', 64),
            'challengeId' => '22222222-2222-4222-8222-222222222222', 'verifiedAt' => '2026-11-18T00:00:00.000Z', 'processingDeadline' => '2026-11-18T00:05:00.000Z',
            'correlationId' => '99999999-9999-4999-8999-999999999999',
            'childIds' => ['emailId' => '33333333-3333-4333-8333-333333333333', 'credentialId' => null, 'termsAckId' => '44444444-4444-4444-8444-444444444444', 'privacyAckId' => '55555555-5555-4555-8555-555555555555', 'auditId' => '66666666-6666-4666-8666-666666666666', 'journalId' => '77777777-7777-4777-8777-777777777777']];
    }

    private function proofKey(bool $issuer): string
    {
        return $issuer ? 'synthetic-allocation-proof-key-only' : 'synthetic-admission-proof-key-only';
    }

    private function policy(bool $issuer = false, ?\Closure $verifier = null): AuthorityProofPolicy
    {
        $key = $issuer ? 'issuer-key' : 'admission-key';

        return new AuthorityProofPolicy($issuer ? 'synthetic-issuer' : 'synthetic-registration', $issuer ? 'issuer-1' : 'admission-1', [$key],
            $verifier ?? fn ($body, $proof, $keyId) => $keyId === $key && hash_equals(hash_hmac('sha256', $body, $this->proofKey($issuer)), $proof), new \DateTimeImmutable('2026-11-18T00:10:00.000Z'));
    }

    private function sign(string $class, array $data, bool $issuer = false): object
    {
        unset($data['proof']);
        $unsigned = new $class($data + ['proof' => 'placeholder']);

        return new $class($data + ['proof' => hash_hmac('sha256', $unsigned->canonical(), $this->proofKey($issuer))]);
    }

    private function reservation(RegistrationClaim $claim, array $replace = []): AllocationReservation
    {
        return $this->sign(AllocationReservation::class, array_replace(['purpose' => 'studentity.system-id.reservation.v1', 'schemaVersion' => 1,
            'operationId' => $claim->data['operationId'], 'userId' => $claim->data['userId'], 'intentFingerprint' => $claim->data['intentFingerprint'],
            'systemId' => 'synthetic-student-1', 'state' => 'reserved', 'revision' => '1', 'authorityNamespace' => 'synthetic-issuer', 'issuerGeneration' => 'issuer-1', 'signingKeyId' => 'issuer-key', 'authorityRef' => 'urn:synthetic:reservation:1', 'committedAt' => '2026-11-18T00:00:01.000Z'], $replace), true);
    }

    private function command(?RegistrationClaim $claim = null): ActivationCommand
    {
        $claim ??= new RegistrationClaim($this->claimData());
        $reservation = VerifiedAllocationReservation::verify($claim, $this->reservation($claim), $this->policy(true));

        return ActivationCommand::prepare($claim, $reservation, 'synthetic-registration', 'admission-1', '2026-11-18T00:00:02.000Z');
    }

    private function decision(ActivationCommand $command, array $replace = []): ActivationDecision
    {
        $claim = new RegistrationClaim($command->data['claim']);
        $allocation = new AllocationReservation($command->data['allocation']);

        return $this->sign(ActivationDecision::class, array_replace(['purpose' => 'studentity.registration.activation-decision.v1', 'schemaVersion' => 1,
            'operationId' => $claim->data['operationId'], 'userId' => $claim->data['userId'], 'intentFingerprint' => $claim->data['intentFingerprint'],
            'claimFingerprint' => $claim->fingerprint(), 'sealedPayloadHash' => $claim->data['sealedPayloadHash'], 'noticeBundleFingerprint' => $claim->data['noticeBundleFingerprint'],
            'requestFingerprint' => $command->fingerprint(), 'allocationFingerprint' => $allocation->fingerprint(), 'systemId' => $allocation->data['systemId'],
            'correlationId' => $claim->data['correlationId'], 'decisionId' => '88888888-8888-4888-8888-888888888888',
            'authorityNamespace' => 'synthetic-registration', 'issuerGeneration' => 'admission-1', 'signingKeyId' => 'admission-key', 'authorityRef' => 'urn:synthetic:admission:1', 'checkpointRef' => 'checkpoint-1', 'committedAt' => '2026-11-18T00:00:03.000Z', 'result' => 'allowed'], $replace));
    }

    private function absenceData(): array
    {
        return ['purpose' => 'studentity.registration.activation-absence.v1', 'schemaVersion' => 1, 'operationId' => $this->claimData()['operationId'],
            'authorityNamespace' => 'synthetic-registration', 'issuerGeneration' => 'admission-1', 'signingKeyId' => 'admission-key',
            'nonce' => hash('sha256', 'synthetic-request-nonce'), 'checkedAt' => '2026-11-18T00:00:04.000Z', 'checkpointRef' => 'checkpoint-2', 'historyComplete' => true, 'result' => 'not-committed', 'proof' => 'placeholder'];
    }
}
