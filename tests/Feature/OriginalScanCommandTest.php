<?php

namespace Tests\Feature;

use App\Domain\Documents\FileScanCommand;
use App\Infrastructure\Documents\MongoOriginalScanCommandStore;
use App\Infrastructure\Documents\ScanCommandLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class OriginalScanCommandTest extends TestCase
{
    use FileSecurityFixtures;

    private function db()
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanCommandLabDatabase::NAME);
    }

    private function command(): FileScanCommand
    {
        return new FileScanCommand((string) Str::uuid(), $this->fileReference(), $this->filePolicy(), '2026-11-19T00:00:00.000Z');
    }

    private function store()
    {
        ScanCommandLabDatabase::install($this->db());

        return new MongoOriginalScanCommandStore($this->db(), fn () => true);
    }

    public function test_original_scan_identity_body_policy_and_time_survive_restart(): void
    {
        $s = $this->store();
        $c = $this->command();
        $s->prepare($c);
        $s->prepare($c);
        self::assertSame($c->wire(), $this->store()->lookup($c->scanId)->wire());
        self::assertSame(1, $this->db()->scan_commands->countDocuments(['_id' => $c->scanId]));
    }

    public function test_conflicting_same_id_never_overwrites_original(): void
    {
        $s = $this->store();
        $c = $this->command();
        $s->prepare($c);
        foreach ([new FileScanCommand($c->scanId, $this->fileReference(['mediaType' => 'image/png']), $c->policy, $c->issuedAt), new FileScanCommand($c->scanId, $c->object, $this->filePolicy(['signatureVersion' => 'next']), $c->issuedAt), new FileScanCommand($c->scanId, $c->object, $c->policy, '2026-11-19T00:00:01.000Z')] as $bad) {
            $this->fileRejects(fn () => $s->prepare($bad));
        }self::assertSame($c->wire(), $s->lookup($c->scanId)->wire());
    }

    public function test_missing_is_unknown_and_default_gate_denies_all_io(): void
    {
        $s = $this->store();
        self::assertNull($s->lookup((string) Str::uuid()));
        $c = $this->command();
        $deny = new MongoOriginalScanCommandStore($this->db());
        $this->fileRejects(fn () => $deny->prepare($c));
        $this->fileRejects(fn () => $deny->lookup($c->scanId));
        self::assertSame(0, $this->db()->scan_commands->countDocuments(['_id' => $c->scanId]));
    }

    public function test_corrupt_original_evidence_fences_without_repair(): void
    {
        $s = $this->store();
        $c = $this->command();
        $s->prepare($c);
        $this->db()->scan_commands->updateOne(['_id' => $c->scanId], ['$set' => ['fingerprint' => str_repeat('0', 64)]]);
        $this->fileRejects(fn () => $s->lookup($c->scanId));
        $this->fileRejects(fn () => $s->prepare($c));
        self::assertSame(str_repeat('0', 64), $this->db()->scan_commands->findOne(['_id' => $c->scanId])['fingerprint']);
    }

    public function test_closed_wire_and_invalid_id_are_rejected(): void
    {
        $c = $this->command();
        $d = json_decode($c->wire(), true);
        $d['secret'] = 'forbidden';
        $this->fileRejects(fn () => FileScanCommand::fromWire(json_encode($d)));
        self::assertSame($c->wire(), FileScanCommand::fromWire($c->wire())->wire());
        $this->fileRejects(fn () => new FileScanCommand('bad', $c->object, $c->policy, $c->issuedAt));
    }

    public function test_accepted_database_cannot_be_installed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScanCommandLabDatabase::install((new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_dev_m0'));
    }

    public function test_validator_rejects_payload_fields(): void
    {
        $this->store();
        $this->expectException(BulkWriteException::class);
        $this->db()->scan_commands->insertOne(['_id' => (string) Str::uuid(), 'wire' => '{}', 'fingerprint' => str_repeat('a',64), 'bytes' => 'forbidden']);
    }
}
