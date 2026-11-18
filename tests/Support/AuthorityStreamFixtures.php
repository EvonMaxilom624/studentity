<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\AuthorityChangePage;
use App\Domain\Security\Authority\AuthorityCheckpoint;
use App\Domain\Security\Authority\AuthorityStreamPolicy;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;

/** Synthetic HMAC fixture only; does not select a production proof algorithm. */
trait AuthorityStreamFixtures
{
    use AuthorityReceiptFixtures;

    private string $streamKey = 'synthetic-stream-contract-key';

    private function nonce(string $label): string
    {
        return hash('sha256', $label);
    }

    private function policy(?\Closure $proof = null): AuthorityStreamPolicy
    {
        return new AuthorityStreamPolicy('synthetic-authority', 'generation-1', ['lab-key'], $proof ?? fn ($body, $signature, $key) => $key === 'lab-key' && hash_equals(hash_hmac('sha256', $body, $this->streamKey), $signature), new \DateTimeImmutable('2026-11-18T00:00:00.000Z'), new \DateTimeImmutable('2026-11-18T00:00:01.000Z'));
    }

    private function checkpoint(array $entries, array $replace = []): AuthorityCheckpoint
    {
        $last = $entries ? end($entries) : null;
        $data = array_replace(['purpose' => 'studentity.authority.checkpoint.v1', 'schemaVersion' => 1, 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'signingKeyId' => 'lab-key', 'checkpointRef' => 'barrier-'.count($entries), 'position' => $last['position'] ?? '0', 'chainHash' => $last['entryHash'] ?? str_repeat('0', 64), 'minimumPosition' => '0', 'nonce' => $this->nonce('checkpoint'), 'issuedAt' => '2026-11-18T00:00:00.500Z'], $replace);

        return new AuthorityCheckpoint($this->signed($data));
    }

    private function page(array $entries, array $replace = []): AuthorityChangePage
    {
        $last = $entries ? end($entries) : null;
        $data = array_replace(['purpose' => 'studentity.authority.change-page.v1', 'schemaVersion' => 1, 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'signingKeyId' => 'lab-key', 'anchorRef' => 'barrier-2', 'fromRef' => 'genesis', 'fromPosition' => '0', 'fromHash' => str_repeat('0', 64), 'toRef' => 'barrier-2', 'toPosition' => $last['position'] ?? '0', 'toHash' => $last['entryHash'] ?? str_repeat('0', 64), 'nonce' => $this->nonce('page'), 'issuedAt' => '2026-11-18T00:00:00.600Z', 'entries' => $entries], $replace);

        return new AuthorityChangePage($this->signed($data));
    }

    private function signed(array $data): array
    {
        unset($data['proof']);

        return $data + ['proof' => hash_hmac('sha256', CanonicalAuthorityJson::encode($data), $this->streamKey)];
    }

    private function streamEntries(int $count = 2): array
    {
        $authority = new ReceiptAuthorityLab;
        $entries = [];
        $previous = str_repeat('0', 64);
        for ($i = 1; $i <= $count; $i++) {
            $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => sprintf('%08d-1111-4111-8111-111111111111', $i)]));
            $receipt = $authority->revoke($command);
            // Stream fixtures use a separate, explicit verifier key, not the ephemeral memory model key.
            $receipt = new RevocationReceipt($this->signed($receipt->data));
            $hash = hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.authority.change-entry.v1', 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'position' => (string) $i, 'previousHash' => $previous, 'operationId' => $command->data['operationId'], 'requestFingerprint' => $command->fingerprint(), 'receiptFingerprint' => $receipt->fingerprint()]));
            $entries[] = ['position' => (string) $i, 'previousHash' => $previous, 'entryHash' => $hash, 'commandWire' => $command->canonical(), 'receiptWire' => $receipt->wire()];
            $previous = $hash;
        }

        return $entries;
    }
}
