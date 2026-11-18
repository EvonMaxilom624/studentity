<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Governance\FeatureGatePolicy;
use App\Domain\Governance\GateApproval;
use App\Domain\Identifiers\SystemIdIssuer;
use App\Infrastructure\Governance\MongoFeatureGateRepository;
use App\Infrastructure\Governance\UnavailableApprovalAuthority;
use App\Infrastructure\Identity\UnavailableSystemIdIssuer;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Tests\TestCase;

final class FeatureApprovalGateTest extends TestCase
{
    private array $ids = [];

    private const NOW = 1794960000000;

    protected function tearDown(): void
    {
        try {
            if ($this->ids) {
                $this->db()->selectCollection('institutional_approvals')->deleteMany(['_id' => ['$in' => $this->ids]]);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_missing_default_unknown_and_synthetic_real_data_approvals_deny(): void
    {
        $repo = new MongoFeatureGateRepository($this->db());
        $version = (string) Str::uuid();
        self::assertFalse((new FeatureGatePolicy($repo, new UnavailableApprovalAuthority))->decide('registration', $version, self::NOW)->allowed);
        $this->insert($version);
        self::assertFalse((new FeatureGatePolicy($repo, new UnavailableApprovalAuthority))->decide('registration', $version, self::NOW)->allowed);
        $anchor = $this->anchor($repo->forGate('G-05', $version)[0]);
        self::assertFalse((new FeatureGatePolicy($repo, $anchor))->decide('registration', $version, self::NOW)->allowed);
        self::assertTrue((new FeatureGatePolicy($repo, $anchor, false))->decide('registration', $version, self::NOW)->allowed);
        self::assertFalse((new FeatureGatePolicy($repo, $anchor, false))->decide('unmapped-feature', $version, self::NOW)->allowed);
    }

    public function test_pending_future_conflicting_and_restored_untrusted_records_deny(): void
    {
        $version = (string) Str::uuid();
        $id = $this->insert($version);
        $repo = new MongoFeatureGateRepository($this->db());
        $anchor = $this->anchor($repo->forGate('G-05', $version)[0]);
        $policy = new FeatureGatePolicy($repo, $anchor, false);
        self::assertTrue($policy->decide('registration', $version, self::NOW)->allowed);
        foreach (['pending', 'superseded', 'withdrawn', 'rejected'] as $status) {
            $this->db()->selectCollection('institutional_approvals')->updateOne(['_id' => $id], ['$set' => ['status' => $status]]);
            self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
        }
        $this->db()->selectCollection('institutional_approvals')->updateOne(['_id' => $id], ['$set' => ['status' => 'approved', 'effective_at' => new UTCDateTime(self::NOW + 1)]]);
        self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
        $this->db()->selectCollection('institutional_approvals')->updateOne(['_id' => $id], ['$set' => ['effective_at' => new UTCDateTime(self::NOW - 1000)]]);
        $anchor->current = false; // External currentness is not restored with MongoDB.
        self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
        $anchor->current = true;
        $this->insert($version);
        self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
        self::assertFalse($policy->decide('registration', 'wrong-version', self::NOW)->allowed);
    }

    public function test_currentness_is_rechecked_and_provider_errors_are_sanitized(): void
    {
        $version = (string) Str::uuid();
        $this->insert($version);
        $repo = new MongoFeatureGateRepository($this->db());
        $anchor = $this->anchor($repo->forGate('G-05', $version)[0]);
        $policy = new FeatureGatePolicy($repo, $anchor, false);
        self::assertTrue($policy->decide('registration', $version, self::NOW)->allowed);
        $anchor->offline = true;
        $decision = $policy->decide('registration', $version, self::NOW);
        self::assertFalse($decision->allowed);
        self::assertSame('approval_unavailable', $decision->reason);
    }

    public function test_application_resolves_unavailable_defaults_without_activating_synthetic_backends(): void
    {
        self::assertInstanceOf(UnavailableApprovalAuthority::class, app(CurrentApprovalAuthority::class));
        self::assertFalse(app(FeatureGatePolicy::class)->decide('registration', 'unapproved', self::NOW)->allowed);
        self::assertInstanceOf(UnavailableSystemIdIssuer::class, app(SystemIdIssuer::class));
    }

    public function test_forward_schema_and_changed_provenance_cannot_reuse_previous_confirmation(): void
    {
        $version = (string) Str::uuid();
        $id = $this->insert($version);
        $repo = new MongoFeatureGateRepository($this->db());
        $anchor = $this->anchor($repo->forGate('G-05', $version)[0]);
        $policy = new FeatureGatePolicy($repo, $anchor, false);
        self::assertTrue($policy->decide('registration', $version, self::NOW)->allowed);
        $this->db()->selectCollection('institutional_approvals')->updateOne(['_id' => $id], ['$set' => ['schema_version' => 2]]);
        self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
        $this->db()->selectCollection('institutional_approvals')->updateOne(['_id' => $id], ['$set' => ['schema_version' => 1, 'created_at' => new UTCDateTime(self::NOW - 1999)]]);
        self::assertFalse($policy->decide('registration', $version, self::NOW)->allowed);
    }

    private function anchor(GateApproval $record): CurrentApprovalAuthority
    {
        return new class($record->fingerprint()) implements CurrentApprovalAuthority
        {
            public bool $current = true;

            public bool $offline = false;

            public function __construct(private string $fingerprint) {}

            public function isSynthetic(): bool
            {
                return true;
            }

            public function confirms(GateApproval $approval, string $feature, int $now): bool
            {
                if ($this->offline) {
                    throw new \RuntimeException('synthetic-provider-secret');
                }

                return $this->current && $feature === 'registration' && hash_equals($this->fingerprint, $approval->fingerprint());
            }
        };
    }

    private function db(): Database
    {
        $db = app('db')->connection('mongodb')->getDatabase();
        if ($db->getDatabaseName() !== 'studentity_test_m0' || ! in_array(parse_url(config('database.connections.mongodb.dsn'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic database required.');
        }

        return $db;
    }

    private function insert(string $version): string
    {
        $id = $this->ids[] = (string) Str::uuid();
        $this->db()->selectCollection('institutional_approvals')->insertOne(['_id' => $id, 'gate_code' => 'G-05', 'approved_version' => $version, 'authority_label' => 'Synthetic fixture, not institutional approval', 'status' => 'approved', 'decision_ref' => 'synthetic:decision', 'evidence_uri' => 'urn:synthetic:evidence', 'effective_at' => new UTCDateTime(self::NOW - 1000), 'approved_at' => new UTCDateTime(self::NOW - 1000), 'blocks_feature' => 'registration', 'created_at' => new UTCDateTime(self::NOW - 2000), 'schema_version' => 1]);

        return $id;
    }
}
