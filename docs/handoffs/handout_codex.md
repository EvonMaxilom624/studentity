# Studentity — current Codex handoff

Updated 2026-11-18. Work directly in this checkout on **master**, checkpoints 4139b48 / 525d986 (owner-requested commits).
This is the only maintained handoff; keep it short and replace completed execution
detail with results/evidence links. Do not create competing roadmaps or branches.

Historical record: [archived handoff](handout_codex_archive_2026-11-18_pre_governance_identity.md).
The owner authorized its rename/archive; its original bytes/hash are preserved.
Read the archive only for a specific historical detail, not on every continuation.
Activation decision and original P0–P5 design are in its activation-readiness section.

## Mandatory safeguards

- Read AGENTS.md. Synthetic data only; no destructive MongoDB operations, production
  activation, cloud provisioning, real credentials or invented institutional approvals.
- Preserve SRS v1.1, Blueprint and accepted architecture documents. Consult both
  ../Documentations/ and ../Studentity_M0_Laravel_MongoDB_Approved_v1.0/ when referencing
  requirements; final SRS and approved ADR-01/02/invariants govern implementation.
- Preserve the committed app/Domain/Identity/ and protected
  tests/Feature/M0MongoLiveIntegrationTest.php: no edits/reset/stash/staging/commit
  without explicit permission. Owner authorized committing their exact existing bytes on
  2026-11-18; no content edits are authorized. New code uses sibling folders.
- Protected SHA256: M0 test =
  5D54B0911AB0CCE2BD8D5EE6F4CE980A9BE7539BFE531DE5A2A0EC2281BC19A0;
  Identity/UserKind.php =
  A79AE473FEE5482E5DFFB0EEF81E3F4BD4C350EA85F2B7AB0D99884CC065BED1.
- Preserve unrelated dirty/untracked work. No automatic commits, extra checkouts or
  worktrees. Do not modify accepted 62-collection metadata or migrate/drop accepted
  DBs to implement synthetic experiments. Never use user_sessions/audit/journal as
  payload stores or add blanket TTL. Restore must not revive revoked privilege.

## Verified baseline

PHP 8.4.27: C:\tools\php84\php.exe (bare PHP can resolve XAMPP 8.2.12).
Laravel 13.42.0, Tinker 3.1.0, Sanctum 4.4.0, PHPUnit 12.5.44; official MongoDB
Laravel 5.12/library 2.5.1/extension 2.5.7; local MongoDB 8.3.7 rs0 PRIMARY.
Node 24.13 / npm 11.6 / Vite 7.3.8. No dependency change is planned.

Last full suite: **408 tests / 3,375 assertions**, no failures/skips. Evidence:
storage/app/private/registration-test-acceptance-2026-11-18/full-tests.log.
Current runtime: unavailable security authority, file/JSON sessions, encryption=false,
Eloquent AuthenticatedUser identity lookup, sync queue. No real login/registration or
business API is exposed. Native encrypted/fenced HTTP/process/receipt/recovery proofs
exist under Tests; no durable external authority or production transport is selected.

Accepted studentity_dev_m0/studentity_test_m0: 62 strict application validators +
migration ledger, 128 catalogue indexes each. Existing extra lab DBs are synthetic.
Immutable checks: storage/app/private/session-lock-lab-2026-11-17/reference-hashes.json;
protected/config/dependency checks: storage/app/private/session-guard-lab-2026-11-17/before-hashes.json.

## Completed prerequisite foundation

P0/P1/P2 are implemented and tested, not full runtime/production acceptance. Provider
binds unavailable approval/ID issuers and read-only approval bookkeeping; security
trust remains unavailable. P0 maps registration G-05/invitations G-09, fingerprints
approved v1 provenance and rejects unknown representation; blocks_feature is STRING.
P1 is authority-first; local allocation projections never authorize issuance/reuse.
P2 binds immutable intent, exact notice versions/hashes, recipient/purpose and keyed
OTP; atomic expiry/attempt/consume proofs use fixed local testing-only backends.
No real durable issuer, official ID format, production intent store/delivery/global
throttle, public route, session issuance or independent challenge-restore protection exists.

Evidence: storage/app/private/governance-identity-registration-2026-11-18/ contains
full-tests-final.log (301 / 3,137, 62.932s), 20 scoped tests/109 assertions, Pint,
manifest, provider snapshot and archive proof. All important review findings were
fixed with red/green and restored-source mutation checks; minor follow-ups remain
below. The extra testing DB has allocations_v1/registration_challenges_v1, strict
closed validators and no TTL; never repoint these helpers into accepted application DBs.

