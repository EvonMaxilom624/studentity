<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Security\Authority\AuthorityProofPolicy;

/** Fixed explicit protocol observations, synthetic keys only; no live clock/provider claim. */
trait HandlerProtocolFixtures
{
    private function job(): ReferenceJobCommand
    {
        return new ReferenceJobCommand(['purpose' => 'studentity.job.command.v1', 'schemaVersion' => 1, 'operationId' => '11111111-1111-4111-8111-111111111111', 'correlationId' => '22222222-2222-4222-8222-222222222222', 'enqueueAuditId' => '33333333-3333-4333-8333-333333333333', 'finishAuditId' => '44444444-4444-4444-8444-444444444444', 'requestedBy' => '55555555-5555-4555-8555-555555555555', 'jobType' => 'synthetic.reference-check.v1', 'items' => [['targetId' => '66666666-6666-4666-8666-666666666666', 'resultId' => '77777777-7777-4777-8777-777777777777', 'auditId' => '88888888-8888-4888-8888-888888888888']]]);
    }

    private function itemCommand(): JobItemCommand
    {
        return JobItemCommand::forItem($this->job(), $this->job()->data['items'][0], 'synthetic-handler', 'handler-1');
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-19T'.$time.'Z');
    }

    private function policy(?\Closure $verify = null): AuthorityProofPolicy
    {
        return new AuthorityProofPolicy('synthetic-handler', 'handler-1', ['receipt-key', 'lookup-key'], $verify ?? fn ($body, $proof, $key) => hash_equals(hash_hmac('sha256', $body, 'synthetic-handler-proof:'.$key), $proof), $this->at('00:10:00.000'));
    }

    private function sign(string $class, array $data): object
    {
        unset($data['proof']);
        $raw = new $class($data + ['proof' => 'placeholder']);

        return new $class($data + ['proof' => hash_hmac('sha256', $raw->canonical(), 'synthetic-handler-proof:'.$data['signingKeyId'])]);
    }

    private function receipt(array $replace = []): JobItemReceipt
    {
        $c = $this->itemCommand();
        $d = $c->data;
        unset($d['purpose'],$d['expectedIssuerGeneration']);

        return $this->sign(JobItemReceipt::class, array_replace($d + ['purpose' => 'studentity.job.item-receipt.v1', 'requestFingerprint' => $c->fingerprint(), 'result' => 'succeeded', 'errorCode' => 'none', 'receiptId' => '99999999-9999-4999-8999-999999999999', 'issuerGeneration' => 'handler-1', 'signingKeyId' => 'receipt-key', 'authorityRef' => 'urn:synthetic:item-receipt:1', 'checkpointRef' => 'checkpoint:receipt:1', 'committedAt' => '2026-11-19T00:00:01.000Z'], $replace));
    }

    private function protocolQuery(?string $nonce = null): JobItemLookupQuery
    {
        return JobItemLookupQuery::forCommand($this->itemCommand(), $nonce ?? str_repeat('a', 64), '2026-11-19T00:01:00.000Z');
    }

    private function lookup(string $status = 'committed', array $replace = []): JobItemLookupResponse
    {
        if ($status === 'unknown') {
            return new JobItemLookupResponse(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => 'unknown']);
        }
        $c = $this->itemCommand();
        $q = $this->protocolQuery();

        return $this->sign(JobItemLookupResponse::class, array_replace(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => $status, 'jobOperationId' => $c->data['jobOperationId'], 'itemOperationId' => $c->data['itemOperationId'], 'requestFingerprint' => $c->fingerprint(), 'queryFingerprint' => $q->fingerprint(), 'nonce' => $q->data['nonce'], 'checkedAt' => '2026-11-19T00:01:01.000Z', 'historyComplete' => true, 'authorityNamespace' => 'synthetic-handler', 'issuerGeneration' => 'handler-1', 'signingKeyId' => 'lookup-key', 'authorityRef' => 'urn:synthetic:item-lookup:1', 'checkpointRef' => 'checkpoint:lookup:2', 'receipt' => $status === 'committed' ? $this->receipt()->data : null], $replace));
    }
}
