<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Security\Authority\AuthorityChangePage;
use App\Domain\Security\Authority\AuthorityCheckpoint;
use App\Domain\Security\Authority\AuthorityStreamCursor;
use App\Domain\Security\Authority\AuthorityStreamSchema;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AuthorityStreamFixtures;

final class AuthorityStreamContractTest extends TestCase
{
    use AuthorityStreamFixtures;

    public function test_checkpoint_cannot_skip_history_and_pages_recover_original_commands(): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        self::assertFalse($cursor->isFullyVerified());
        self::assertSame('0', $cursor->position());
        $first = $this->page([$entries[0]], ['toRef' => 'page-1']);
        $second = $this->page([$entries[1]], ['fromRef' => 'page-1', 'fromPosition' => '1', 'fromHash' => $entries[0]['entryHash'], 'nonce' => $this->nonce('second')]);
        $cursor = $cursor->advance($first, $this->nonce('page'));
        self::assertFalse($cursor->isFullyVerified());
        self::assertSame($entries[0]['commandWire'], $cursor->changes()[0]['command']->canonical());
        $cursor = $cursor->advance(AuthorityChangePage::fromJson($second->wire()), $this->nonce('second'));
        self::assertTrue($cursor->isFullyVerified());
        self::assertSame('2', $cursor->position());
        self::assertSame('anchored', $cursor->changes()[0]['receipt']->journal()['external_anchor_status']);
        // Losing the local cursor starts at genesis again, even with the same signed barrier.
        self::assertSame('0', AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'))->position());
    }