## IAM-01 isolated durable registration foundation

Spec: [schema/protocol proposal](../../database/mongodb/proposals/iam01_registration_v1.json).
Four strict/error/no-TTL collections: registration_intents_v1, registration_challenges_v1,
registration_activations_v1, system_id_allocations_v1. Accepted catalogue/migrations are
unchanged. Original read-only schema proof: 47 cases (13 accepted/34 rejected), under
storage/app/private/iam01-registration-design-2026-11-18/.
Contract step completed previously: closed claim/reservation/command/decision, configured
proof verification, query-bound lookup and unavailable activation authority. Evidence:
storage/app/private/registration-activation-contracts-2026-11-18/ (358/3,219 baseline).

Owner requested checkpoint commit 4139b48 and the next two steps on 2026-11-18.
- [x] Task 2: encrypted immutable intent/start/resend; snapshot/primary/majority claim
  commits wrong attempts and atomically consumes only correct current OTP with original
  operation/child UUIDs. Separate recipient/OTP key IDs and domains, pinned limits/expiry,
  current local gate and DB-clock ordering. Consumed OTP alone cannot resume processing.
- [x] Task 3: persist original encrypted command before I/O; verify operation-bound
  decision/current allocation; atomic provisional user/verified email/optional credential/
  exact notice acknowledgments/redacted audit/journal + activation/intent transitions.
  Rejection creates no account. Exact original-body repair rejects partial/changed
  accounts, credentials, receipt metadata or backward issuer state/revision/time.

These are isolated transaction proofs only, not full IAM-01/runtime acceptance.
RegistrationLabDatabase v1 explicitly installs only in local testing target
studentity_test_registration_activation_v1: four proposed collections plus seven
unchanged catalogue clones. Constructor/assertReady never installs or repairs metadata.
No production migration, public route, delivery, session, role or verified academic
assignment was added. Academic selections remain in the immutable encrypted intent.
Real approval/issuer/activation authorities stay unavailable; no lab provider is bound.
Server-owned gate callbacks are non-network fixtures and strict true is required.
They do not establish independent production recovery/currentness.

Original intent UUID = allocation/activation operation = proposed user; no account
exists until final apply. No network call spans a transaction. New decision submission
needs the original deadline and configured destination; historical committed outcomes
use guarded repair. Noncommit is reauthenticated under current configured policy and
bound to current nonce/time; unknown/offline does not prove absence. Original command
and child UUIDs never regenerate on retry. Keep actual verified issuer state/revision/
receipt; local applied never invents issued. IDs remain irrevocably nonreusable.

Review: eight Important findings fixed with observed RED/GREEN regressions. Evidence:
storage/app/private/registration-durable-2026-11-18/ (ledger.md, review-red.log,
review-policy-red.log, review-green.log, full-tests-final.log, manifest.json).
Scoped acceptance: 48 tests / 129 assertions; final full results are recorded above.
Full suite: 406 / 3,348, no failures/skips (96.782s); Pint 12 new PHP files passed.
Both accepted DBs remain 62 collections/128 indexes; six routes unchanged. All 13
immutable checks match; archive/protected bytes match checkpoint. Vite passed before
this PHP-only slice. Source and dependency versions unchanged except this slice.
Rollback fences processing and retains claims/ciphertext/commands/receipts/burned-ID
proof; selectively revert this slice's files after diff review. No drops/down cleanup.

Rulings: match accepted migration's deduplication of equivalent notice-ack index names;
one interrupted empty lab install was explicitly completed with its missing index,
not repaired automatically. Normalize driver native int64 snapshots to BSON Int64
for schema-validated epoch/revision comparison. These decisions affect isolated proof
portability/repair comparisons; they do not change accepted schemas or production trust.
Full rulings/costs and per-file evidence are in ledger.md/completion-report.md.

Completed next-two acceptance follow-ups after resume:
- [x] Worker-ready/release/entry barrier; both workers are live and no attempts change
  before release. This proves coordinated starts, not measured Mongo transaction overlap.
- [x] Clock-relative acknowledgment fixture; injected immutable clock, UTC offset/date
  rollover, millisecond precision and default bounds tested. One-minute offset is a
  test fixture choice, not institutional policy.
