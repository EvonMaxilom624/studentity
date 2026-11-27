<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Registration\RegistrationIntent;
use App\Infrastructure\Http\Identity\RegistrationApiInput;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RegistrationApiInputTest extends TestCase
{
    private const PROGRAM = '11111111-1111-4111-8111-111111111111';

    private const DEPARTMENT = '22222222-2222-4222-8222-222222222222';

    private function input(): array
    {
        return ['email' => 'SYNTHETIC@example.invalid', 'password' => 'synthetic-password', 'termsVersion' => 'terms-v1', 'noticeVersion' => 'privacy-v1', 'acknowledged' => true, 'programId' => self::PROGRAM, 'departmentId' => self::DEPARTMENT, 'yearLevel' => 3, 'classification' => 'Continuing/Regular'];
    }

    public function test_strict_api_input_converts_explicitly_without_changing_legacy_intent(): void
    {
        $input = $this->input();
        $dto = RegistrationApiInput::fromArray($input, [self::PROGRAM => self::DEPARTMENT], [1, 3]);
        $hash = (new BcryptHasher(['rounds' => 4]))->make($input['password']);
        $hasher = $this->createMock(Hasher::class);
        $hasher->expects(self::once())->method('make')->with('synthetic-password')->willReturn($hash);
        $id = '33333333-3333-4333-8333-333333333333';
        $expected = new RegistrationIntent(['id' => $id, 'email' => 'synthetic@example.invalid', 'programId' => self::PROGRAM, 'departmentId' => self::DEPARTMENT, 'yearLevel' => '3', 'classification' => 'Continuing/Regular', 'termsVersion' => 'terms-v1', 'termsHash' => str_repeat('a', 64), 'noticeVersion' => 'privacy-v1', 'noticeHash' => str_repeat('b', 64), 'acknowledged' => true, 'passwordHash' => $hash]);
        self::assertSame($expected->fingerprint(), $dto->toLegacyIntent($id, str_repeat('a', 64), str_repeat('b', 64), $hasher)->fingerprint());
        self::assertSame(3, $dto->academicSelections()['year_level']);
        self::assertSame(self::DEPARTMENT, $dto->academicSelections()['department_id']);
        self::assertStringNotContainsString('synthetic-password', print_r($dto, true));
        self::assertStringNotContainsString('synthetic-password', json_encode($dto, JSON_THROW_ON_ERROR));
    }

    public static function invalid(): array
    {
        return [
            'coerced year' => ['yearLevel', '3'], 'float year' => ['yearLevel', 3.0],
            'unaccepted year' => ['yearLevel', 2], 'wrong department' => ['departmentId', '44444444-4444-4444-8444-444444444444'],
            'unknown program' => ['programId', '44444444-4444-4444-8444-444444444444'], 'malformed uuid' => ['programId', '../program'],
            'coerced acknowledgment' => ['acknowledged', 'true'], 'not acknowledged' => ['acknowledged', false],
            'unknown classification' => ['classification', 'synthetic-unapproved'], 'blank version' => ['termsVersion', ''],
            'version control byte' => ['noticeVersion', "privacy\nsecret"], 'invalid email' => ['email', 'not-email'],
            'nested field' => ['email', ['synthetic-secret']], 'short password' => ['password', 'short'],
            'oversize password' => ['password', str_repeat('s', 73)], 'null password' => ['password', null],
            'unknown field' => ['systemId', 'forged'], 'hash injection' => ['passwordHash', 'forged'],
            'embedded null' => ['password', "synthetic\0password"], 'nested year' => ['yearLevel', ['3']], 'numeric password' => ['password', 1234567890],
            'unicode byte limit' => ['password', str_repeat('界', 25)],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_or_client_injected_fields_fail_closed(string $field, mixed $value): void
    {
        $input = $this->input();
        $input[$field] = $value;
        $this->expectException(ValidationException::class);
        RegistrationApiInput::fromArray($input, [self::PROGRAM => self::DEPARTMENT], [1, 3]);
    }

    public function test_missing_academic_field_is_not_silently_defaulted(): void
    {
        $input = $this->input();
        unset($input['departmentId']);
        $this->expectException(ValidationException::class);
        RegistrationApiInput::fromArray($input, [self::PROGRAM => self::DEPARTMENT], [3]);
    }

    public function test_optional_password_is_absent_and_never_hashed(): void
    {
        $input = $this->input();
        unset($input['password']);
        $dto = RegistrationApiInput::fromArray($input, [self::PROGRAM => self::DEPARTMENT], [3]);
        $hasher = $this->createMock(Hasher::class);
        $hasher->expects(self::never())->method('make');
        self::assertInstanceOf(RegistrationIntent::class, $dto->toLegacyIntent('33333333-3333-4333-8333-333333333333', str_repeat('a', 64), str_repeat('b', 64), $hasher));
    }

    public function test_client_references_cannot_mutate_validated_snapshot(): void
    {
        $input = $this->input();
        $input['yearLevel'] = &$year;
        $year = 3;
        $dto = RegistrationApiInput::fromArray($input, [self::PROGRAM => self::DEPARTMENT], [3]);
        $year = 999;
        self::assertSame(3, $dto->academicSelections()['year_level']);
        $this->expectException(\LogicException::class);
        serialize($dto);
    }
}
