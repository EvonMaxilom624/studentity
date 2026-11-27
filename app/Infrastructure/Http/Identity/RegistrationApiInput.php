<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Identity;

use App\Domain\Registration\RegistrationIntent;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

/** Unbound boundary DTO. Server lookup snapshots validate selections; they do not grant admission. */
final readonly class RegistrationApiInput
{
    private const FIELDS = ['email', 'termsVersion', 'noticeVersion', 'acknowledged', 'programId', 'departmentId', 'yearLevel', 'classification'];

    private const CLASSIFICATIONS = ['Incoming Freshman', 'Continuing/Regular', 'Transferee', 'Returning/Readmission', 'Shiftee', 'Second-Course'];

    private function __construct(#[\SensitiveParameter] private array $data) {}

    /** Lookup maps and year values MUST come from server-resolved accepted metadata, never the request. */
    public static function fromArray(#[\SensitiveParameter] array $input, array $programDepartments, array $yearLevels): self
    {
        if (array_diff(array_keys($input), [...self::FIELDS, 'password']) || array_diff(self::FIELDS, array_keys($input))) {
            self::reject();
        }
        $copy = [];
        foreach ($input as $field => $value) {
            if ($field === 'yearLevel') {
                if (! is_int($value) || $value < -2147483648 || $value > 2147483647 || ! in_array($value, $yearLevels, true)) {
                    self::reject();
                }
            } elseif ($field === 'acknowledged') {
                if ($value !== true) {
                    self::reject();
                }
            } elseif (! is_string($value) || $value === '' || strlen($value) > 256 || ! mb_check_encoding($value, 'UTF-8')) {
                self::reject();
            }
            $copy[$field] = $value; // Dereference caller-owned scalar slots before retaining them.
        }
        foreach (['programId', 'departmentId'] as $field) {
            if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $copy[$field])) {
                self::reject();
            }
        }
        foreach (['termsVersion', 'noticeVersion'] as $field) {
            if (! preg_match('/\A[\x21-\x7e]{1,256}\z/D', $copy[$field])) {
                self::reject();
            }
        }
        if (! filter_var($copy['email'], FILTER_VALIDATE_EMAIL) || ! preg_match('/\A[\x21-\x7e]+\z/D', $copy['email']) || ! in_array($copy['classification'], self::CLASSIFICATIONS, true)
            || ($programDepartments[$copy['programId']] ?? null) !== $copy['departmentId']) {
            self::reject();
        }
        if (isset($copy['password']) && (mb_strlen($copy['password'], 'UTF-8') < 10 || strlen($copy['password']) > 72 || str_contains($copy['password'], "\0"))) {
            self::reject();
        }
        $copy['email'] = strtolower($copy['email']);

        return new self($copy);
    }

    private static function reject(): never
    {
        throw ValidationException::withMessages(['registration' => 'Invalid registration input.']);
    }

    /** No persistence or verification result; the year remains a BSON-compatible integer. */
    public function academicSelections(): array
    {
        return ['program_id' => $this->data['programId'], 'department_id' => $this->data['departmentId'], 'year_level' => $this->data['yearLevel'], 'classification' => $this->data['classification']];
    }

    /** Explicit v1 legacy adapter: no existing signed intent or stored payload is rewritten. */
    public function toLegacyIntent(string $intentId, string $termsHash, string $noticeHash, Hasher $hasher): RegistrationIntent
    {
        return new RegistrationIntent([
            'id' => $intentId, 'email' => $this->data['email'], 'programId' => $this->data['programId'], 'departmentId' => $this->data['departmentId'],
            'yearLevel' => (string) $this->data['yearLevel'], 'classification' => $this->data['classification'],
            'termsVersion' => $this->data['termsVersion'], 'termsHash' => $termsHash, 'noticeVersion' => $this->data['noticeVersion'], 'noticeHash' => $noticeHash,
            'acknowledged' => true, 'passwordHash' => isset($this->data['password']) ? $hasher->make($this->data['password']) : null,
        ]);
    }

    public function __debugInfo(): array
    {
        return ['registrationInput' => 'redacted'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Sensitive boundary input must not be serialized.');
    }
}