Observed RED/GREEN; 50 scoped tests/156 assertions, full **408/3,375**, no failures/skips
(72.260s), Pint4 PHP files passed. Fresh review: no Important findings; one deferred
assertion below. All208 baseline files preserved; only three tests/helper files and this
handoff changed, one unit-test file added. All13 immutable checks, archive, accepted
schemas and six routes match. No app/dependency/schema change; Vite's checkpoint build
remains applicable. Evidence: storage/app/private/registration-test-acceptance-2026-11-18/
(ledger.md, full-tests.log, manifest.json, completion-report.md).

Next bounded plans: (1) FND-04 audit/outbox/idempotent-command design against accepted
background_jobs/job_results, closed reference-only redacted DTOs and exact additive lab
schema proposal; (2) after design review, isolated worker leases/retry/per-item outcome
proofs. Raw OTP/password/email payloads need a separately reviewed encrypted delivery
handoff. Keep independent issuer/admission/current-recovery and key/clock design open;
provider ownership, approved notices/global limits and real deployment acceptance are
required before runtime registration/public routes. No institutional decision is implied.

## Carried-forward open work / activation gates

1. Complete/runtime-accept P0/P1/P2 after the bounded foundation above; then follow
   IAM-01 -> IAM-02 -> IAM-03 -> IAM-04. IAM-01 is email OTP registration with exact
   notice acknowledgments (FND-03/FND-05/G-05), not a renamed System ID story.
2. Real independently authenticated current authority: ownership, peer trust,
   key/issuer lifecycle, prompt revocation, durable idempotent ACK/lookup, original
   command persistence, durable applied cursor and approved baseline/pruning policy.
   Current active revoke port is void; runtime receipt/journal integration is missing.
3. Shared encrypted transport production design/binding/additive schema and actual
   two-HTTP-instance/outage/restore/key-rotation acceptance. Mongo candidate is only
   a recommendation; another DB on the same restored Mongo server is not independence.
4. Permanent ID format/issuer and independently trustworthy nonrecycling after restore;
   additive production issuance/registration-intent/MFA replay schemas need exact diff,
   negative proof and repair/rollback review. No ID may be recycled after merge/retirement.
5. FND-04 outbox/worker/idempotency and safe encrypted OTP delivery; current queue is
   sync. Hash-only challenge records; never raw OTP/password in audit or job payloads.
6. Credentials in credentials, verified-only aliases, password reset/session revocation,
   native rotation, privileged password+TOTP or policy-approved additional email MFA,
   coordinator invitations. IAM-07 lost-email and IAM-08 two-custodian recovery remain
   separate stories. Remember-me/bearer shortcuts stay disabled.
7. Exact approved notice/terms historical text/effective versions and distinct legal
   consent; institutional gates, mail/hosting/region/budget/key owners and actual
   production recovery/operational acceptance must be supplied, never invented.
8. OpenAPI review before endpoints: registration DTO lacks department/year despite
   FR-ID-001; SelfUser requires systemId but staff have none; map CSRF 419/uncertain
   failures. Production login/logout are 200 schema results, not test harness 204s.
9. Wider backlog remains open: FND-01 secure CI/reproducible releases, full FND-04
   workers/outbox, full FND-05 management, FND-06 private storage, FND-07 API/problem
   contract harness and QA traceability/acceptance. No student, upload, document,
   certificate/public-verification features until their relevant gates pass.
10. Deferred minor: empty-genesis recovery test checks a fixture array rather than
    observing DB writes; do not cite it as instrumented no-write proof.
    New deferred test hardening: replace the potentially flaky OTP substring check
    with structural MAC assertions; broaden the intent-ID-only account-write check
    to observe service-level account/session writes. No instrumented global claim.
    Durable-slice minors resolved by the two acceptance follow-ups above. Remaining
    Minor: assert entered markers are absent immediately before parent release; current
    worker publishes them after its release wait. Actual transaction overlap is unmeasured.

## Daily commands

Use explicit PHP84 or set $env:Path='C:\tools\php84;'+$env:Path in that terminal.
php artisan serve --host=127.0.0.1 --port=8000; npm run dev in a second terminal.
MongoDB must be running; Start-Service may need an elevated terminal. Never replace
an existing running service or perform data cleanup to start development.
Tests: & 'C:\tools\php84\php.exe' vendor/bin/phpunit.
Schema inspection (read-only): & 'C:\tools\php84\php.exe'
storage/app/private/laravel13-upgrade-2026-11-17/schema-inspection.php.
