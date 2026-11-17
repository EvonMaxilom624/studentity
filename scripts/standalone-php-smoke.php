<?php

declare(strict_types=1);

require __DIR__ . '/../app/Domain/Certificates/CertificateYearSlotKey.php';
require __DIR__ . '/../app/Domain/Certificates/ClearanceClaimGate.php';
require __DIR__ . '/../app/Domain/Security/AuthorizationRecoveryGate.php';
require __DIR__ . '/../app/Domain/Documents/QuarantineReleaseGate.php';

use App\Domain\Certificates\CertificateYearSlotKey;
use App\Domain\Certificates\ClearanceClaimGate;
use App\Domain\Security\AuthorizationRecoveryGate;
use App\Domain\Documents\QuarantineReleaseGate;

function check(bool $pass, string $label): void {
    if (!$pass) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}
$slot = CertificateYearSlotKey::fromIds('123e4567-e89b-42d3-a456-426614174000','123e4567-e89b-42d3-a456-426614174001');
check(str_contains($slot, ':'), 'stable student-year key');
check(ClearanceClaimGate::mayClaimValid(true,true,true,true,true,true,true,true), 'all clearance gates valid');
check(!ClearanceClaimGate::mayClaimValid(true,true,true,true,true,true,false,true), 'no stale clearance');
check(!AuthorizationRecoveryGate::canResume(true,false,true,true,true), 'revoked session recovery denied');
check(AuthorizationRecoveryGate::canResume(true,true,true,true,true), 'only fully verified recovery permitted');
check(!QuarantineReleaseGate::canEnterReview('quarantined','pending',false,false,true), 'scanner outage quarantined');
check(QuarantineReleaseGate::canEnterReview('cleared','cleared',true,true,true), 'clean scanned object passes scan gate');
