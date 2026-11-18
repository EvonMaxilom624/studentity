<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptSchema;

/** Immutable sensitive intent envelope; never expose it as an API response. */
final readonly class RegistrationPayload
{
    public RegistrationIntent $intent;

    private string $canonical;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        F::keys($data, ['purpose', 'schemaVersion', 'intent', 'terms_notice_id', 'privacy_notice_id', 'acknowledged_at']);
        if ($data['purpose'] !== 'studentity.registration.intent-payload.v1' || $data['schemaVersion'] !== 1) {
            throw new \InvalidArgumentException('Invalid registration payload version.');
        }
        $this->intent = new RegistrationIntent(F::object($data['intent']));
        foreach (['terms_notice_id', 'privacy_notice_id'] as $f) {
            F::uuid($data[$f]);
        }
        ReceiptSchema::time($data['acknowledged_at']);
        if (! $this->intent->acknowledged() || $data['terms_notice_id'] === $data['privacy_notice_id']) {
            throw new \InvalidArgumentException('Distinct acknowledged notice versions required.');
        }
        $this->canonical = CanonicalAuthorityJson::encode($data);
        if (strlen($this->canonical) > 16384) {
            throw new \InvalidArgumentException('Registration payload too large.');
        }
    }

    public static function fromJson(#[\SensitiveParameter] string $wire): self
    {
        return new self((array) CanonicalAuthorityJson::decodeObject($wire));
    }

    public function wire(): string
    {
        return $this->canonical;
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonical);
    }

    public function fields(): array
    {
        return json_decode($this->canonical, true, 32, JSON_THROW_ON_ERROR);
    }

    public function __debugInfo(): array
    {
        return ['payload' => 'redacted'];
    }
}
