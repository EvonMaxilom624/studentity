# Studentity M0 Candidate Acceptance Verification Packet v1

**Publication Date:** 24 November 2026  
**Evidence Review:** 27 November 2026  
**Status:** Strict Local Verification Candidate (`profile=strict-ci-candidate`, `deployable=false`).  
**Scope:** Formal audit packet documenting the current M0 foundation baseline, architectural boundaries, verified test evidence, catalogue integrity, and remaining institutional operational gates.  
**Canonical Handoff Reference:** [Development handoff](../handoffs/handout_codex.md)

---

## 1. Executive Summary & Verification Candidate Baseline

Studentity is a university student-data system built with Laravel 13, MongoDB, and React. Under the approved architecture (ADR-01, ADR-02, SRS v1.1), the system implements an authority-first, fail-closed security foundation across 7 core foundation areas (FND-01 through FND-07).

### Verification measurements for source review

- **Reconciled 27 November baseline.** The strict PHPUnit stage was GREEN with **790 tests and 5,033 assertions** and covered the certificate-slot concurrency work. The default Vite loader hit the Windows sandbox access error. The separate native-loader build passed, and all 323 selected source inputs matched. Evidence is under `storage/app/private/local-verification/2ee5136825f141976b6e043c41b69865/`.
- **Local PHPUnit.** On 27 November, `& 'C:/tools/php84/php.exe' scripts/verify-local.php --ci-candidate` passed the strict test stage with **801 tests and 5,226 assertions**. The verifier exited 1 at the default Vite config loader after a Windows sandbox directory-access error.
- **Local frontend build.** The separate `npm.cmd run build -- --configLoader native` command built 35 modules in 786 ms with Vite 7.3.8. The 324 selected source inputs matched before tests and after this build. The source review recaptured the same digest with `& 'C:/tools/php84/php.exe' artisan studentity:artifact-manifest --json`. The manifest reports `deployable=false`.
- **Local evidence.** See `storage/app/private/local-verification/3badccfcbe742eca627f4e2f5e7fd5b6/` for `tests.log`, the default `build.log`, `build-native.log`, and the pre-test and post-build source manifests.
- **Hosted evidence.** The 23 November run 45095083400 passed **731 tests and 4,636 assertions** and both Vite builds against commit `578e1fd`. Each fresh job catalogue matched 62 collections, 128 catalogue-defined indexes plus 62 automatic `_id` indexes, and a separate `migrations` ledger. The current local changes await hosted verification. See the [hosted run record in the handoff](../handoffs/handout_codex.md#fnd-01-hosted-measurement-23november2026).
- **Focused identity and session evidence.** The later M0-05 run passed **45 tests and 787 assertions**. The test-only composition deferred adding a missing password-integrity marker on a rejected first authenticated request and preserved native password-change rejection. See the [first-request verification in the handoff](../handoffs/handout_codex.md#m0-05-first-authenticated-request-marker-verification).

---

## 2. Foundation Milestone Audit Matrix (FND-01 – FND-07)

| Milestone | Subsystem | Implementation Status | Operational Gate Status |
|---|---|---|---|
| **FND-01** | Verification Runner & CI Candidate | Strict candidate runner (`scripts/verify-local.php --ci-candidate`), `SourceArtifactManifest`, GitHub Actions manual candidate (`.github/workflows/ci-strict-candidate.yml`). | **Candidate Ready / Unsigned.** Requires institutional CI runner approval, artifact signing authority, and SBOM review before hosted release. |
| **FND-02** | Schema & Migration Governance | 62-collection catalogue JSON, immutable migration ledger, non-destructive collMod semantics, read-only integrity checker. | **Fresh hosted catalogue verified.** The recorded run used isolated job databases. Deployment migration acceptance remains open. |
| **FND-03** | Identity & Authority Contracts | `AuthorityContractFields`, canonical JSON serialization, `RegistrationIntent`, `SelfUserProfile`, closed DTOs (`RegistrationApiInput`). | **Local Synthetic Complete.** Real permanent ID issuer, trust roots, key custody, and independent recovery authority pending. |
| **FND-04** | Outbox & Encrypted Delivery | Database-clock token leases, atomic outbox transactions, producer fault isolation, transient retry hooks, isolated protocol contracts. | **Engineering Foundation Complete.** External mail transport, real encrypted envelope custody, and peer outage recovery pending. |
| **FND-05** | Governance & Feature Gate Policy | `FeatureGatePolicy` distinguishing synthetic vs. real data modes, SRS G-04 privacy/terms traceability, `OperationalReadinessInspector`. | **Blocked by Design (`activationAllowed=false`).** Real data admission denied until named institutional approvers accept notices/terms. |
| **FND-06** | File Security & Quarantine | `LocalQuarantineLabStore`, `LocalSyntheticFileSecurityScanner` (magic bytes, EICAR/synthetic malware, active content), `DocumentPreviewService` (distinct preview ID, untouched original bytes), `SyntheticDocumentWorkflow`. | **Local Synthetic Complete.** Enterprise anti-malware service (`clamdscan`), production cloud storage ACLs, and signed delivery proxy pending. |
| **FND-07** | API Scope & Problem Boundary | RFC 7807 safe Problem mapping (`FoundationProblemResponse`), correlation UUIDs, outer response boundary (`FoundationProblemBoundary`), Identity derivative contract (`identity-v1.openapi.json`). | **Engineering Derivative Complete.** Source OpenAPI remains immutable; runtime controller/handler routes unregistered. |

### Original M0 milestone traceability

The milestone IDs below are from the [original M0 plan](../../../Studentity_M0_Laravel_MongoDB_Approved_v1.0/M0_NEXT_DEVELOPMENT_PLAN.md). Institutional M0 acceptance remains open.

| Milestone | Verified engineering evidence | Remaining acceptance work |
|---|---|---|
| M0-01 Core runtime | Laravel 13/PHP 8.4 boot, root and health smoke coverage, and a local Vite build. | Retest the selected runtime on the exact hosted candidate. |
| M0-02 Mongo schema | Fresh isolated hosted catalogues verified 62 collections and 190 physical indexes per catalogue database. | Verify both catalogues again on the reviewed candidate and document the approved deployment migration process. |
| M0-03 Transactions | Live replica-set commit/rollback, slot uniqueness, and outbox atomicity coverage. | Retain the transaction checks for the reviewed candidate and verify the approved deployment topology. |
| M0-04 Identity | Synthetic registration and issuance contracts, bounded identity API DTOs, and guard rejection tests. | Accept the issuer, System ID format, notices, and live authority. |
| M0-05 Session and recovery | Synthetic Sanctum/CSRF, logout, rollback, and recovery tests. The focused first-request run passed 45 tests and 787 assertions. | Accept the runtime missing-marker policy and prove approved encrypted sessions, MFA, revocation, and independent restore across instances. |
| M0-06 File and worker | Synthetic quarantine and scanner, outbox evidence, and [journal race tests](../../tests/Feature/ScanJournalRaceTest.php) covering stale-nonce rejection and a MongoDB write conflict between completions targeting the same pending head. The same-generation test verifies a single completion and outbox record. | Operational storage, scanner, delivery, and restore need approved services and evidence. |
| M0-07 Public clearance | Synthetic certificate slot and two-worker generation conflict tests plus fail-closed clearance rules. | Verify freshness with approved live authorities and resolve signing/custody before public activation. |
| M0-08 CI and review | Local verification evidence and hosted run 45095083400 on `578e1fd`. | Review the exact candidate, obtain a fresh hosted pass, prepare and sign artifact evidence, and record institutional signoff. |

---

## 3. Operational Readiness Blocker Analysis

The operational readiness diagnostic (`php artisan studentity:readiness --json`) intentionally exits with code 1, reporting `status: "blocked"` and `activationAllowed: false`. The 8 recorded blockers and their institutional requirements are:

1. **`security-recovery` (FND-03, FR-ACT-020/021, AUD-13):**
   - *Requirement:* Named independent security authority, peer trust, current credential/revocation evidence, and durable recovery cursor. Old restored state cannot revive privilege.
2. **`permanent-issuer` (FND-03, ADR-02, FR-ID-011):**
   - *Requirement:* Institutional approval of the permanent System ID format and issuer ownership, plus durable same-operation allocation and lookup and a nonrecycling registry.
3. **`proof-key-custody` (FND-03, AUD-13):**
   - *Requirement:* Approved authority namespaces, server-owned trust roots, key custody, and key revocation/rotation process.
4. **`trusted-clock` (FND-03, FND-05, FR-ACT-021):**
   - *Requirement:* Operational clock ownership/synchronization, accepted skew/deadline policy, and rollback detection across instances.
5. **`approved-notices` (FND-05, SRS G-04):**
   - *Requirement:* Named institutional approvers and exact approved notice/terms content, hashes, versions, and separate consent legal basis under SRS G-04.
6. **`session-transport` (FND-03, ADR-01, FR-ACT-020/021):**
   - *Requirement:* Accepted same-origin cookie/CSRF/guard and private encrypted durable session transport with no bearer/remember-me fallback.
7. **`durable-delivery` (FND-04, FR-NOT-003–006, NFR-OPS-001):**
   - *Requirement:* Approved sender, encrypted private envelope custody, template versions, purpose/recipient binding, and confidential message protection.
8. **`deployment-acceptance` (FND-05, ADR-01, NFR-OPS-002/003):**
   - *Requirement:* Institutional privacy/security approvals, approved hosting/region/budget, encrypted off-site backups, and measured RPO/RTO $\le$ 24 hours.

---

## 4. Immutable References at Preparation

- `composer.lock` SHA-256: `bb2ff2ff59233b50561a3e7c3457b127b50d1fa80313d9a06d09d8237848d503`
- `package-lock.json` SHA-256: `7185acc8b626c7be92b46cebfd5173e8d63abe446fbd2d8969a0fa87b5862f53`
- `database/mongodb/collection_catalogue.json` SHA-256: `2fb572b70b6ed1ef2f4bcb94f07e3a79d7b76706a13cc02e02408c79afc32709`
- `resources/contracts/identity-v1.openapi.json` SHA-256: `13fbdf985617d3f83b89345282bfc6e7aba5e22340a24282c257ee6059aed890` (canonical Git LF checkout).
- Protected File `app/Domain/Identity/UserKind.php` SHA-256: `A79AE473FEE5482E5DFFB0EEF81E3F4BD4C350EA85F2B7AB0D99884CC065BED1`
- Protected File `tests/Feature/M0MongoLiveIntegrationTest.php` SHA-256: `5D54B0911AB0CCE2BD8D5EE6F4CE980A9BE7539BFE531DE5A2A0EC2281BC19A0`

---

## 5. Candidate Acceptance Verification Sequence

### Proposed source boundary

The local review starts at `d4b3086b686e3fbb3402da004574ac96b02da7b4`. The proposed candidate contains the manual CI checkout pin update, fail-closed governance and readiness changes, unbound identity contracts, the synthetic React preview, certificate and document foundations, and their tests and documentation. See `storage/app/private/m0-08-source-review-0e566e87b02241e684f069cd2a496eed/candidate-inventory.json` for exact changed paths, SHA-256 hashes, Git status, and manifest coverage.

The owner must review the listed source and documentation scope, including the new checkout pin and the default local preview, and authorize the candidate commit, push, and manual hosted run. The runtime route snapshot shows the existing framework storage and CSRF routes, root, and health endpoints. Identity business route activation remains gated.

Release decisions remain open for the immutable build environment, artifact contents, SBOM, signing and provenance authority, and evidence retention. The readiness inspector still reports eight blockers. Reviewers need the institutional choices and proofs listed in section 3 before written acceptance.

To transition from local candidate to institutional M0 acceptance:
1. **Source Freeze:** Verify working tree clean on `master` with zero unreviewed diffs.
2. **Hosted Execution:** Run `.github/workflows/ci-strict-candidate.yml` in an isolated GitHub Actions runner; ensure both disposable `studentity_test_m0` and `studentity_dev_m0` namespaces pass fresh schema bootstrap and full strict verification.
3. **Artifact Signing:** Mint and attach cryptographic provenance and signature from an independently authorized signing authority.
4. **Institutional Review:** Present this packet and the 8 readiness blocker resolutions to named academic and security reviewers.
5. **No Invented Approvals:** Until formal signoff is granted, all public business routes and real data admission remain disabled.
