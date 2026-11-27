<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Readiness\OperationalReadinessInspector;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class M0CandidateAcceptancePacketTest extends TestCase
{
    private string $packetContent;

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 2).'/docs/proposals/m0_candidate_acceptance_packet_v1.md';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->packetContent = $content;
    }

    public function test_all_operational_readiness_blockers_are_documented(): void
    {
        $inspector = new OperationalReadinessInspector(new Container, new Repository);
        $report = $inspector->report();

        $this->assertSame('blocked', $report['status']);
        $this->assertFalse($report['activationAllowed']);
        $this->assertCount(8, $report['blockers']);

        foreach ($report['blockers'] as $blocker) {
            $this->assertStringContainsString(
                '`'.$blocker['id'].'`',
                $this->packetContent,
                "Blocker {$blocker['id']} must be explicitly documented in M0 acceptance packet."
            );
        }
    }

    public function test_candidate_profile_and_non_deployable_status_are_asserted(): void
    {
        $this->assertStringContainsString('profile=strict-ci-candidate', $this->packetContent);
        $this->assertStringContainsString('deployable=false', $this->packetContent);
    }

    public function test_protected_file_hashes_match_packet_documentation(): void
    {
        $userKindPath = dirname(__DIR__, 2).'/app/Domain/Identity/UserKind.php';
        $m0LivePath = dirname(__DIR__, 2).'/tests/Feature/M0MongoLiveIntegrationTest.php';

        $userKindHash = strtoupper(hash_file('sha256', $userKindPath));
        $m0LiveHash = strtoupper(hash_file('sha256', $m0LivePath));

        $this->assertStringContainsString($userKindHash, $this->packetContent);
        $this->assertStringContainsString($m0LiveHash, $this->packetContent);
    }

    public function test_immutable_reference_hashes_match_disk(): void
    {
        $root = dirname(__DIR__, 2);
        $references = [
            'composer.lock' => $root.'/composer.lock',
            'package-lock.json' => $root.'/package-lock.json',
            'collection_catalogue.json' => $root.'/database/mongodb/collection_catalogue.json',
            'identity-v1.openapi.json' => $root.'/resources/contracts/identity-v1.openapi.json',
        ];

        foreach ($references as $name => $path) {
            $this->assertFileExists($path);
            $hash = strtolower(hash_file('sha256', $path));
            $this->assertStringContainsString(
                $hash,
                $this->packetContent,
                "Hash for {$name} in acceptance packet must match disk."
            );
        }
    }
}
