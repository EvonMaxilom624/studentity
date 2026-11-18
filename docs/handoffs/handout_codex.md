# Studentity — current Codex handoff

Updated 2026-11-18. Work directly in this checkout on **master**, baseline ca22296.
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
- Preserve the entire untracked app/Domain/Identity/ and modified
  tests/Feature/M0MongoLiveIntegrationTest.php: no edits/reset/stash/staging/commit
  without explicit permission. New domain code uses unprotected sibling folders.
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

Last full suite: **358 tests / 3,219 assertions**, no failures/skips. Evidence:
storage/app/private/registration-activation-contracts-2026-11-18/full-tests-final.log.
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
No durable issuer, official ID format, persisted registration intent, delivery/global
throttle, public route, session issuance or challenge-restore protection exists yet.

Evidence: storage/app/private/governance-identity-registration-2026-11-18/ contains
full-tests-final.log (301 / 3,137, 62.932s), 20 scoped tests/109 assertions, Pint,
manifest, provider snapshot and archive proof. All important review findings were
fixed with red/green and restored-source mutation checks; minor follow-ups remain
below. The extra testing DB has allocations_v1/registration_challenges_v1, strict
closed validators and no TTL; never repoint these helpers into accepted application DBs.

## IAM-01 proposal and completed contract slice

Review-only artifact: [schema/protocol proposal](../../database/mongodb/proposals/iam01_registration_v1.json).
Four strict/error/no-TTL collections are proposed: registration_intents_v1,
registration_challenges_v1, registration_activations_v1, system_id_allocations_v1.
They are not loaded by migration/boot. Accepted auth_challenges stays unchanged.
Read-only $documents/$match proof: 47 cases (13 accepted/34 rejected), inventory
unchanged; shape proof does not establish concurrency or cryptographic trust.
Design evidence: storage/app/private/iam01-registration-design-2026-11-18/.

Sequence: encrypted immutable intent -> atomic OTP consume + original durable claim
and stable child UUIDs -> independently irrevocable reservation + persisted exact
command -> operation-bound current admission -> atomic account apply/deny. Never
perform network calls inside Mongo transactions or create accounts after the boolean
OTP verifier alone. Original intent UUID identifies operation and proposed user;
user creation happens only at final apply. Abandoned reservations stay nonreusable.
Local projection retains actual verified issuer state/revision/receipt; applied is
local activation completion and never fabricates issued. No session/role/assignment/
consent/certificate is implied. Unknown state/recovery remains fenced; lost ACKs retry
only the original command, with valid possession proof/approved resume capability.

Completed coding slice: closed RegistrationClaim, ActivationCommand/Decision and
AllocationReservation contracts, privately verified decision/reservation/noncommit
capabilities, typed lookup and unavailable activation authority. Pure bounded canonical
v1 wire; no schema, user creation, durable job, delivery or public route was enabled.
Server-owned proof policies pin namespace/generation/key IDs and observation bounds;
proof callbacks must return strict true. Noncommit needs a challenged complete-ledger
assertion; unknown/offline never proves absence. Reservation state remains the actual
issuer state, not a fabricated issued state after local application.

Rulings: historical same-original proof may reverify for repair; cross-body/operation/
issuer replay denies. UTC causal/deadline checks have no implicit skew allowance;
operator clock/trust policy remains a production gate. No receipt/lookup status grants
current authorization or confirms a local account commit. Verified retirement can be
history but cannot prepare a new activation command.

Review: both Important findings fixed with observed red/green regressions. Lookup
status/payload are private; resolveFor requires the current operation, fresh server
nonce and observation bounds even for cached unions. Command factory AND wire parser
enforce claim verification <= reservation commit <= preparation < original deadline.

Results: 57 scoped tests / 82 assertions; full suite **358 / 3,219**, no failures or
skips, 61.125s under explicit PHP84. Pint passes for 18 PHP files. Proposal wire
metadata updated; collection validators/indexes unchanged and unapplied. Both accepted
DBs remain 62 collections/128 indexes, six routes unchanged; all 13 protected/reference
checks and archived bytes match. Vite not rerun for PHP-only work. Dependencies unchanged.
No staging or commit. Evidence: storage/app/private/registration-activation-contracts-2026-11-18/
(full-tests-final.log, review-resolution.md, file-manifest.json, final-checks.json).
The 179-source baseline/provider/proposal/handoff snapshots support selective rollback:
restore only the three listed prior files and remove only listed new files after diff
review, preserving all unrelated/protected work and the 2,532-line archive.

Next: after explicit approval of the exact additive schema/isolated target, implement
durable claim/store transactions, then original-ID/decision/final-apply and repair.
Follow with FND-04 encrypted delivery/DTO/global throttle/readiness acceptance before
separately reviewed public routes. Provider, ID format, deadlines, custody, notices
and institutional approvals remain required inputs; never invent them.

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

## Daily commands

Use explicit PHP84 or set $env:Path='C:\tools\php84;'+$env:Path in that terminal.
php artisan serve --host=127.0.0.1 --port=8000; npm run dev in a second terminal.
MongoDB must be running; Start-Service may need an elevated terminal. Never replace
an existing running service or perform data cleanup to start development.
Tests: & 'C:\tools\php84\php.exe' vendor/bin/phpunit.
Schema inspection (read-only): & 'C:\tools\php84\php.exe'
storage/app/private/laravel13-upgrade-2026-11-17/schema-inspection.php.
