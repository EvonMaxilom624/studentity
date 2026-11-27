# M0-07 Public Clearance & Certificate Year Slot Foundation

**Date:** 24 November 2026  
**Status:** Implemented and Verified in Local Environment.  
**Scope:** Certificate year slot uniqueness, optimistic concurrency control, and ClearanceClaimGate eligibility binding.  
**Canonical Handoff Reference:** `docs/handoffs/handout_codex.md`

---

## 1. Context and Objective

Under the approved master coding plan (`M0_NEXT_DEVELOPMENT_PLAN.md`), milestone **M0-07 Public Clearance Foundation** establishes the physical and transactional foundation for student certificate year slots:
- Enforcing deterministic student-year slot allocation (`_id = studentId:academicYearId`) and unique compound index `uq_one_operative_slot_per_student_year`.
- Allowing at most one binding for a given slot generation via the conditional compare-and-swap specified in [ADR-02](../../../Studentity_M0_Laravel_MongoDB_Approved_v1.0/ADR-02_FINAL_MongoDB_Physical_Model.md#transaction-requirements).
- Enforcing AUD-12 live eligibility gates via `ClearanceClaimGate::mayClaimValid(...)` before any operative certificate binding.
- Ensuring neutral fail-closed behavior when dependencies or eligibility are unsatisfied.

---

## 2. Architecture & Components

### 2.1 CertificateYearSlotManager
- **Location:** `app/Domain/Certificates/CertificateYearSlotManager.php`
- **Role:** Coordinates slot allocation and operative certificate transitions in MongoDB collection `certificate_year_slots`.
- **Operations:**
  - `getOrCreateSlot(string $studentId, string $academicYearId): CertificateYearSlotRecord`: Idempotent slot allocation with deterministic `_id`, initialized to `generation = 0` and un-bound operative certificate.
  - `bindOperativeCertificate(string $studentId, string $academicYearId, string $certificateId, int $expectedGeneration, array $eligibilityWitnesses): CertificateYearSlotRecord`:
    - Evaluates `ClearanceClaimGate::mayClaimValid(...)` across all 8 required eligibility checks (authentic issuance, issued lifecycle, current academic year, official status eligible, verified identity/enrollment, approved requirements, consistent dependencies, and current recovery reconciliation).
    - Throws `CertificateSlotUnavailable` if any eligibility check fails.
    - Atomically updates `operative_certificate_id` and increments `generation` conditioned on `['_id' => $slotId, 'generation' => $expectedGeneration]`.
    - Detects stale generation conflicts and fails closed if concurrent modification occurred.
  - `revokeOperativeCertificate(string $studentId, string $academicYearId, int $expectedGeneration): CertificateYearSlotRecord`:
    - Clears `operative_certificate_id` via `$unset` and increments `generation` atomically.

### 2.2 CertificateYearSlotRecord & Key
- **Locations:** `app/Domain/Certificates/CertificateYearSlotRecord.php`, `CertificateYearSlotKey.php`
- **Role:** Immutable DTO representing slot state with canonical SHA-256 fingerprint; deterministic key generator enforcing lowercase UUID format.

---

## 3. Verification Evidence

- **Feature Test Suite:** [CertificateYearSlotTransactionTest.php](../../tests/Feature/CertificateYearSlotTransactionTest.php) (7 tests, 38 assertions passed in the November 27 focused run):
  - Deterministic slot ID initialization and schema version assertion.
  - Idempotent duplicate slot creation.
  - Operative certificate binding on valid eligibility and generation advance.
  - Fail-closed rejection when any clearance gate check fails.
  - Optimistic concurrency conflict rejection on stale generation.
  - Revocation clearing operative pointer and advancing generation.
  - Two independent workers read the same slot generation, pause just before their updates, and try to bind different certificates. One succeeds and one reports a generation conflict. The stored slot advances exactly once and points to the winner.
- **Strict Verification Candidate:** Verified via `scripts/verify-local.php --ci-candidate`.

---

## 4. Boundaries and Remaining Gates

1. **Local Foundation Only:** This foundation provides transactional slot safety and eligibility gating in MongoDB. It does not activate public QR verification endpoints or issue production credentials.
2. **Operational Gate Requirements:** Public clearance and certificate issuance remain gated on approved permanent System ID formats, institutional signing keys, and operational readiness signoff.
