<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Pending intent only: never a users/account/session record or verification result. */
final readonly class RegistrationIntent
{
    private array $data;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        $expected = ['id', 'email', 'programId', 'departmentId', 'yearLevel', 'classification', 'termsVersion', 'termsHash', 'noticeVersion', 'noticeHash', 'acknowledged', 'passwordHash'];
        $keys = array_keys($data);
        sort($keys);
        sort($expected);
        if ($keys !== $expected) {
            throw new \InvalidArgumentException('Invalid registration intent shape.');
        }
        $copy = [];
        foreach ($data as $key => $value) {
            if (! is_string($value) && ! is_bool($value) && $value !== null) {
                throw new \InvalidArgumentException('Invalid registration intent scalar.');
            }$copy[$key] = $value;
        }
        foreach (array_diff($expected, ['acknowledged', 'passwordHash']) as $key) {
            if (! is_string($copy[$key]) || $copy[$key] === '' || strlen($copy[$key]) > 256) {
                throw new \InvalidArgumentException('Invalid registration intent field.');
            }
        }
        foreach (['id', 'programId', 'departmentId'] as $key) {
            if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $copy[$key])) {
                throw new \InvalidArgumentException('Invalid intent identifier.');
            }
        }
        foreach (['termsHash', 'noticeHash'] as $key) {
            if (! preg_match('/\A[0-9a-f]{64}\z/D', $copy[$key])) {
                throw new \InvalidArgumentException('Invalid notice content hash.');
            }
        }
        $email = strtolower(trim($copy['email']));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/\A[\x21-\x7e]+\z/D', $email) || ! is_bool($copy['acknowledged']) || ! in_array($copy['classification'], ['Incoming Freshman', 'Continuing/Regular', 'Transferee', 'Returning/Readmission', 'Shiftee', 'Second-Course'], true)) {
            throw new \InvalidArgumentException('Invalid registration selections.');
        }
        $hash = $copy['passwordHash'];
        if ($hash !== null && (! is_string($hash) || strlen($hash) > 256 || ! in_array(password_get_info($hash)['algoName'], ['bcrypt', 'argon2id'], true))) {
            throw new \InvalidArgumentException('Adaptive password hash required.');
        }
        $copy['email'] = $email;
        $this->data = $copy;
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function recipient(): string
    {
        return $this->data['email'];
    }

    public function acknowledged(): bool
    {
        return $this->data['acknowledged'];
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode($this->data));
    }

    public function noticeBundleFingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode(array_intersect_key($this->data, array_flip(['termsVersion', 'termsHash', 'noticeVersion', 'noticeHash']))));
    }

    public function __debugInfo(): array
    {
        return ['intentId' => $this->id(), 'privateFields' => 'redacted'];
    }
}
