<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Authentication\SelfUserProfile;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class SelfUserProfileTest extends TestCase
{
    public function test_accepted_account_kinds_states_and_opaque_issuer_reference_are_supported(): void
    {
        foreach (['coordinator', 'system_admin'] as $kind) {
            self::assertSame($kind, (new SelfUserProfile((string) Str::uuid(), null, 'provisional', [], $kind))->userKind);
        }
        $profile = new SelfUserProfile((string) Str::uuid(), 'synthetic-issued-AZ7', 'active', ['student'], 'student');
        self::assertSame('synthetic-issued-AZ7', $profile->systemId);
    }

    public function test_profile_states_match_the_accepted_user_catalogue(): void
    {
        $catalogue = json_decode(file_get_contents(dirname(__DIR__, 2).'/database/mongodb/collection_catalogue.json'), true, 512, JSON_THROW_ON_ERROR);
        $users = array_values(array_filter($catalogue['collections'], fn ($c) => $c['name'] === 'users'))[0];
        self::assertEqualsCanonicalizing($users['validator']['$jsonSchema']['properties']['account_state']['enum'], SelfUserProfile::VALID_ACCOUNT_STATES);
    }

    public function test_student_profile_with_verified_system_id(): void
    {
        $id = (string) Str::uuid();
        $profile = new SelfUserProfile($id, '2026-00001', 'active', ['student'], 'student');
        self::assertSame($id, $profile->id);
        self::assertSame('2026-00001', $profile->systemId);
        self::assertSame('active', $profile->accountState);
        self::assertSame(['student'], $profile->effectiveRoles);
        self::assertSame('student', $profile->userKind);
        self::assertSame([
            'id' => $id,
            'systemId' => '2026-00001',
            'accountState' => 'active',
            'effectiveRoles' => ['student'],
        ], $profile->toOpenApi());
    }

    public function test_student_profile_without_system_id_pending_verification(): void
    {
        $id = (string) Str::uuid();
        $profile = new SelfUserProfile($id, null, 'provisional', ['student'], 'student');
        self::assertNull($profile->systemId);
        self::assertSame('provisional', $profile->accountState);
        self::assertNull($profile->toOpenApi()['systemId']);
    }

    public function test_administrator_profile_must_not_possess_student_system_id(): void
    {
        $id = (string) Str::uuid();
        $profile = new SelfUserProfile($id, null, 'active', ['system_admin'], 'system_admin');
        self::assertNull($profile->systemId);
        self::assertSame('system_admin', $profile->userKind);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-student accounts cannot possess a student System ID.');
        new SelfUserProfile($id, '2026-00002', 'active', ['system_admin'], 'system_admin');
    }

    public function test_staff_profile_must_not_possess_student_system_id(): void
    {
        $id = (string) Str::uuid();
        $profile = new SelfUserProfile($id, null, 'active', ['coordinator'], 'coordinator');
        self::assertNull($profile->systemId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-student accounts cannot possess a student System ID.');
        new SelfUserProfile($id, '2026-00003', 'active', ['coordinator'], 'coordinator');
    }

    public function test_invalid_system_id_format_throws(): void
    {
        $id = (string) Str::uuid();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid System ID format.');
        new SelfUserProfile($id, '', 'active', ['student'], 'student');
    }

    public function test_invalid_account_state_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SelfUserProfile((string) Str::uuid(), null, 'unknown_state', ['student'], 'student');
    }
}