    #[DataProvider('pageAttacks')]
    public function test_validly_signed_inconsistent_pages_are_rejected(string $attack): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $replace = [];
        switch ($attack) {
            case 'gap': $entries = [$entries[1]];
                break;
            case 'hash': $entries[0]['previousHash'] = str_repeat('a', 64);
                break;
            case 'entry-hash': $entries[0]['entryHash'] = str_repeat('b', 64);
                break;
            case 'command':
                $command = RevocationCommand::fromJson($entries[0]['commandWire']);
                $entries[0]['commandWire'] = (new RevocationCommand(array_replace($command->data, ['securityEpoch' => '5'])))->canonical();
                break;
            case 'duplicate': $entries[1] = $entries[0];
                $entries[1]['position'] = '2';
                $entries[1]['previousHash'] = $entries[0]['entryHash'];
                break;
            case 'empty': $entries = [];
                break;
            case 'issuer': $replace['issuerGeneration'] = 'generation-0';
                break;
            case 'namespace': $replace['authorityNamespace'] = 'other';
                break;
            case 'key': $replace['signingKeyId'] = 'untrusted';
                break;
            case 'anchor': $replace['anchorRef'] = 'other';
                break;
            case 'from-ref': $replace['fromRef'] = 'other';
                break;
            case 'from-position': $replace['fromPosition'] = '1';
                break;
            case 'to-position': $replace['toPosition'] = '1';
                break;
            case 'to-hash': $replace['toHash'] = str_repeat('a', 64);
                break;
            case 'to-ref': $replace['toRef'] = 'wrong-barrier';
                break;
            case 'stale': $replace['issuedAt'] = '2026-11-17T00:00:00.000Z';
                break;
            case 'future': $replace['issuedAt'] = '2026-11-19T00:00:00.000Z';
                break;
            case 'nonce': $replace['nonce'] = $this->nonce('other');
                break;
        }
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($this->page($entries, $replace), $this->nonce('page'));
    }

    public static function pageAttacks(): array
    {
        return array_map(fn ($v) => [$v], ['gap', 'hash', 'entry-hash', 'command', 'duplicate', 'empty', 'issuer', 'namespace', 'key', 'anchor', 'from-ref', 'from-position', 'to-position', 'to-hash', 'to-ref', 'stale', 'future', 'nonce']);
    }

    public function test_replayed_page_is_rejected_after_progress(): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $page = $this->page($entries);
        $cursor = $cursor->advance($page, $this->nonce('page'));
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($page, $this->nonce('page'));
    }

    public function test_checkpoint_refresh_retains_verified_prefix_and_requires_new_entries(): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint([$entries[0]], ['checkpointRef' => 'barrier-1']), $this->policy(), $this->nonce('checkpoint'));
        $cursor = $cursor->advance($this->page([$entries[0]], ['anchorRef' => 'barrier-1', 'toRef' => 'barrier-1']), $this->nonce('page'));
        self::assertTrue($cursor->isFullyVerified());
        $cursor = $cursor->refresh($this->checkpoint($entries, ['nonce' => $this->nonce('refresh')]), $this->nonce('refresh'));
        self::assertFalse($cursor->isFullyVerified());
        self::assertSame('1', $cursor->position());
        $cursor = $cursor->advance($this->page([$entries[1]], ['fromRef' => 'barrier-1', 'fromPosition' => '1', 'fromHash' => $entries[0]['entryHash'], 'nonce' => $this->nonce('new-page')]), $this->nonce('new-page'));
        self::assertTrue($cursor->isFullyVerified());
    }

    #[DataProvider('checkpointAttacks')]
    public function test_rollback_and_truncated_history_checkpoints_are_rejected(string $attack): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $replace = ['nonce' => $this->nonce('refresh')];
        switch ($attack) {
            case 'rollback': $replace['position'] = '1';
                break;
            case 'fork': $replace['chainHash'] = str_repeat('a', 64);
                break;
            case 'ref': $replace['checkpointRef'] = 'other';
                break;
            case 'generation': $replace['issuerGeneration'] = 'generation-0';
                break;
            case 'horizon': $replace['minimumPosition'] = '1';
                break;
        }
        $this->expectException(ReceiptContractViolation::class);
        $cursor->refresh($this->checkpoint($entries, $replace), $this->nonce('refresh'));
    }

    public static function checkpointAttacks(): array
    {
        return [['rollback'], ['fork'], ['ref'], ['generation'], ['horizon']];
    }

    public function test_proof_failure_and_callback_exception_are_fail_closed(): void
    {
        foreach ([fn () => false, fn () => 1, fn () => throw new \RuntimeException('secret')] as $verify) {
            try {
                AuthorityStreamCursor::fromCheckpoint($this->checkpoint([]), $this->policy($verify), $this->nonce('checkpoint'));
                self::fail('Unverified checkpoint accepted.');
            } catch (ReceiptContractViolation $e) {
                self::assertStringNotContainsString('secret', $e->getMessage());
            }
        }
    }

    public function test_nested_references_cannot_mutate_validated_wire_objects(): void
    {
        $entries = $this->streamEntries();
        $data = $this->page($entries)->data;
        $wire = $entries[0]['commandWire'];
        $data['entries'][0]['commandWire'] = &$wire;
        $page = new AuthorityChangePage($data);
        $original = $page->wire();
        $wire = 'forged';
        self::assertSame($original, $page->wire());
        self::assertSame($original, AuthorityChangePage::fromJson($original)->wire());
        $checkpoint = $this->checkpoint($entries);
        self::assertSame($checkpoint->wire(), AuthorityCheckpoint::fromJson($checkpoint->wire())->wire());
    }

    #[DataProvider('badShapes')]
    public function test_stream_schema_rejects_unknown_fields_wrong_types_and_overflow(string $attack): void
    {
        $data = $this->checkpoint([])->data;
        switch ($attack) {
            case 'extra': $data['endpoint'] = 'https://invalid.test';
                break;
            case 'int': $data['position'] = 0;
                break;
            case 'overflow': $data['position'] = '9223372036854775808';
                break;
            case 'zero-prefix': $data['position'] = '00';
                break;
            case 'time': $data['issuedAt'] = "2026-11-18T00:00:00.500Z\0";
                break;
            case 'nested': $data['nonce'] = [$data];
                break;
        }
        $this->expectException(ReceiptContractViolation::class);
        new AuthorityCheckpoint($data);
    }

    public static function badShapes(): array
    {
        return [['extra'], ['int'], ['overflow'], ['zero-prefix'], ['time'], ['nested']];
    }

    public function test_duplicate_json_members_are_rejected(): void
    {
        $wire = $this->checkpoint([])->wire();
        $this->expectException(ReceiptContractViolation::class);
        AuthorityCheckpoint::fromJson('{"schemaVersion":1,'.substr($wire, 1));
    }

    public function test_refresh_cannot_discard_an_unverified_previous_barrier(): void
    {
        $entries = $this->streamEntries();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint([$entries[0]]), $this->policy(), $this->nonce('checkpoint'));
        $this->expectException(ReceiptContractViolation::class);
        $cursor->refresh($this->checkpoint($entries, ['nonce' => $this->nonce('refresh')]), $this->nonce('refresh'));
    }

    public function test_even_validly_signed_later_page_cannot_repeat_an_original_operation(): void
    {
        $entries = $this->streamEntries();
        $entries[1]['commandWire'] = $entries[0]['commandWire'];
        $entries[1]['receiptWire'] = $entries[0]['receiptWire'];
        $command = RevocationCommand::fromJson($entries[1]['commandWire']);
        $receipt = RevocationReceipt::fromJson($entries[1]['receiptWire']);
        $entries[1]['entryHash'] = hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.authority.change-entry.v1', 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'position' => '2', 'previousHash' => $entries[0]['entryHash'], 'operationId' => $command->data['operationId'], 'requestFingerprint' => $command->fingerprint(), 'receiptFingerprint' => $receipt->fingerprint()]));
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $cursor = $cursor->advance($this->page([$entries[0]], ['toRef' => 'page-1']), $this->nonce('page'));
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($this->page([$entries[1]], ['fromRef' => 'page-1', 'fromPosition' => '1', 'fromHash' => $entries[0]['entryHash'], 'nonce' => $this->nonce('second')]), $this->nonce('second'));
    }

    public function test_empty_tail_requires_verified_barrier_and_a_new_challenge(): void
    {
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint([]), $this->policy(), $this->nonce('checkpoint'));
        self::assertTrue($cursor->isFullyVerified());
        $cursor = $cursor->advance($this->page([], ['anchorRef' => 'barrier-0', 'fromRef' => 'barrier-0', 'toRef' => 'barrier-0']), $this->nonce('page'));
        self::assertTrue($cursor->isFullyVerified());
        self::assertSame([], $cursor->changes());
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($this->page([], ['anchorRef' => 'barrier-0', 'fromRef' => 'barrier-0', 'toRef' => 'barrier-0']), $this->nonce('page'));
    }

    public function test_stream_position_increment_is_exact_above_json_integer_range(): void
    {
        self::assertSame('9007199254740992', AuthorityStreamSchema::next('9007199254740991'));
        self::assertSame('9223372036854775807', AuthorityStreamSchema::next('9223372036854775806'));
        $this->expectException(ReceiptContractViolation::class);
        AuthorityStreamSchema::next('9223372036854775807');
    }

    public function test_forged_receipt_is_rejected_even_inside_a_valid_page_signature(): void
    {
        $entries = $this->streamEntries();
        $receipt = RevocationReceipt::fromJson($entries[0]['receiptWire']);
        $entries[0]['receiptWire'] = (new RevocationReceipt(array_replace($receipt->data, ['proof' => 'forged'])))->wire();
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($this->page($entries), $this->nonce('page'));
    }

    public function test_page_proof_cannot_be_forged(): void
    {
        $entries = $this->streamEntries();
        $page = new AuthorityChangePage(array_replace($this->page($entries)->data, ['proof' => 'forged']));
        $cursor = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'));
        $this->expectException(ReceiptContractViolation::class);
        $cursor->advance($page, $this->nonce('page'));
    }

    public function test_entries_are_bounded_and_reject_recursive_or_extra_members_before_encoding(): void
    {
        $entry = $this->streamEntries(1)[0];
        $data = $this->page([$entry])->data;
        foreach (['count', 'recursive', 'extra'] as $attack) {
            $candidate = $data;
            if ($attack === 'count') {
                $candidate['entries'] = array_fill(0, 9, $entry);
            } elseif ($attack === 'recursive') {
                $candidate['entries'][0]['commandWire'] = &$candidate;
            } else {
                $candidate['entries'][0]['endpoint'] = 'untrusted';
            }
            try {
                new AuthorityChangePage($candidate);
                self::fail('Unsafe entry shape accepted.');
            } catch (ReceiptContractViolation) {
                self::assertTrue(true);
            }
            unset($candidate);
        }
    }
}
