# FND-06 Local Synthetic File Security & Clearance Foundation

**Status:** Local synthetic development foundation only. Active verification pass complete.
**Scope:** Explicitly bounded local synthetic file staging, inspection, and preview pipeline for developer workflows. Does not grant production/M0 signoff or replace institutional scanner/storage authority.

---

## 1. Context and Objective

Per the canonical handoff (`docs/handoffs/handout_codex.md`), Tasks 2, 3, and 4 established the derivative OpenAPI identity contract, closed DTO adapters, and diagnostic gate traceability. Task 5 addresses **File Foundations (FND-06)** under the local synthetic end-to-end development scope:
- Providing an integrated local synthetic scanner implementation without requiring host-level external dependencies (`clamscan`/Docker/OpenSSL).
- Providing an end-to-end document clearance workflow coordinating quarantine staging, security scanning, and preview generation.
- Enforcing fail-closed behavior, original file immutability, and separate preview identity.

---

## 2. Architecture & Components

### 2.1 LocalSyntheticFileSecurityScanner
- **Location:** `app/Infrastructure/Documents/LocalSyntheticFileSecurityScanner.php`
- **Interfaces:** `FileSecurityScanner`, `AttemptCorrelatedFileSecurityScanner`
- **Capabilities:**
  - **Magic-Byte Inspection:** Verifies leading file signatures (`\xFF\xD8\xFF` for JPEG, `\x89PNG\r\n\x1a\n` for PNG, `%PDF-` for PDF). Mismatches between detected and declared media types yield `status: unsafe` and `typeValid: false`.
  - **Malware Detection:** Detects standard EICAR test string (`X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*`) and synthetic test marker (`STUDENTITY_SYNTHETIC_MALWARE`), marking results `unsafe` and `malwareClean: false`.
  - **Active Content Safety:** Detects dangerous PDF actions (`/JavaScript`, `/Launch`, `/EmbeddedFiles`, `/RichMedia`) and embedded executable script tags (`<?php`, `<script`, `#!/bin/`), marking results `unsafe` and `activeContentSafe: false`.
  - **Attempt Nonce Correlation:** Implements `inspectAttempt(...)` to bind scanner output nonces directly to the caller's fresh observation attempt, preventing replayed or cross-attempt results.
  - **Fault Injection:** Configurable `forceUnavailable` flag to simulate scanner outages and verify fail-closed downstream handling.

### 2.2 SyntheticDocumentWorkflow
- **Location:** `app/Domain/Documents/SyntheticDocumentWorkflow.php`
- **Role:** Coordinates the local synthetic lifecycle from ingestion to preview.
- **Workflow Sequence:**
  1. Validates inputs against strict limits (maximum 5 MB per SRS FR-DOC-005, accepted media types `image/jpeg`, `image/png`, `application/pdf`).
  2. Generates server-owned `objectId` UUID and SHA-256 digest; creates immutable `QuarantineObjectReference`.
  3. Stages original bytes into `PrivateQuarantineStore` (e.g. `LocalQuarantineLabStore`).
  4. Runs security inspection through `QuarantineInspectionService` (verifying policy fingerprint, scanner availability, timing bounds, and byte integrity).
  5. If the scan is `unsafe` or fails, fail-closed: throws `FileSecurityUnavailable` and halts pipeline; the object remains quarantined.
  6. If `clean`, delegates to `DocumentPreviewService` to generate an optimized preview with a distinct `previewObjectId`, ensuring original bytes are never mutated.
  7. Returns an immutable `SyntheticDocumentClearanceResult`.

### 2.3 SyntheticDocumentClearanceResult
- **Location:** `app/Domain/Documents/SyntheticDocumentClearanceResult.php`
- **Role:** Immutable clearance DTO binding the `QuarantineObjectReference`, `FileScanResult`, optional `DocumentPreviewReference`, and ISO-8601 UTC timestamp with canonical SHA-256 fingerprinting.

---

## 3. Verification

The foundation is covered by dedicated unit and integration suites:
- `tests/Unit/LocalSyntheticFileSecurityScannerTest.php` (12 tests covering JPEG/PNG/PDF validation, type mismatch, EICAR/synthetic malware detection, active content in PDF/images, corrupted bytes, policy mismatch, attempt nonce binding, and forced outage).
- `tests/Feature/SyntheticDocumentWorkflowTest.php` (5 tests covering end-to-end staging/scanning/preview, distinct preview object ID verification, original byte preservation, malware fail-closed rejection, unsupported media rejection, oversize bounds, and scanner outage).

All 17 tests (44 assertions) pass cleanly.

---

## 4. Boundaries and Remaining Gates

1. **Local Synthetic Only:** This foundation is explicitly designed for local development and test automation. It does not replace real institutional anti-malware services (`clamdscan` / enterprise AV) or production cloud storage.
2. **Operational Release Gate:** Production deployment requires formal institutional review of file size/type policies, quarantine ACLs, scanner definition update infrastructure, and signed M0 acceptance.
