# Studentity current Codex handoff

Updated 20 November 2026 (Asia/Shanghai). Work on **master**. Previous checkpoint
f32c2d5; use git log -1 to identify the latest reviewed commit.
This is the only maintained handoff and execution checklist. Keep completed detail in
linked evidence. No competing roadmaps, branches, worktrees or automatic commits.
Owner authorized review and commit on20November2026. Preserve protected bytes and all
reviewed work. After this checkpoint, do not commit again without explicit instruction.

History: [archived handoff](handout_codex_archive_2026-11-18_pre_governance_identity.md).
Owner authorized its archive; preserve its original bytes. Read it only for a specific
historical question. Original P0-P5/activation readiness detail is retained there.

## Mandatory safeguards

- Read AGENTS.md. Synthetic data only; no destructive MongoDB operations, production
  activation, cloud provisioning, real credentials or invented institutional approvals.
- Preserve final SRS v1.1, Blueprint and accepted architecture. Consult both sibling
  ../Documentations/ and ../Studentity_M0_Laravel_MongoDB_Approved_v1.0/ for references.
  Final SRS and accepted ADR-01/02/invariants govern; original backlog's PostgreSQL
  wording is historical, superseded by accepted MongoDB implementation decisions.
- Preserve app/Domain/Identity/ and tests/Feature/M0MongoLiveIntegrationTest.php without
  content edits/reset/stash/staging/commit. Owner approved committing their exact old
  bytes on 18 November only; subsequent content/commit permission is not implied.
- Protected SHA256: M0 test =
  5D54B0911AB0CCE2BD8D5EE6F4CE980A9BE7539BFE531DE5A2A0EC2281BC19A0;
  Identity/UserKind.php =
  A79AE473FEE5482E5DFFB0EEF81E3F4BD4C350EA85F2B7AB0D99884CC065BED1.
- Never change accepted 62-collection metadata or migrate/drop accepted DBs for labs.
  No blanket TTL; sessions/audit/journal are not payload stores. Restore must not revive
  revoked privilege. No business/public routes before their relevant security gates.

## Verified baseline

Explicit PHP: C:\tools\php84\php.exe 8.4.27; bare PHP may resolve XAMPP 8.2.12.
Laravel 13.42.0, Tinker 3.1.0, Sanctum 4.4.0, PHPUnit 12.5.44; MongoDB Laravel 5.12,
library 2.5.1/extension 2.5.7/server 8.3.7 rs0 PRIMARY. Node 24.13/npm 11.6/Vite 7.3.8.
Last strict suite: **710 tests / 4,550 assertions**, no failures/skips/issues,94.985s.
Vite7.3.8:25modules/790ms;291selected source inputs unchanged, deployable=false.
Evidence: storage/app/private/precommit-review-2026-11-20/verified-tests.log.
Accepted studentity_dev_m0 and studentity_test_m0 each retain 62 strict catalogue
collections + migration ledger and 128 indexes. Six routes; no real auth/business API.
Runtime authorities remain unavailable; file/JSON sessions, encryption=false, sync queue,
AuthenticatedUser identity lookup. No production provider or deployment is accepted.
Immutable references: storage/app/private/session-lock-lab-2026-11-17/reference-hashes.json;
protected/config/dependencies: session-guard-lab-2026-11-17/before-hashes.json under same root.

## M0 timeline and current position

| Date | Verified development progress |
|---|---|
| 17 Nov | Runtime/Mongo baseline committed as ca22296; schema and initial transaction proofs. |
| 18 Nov | Laravel 13 alignment; identity/session/recovery and registration labs; outbox/worker and producer composition. |
| 19 Nov | Handler/worker/recovery, operational-readiness diagnostic and private quarantine/scanner lab foundation completed; real operational acceptance remains pending. |

| Original M0 milestone | Current status |
|---|---|
| M0-01 runtime | Locally verified Laravel 13/PHP 8.4/Vite; hosting acceptance pending. |
| M0-02 schema | Local 62-collection validators/indexes verified; accepted metadata preserved. |
| M0-03 transactions | Live commit/abort/uniqueness and producer/outbox atomic proofs verified. |
| M0-04 identity | Deny-default, IDs and registration labs; real issuer/identity acceptance pending. |
| M0-05 sessions/recovery | Cookie/CSRF/fence/recovery labs; independent authority/deployment pending. |
| M0-06 files/workers | Worker/quarantine/journal/state/audit/outbox labs verified; private delivery and real provider acceptance pending. |
| M0-07 clearance | Slot uniqueness/freshness guards tested; public clearance stays inactive. |
| M0-08 CI/review | Local verifier/input hashes/Vite pass; secure CI/signed release/M0 signoff pending. |

**M0 is incomplete.** Lab proof is not operational/production acceptance. Original weekly
windows were candidates, not current promises; no invented completion date/percentage.
Operational FND-03/FND-05 acceptance still requires real authority/issuer/key/clock/
approved-notice ownership and evidence; independent provider/delivery/shared-throttle
acceptance remains pending. studentity:readiness lists these blockers, never accepts them.
Next development order (activation dependencies remain binding):
1. FND-01: choose actual CI host/repository/runner and signing authority, then review
   real workflow permissions/isolation/provenance before activation. No Git remote or
   active CI configured; strict local candidate is complete. Do not add another local
   substitute for host acceptance. Review docs/proposals/fnd01_ci_acceptance_packet_v1.md.
   Secure CI/exact release artifact/institutional M0 signoff remain unaccepted.
2. FND-07 approved API scope/DTO and actual middleware/logging/finalizer/lifecycle review
   before binding. Opt-in handler plus outer response boundary now cover direct errors
   in the tested composition; global application integration remains unapproved.
3. FND-06 real authentication/storage/scanner/ACL/private-proxy/audit acceptance. The
   synthetic bounded delivery proof is complete; real delivery remains unavailable.

## Completed foundations and evidence

Checkpoint f32c2d5 includes earlier outbox/readiness/quarantine/original-command work.
Worker/correlation and later foundation slices are included in20November review
checkpoint; prior evidence describes their then-uncommitted state.
P0 exact G-05/G-09 provenance, P1 authority-first nonrecycling ID projection and P2
purpose/recipient/notice/OTP guards implemented in isolated registration foundation.
IAM-01 uses original child IDs and actual issuer receipts for atomic provisional
account/email/credential/notices/audit, never automatic roles/sessions/academic status/
consent/certificates. Lab studentity_test_registration_activation_v1 only; real issuer,
ID format/notices/delivery/global throttles/independent recovery remain open. Details:
registration-durable-2026-11-18/ and registration-test-acceptance-2026-11-18/ under
storage/app/private/; archived handoff retains earlier governance/activation history.

FND-04 outbox v2 (3collections/10indexes) has DB-clock token/generation leases,
immutable reference commands/results/audit and atomic producer composition. Handler I/O
outside TX; exact replay verifies originals. Receipt/lookup protocol binds original
IDs/body/actor/destination/generation and fresh query/proof bounds; unknown never
means absent; noncommit is point-in-time evidence, not permission/retry. Adapter
persists original wire/time before I/O and always performs live lookup despite cache.
Synthetic provider serializes controls and atomically stores marker+original receipt;
orphan evidence fences. No positive runtime binding or independent restore/exactly-once
claim. Preserve v1/v2 labs/proposals. Evidence fnd04-outbox-2026-11-18/,
fnd04-producer-2026-11-18/, fnd04-handler-protocol-2026-11-19/ and
fnd04-handler-reconciliation-2026-11-19/ under private storage.

FND-06 immutable synthetic storage is explicit testing-only under
storage/framework/testing/fnd06-quarantine-v1/{UUID}, outside configured local disks/
public/links (default local disk serve=true). Exclusive metadata-fingerprint+byte
staging/replay, hash/length checks, no URL/delete/promotion; partial/conflicting bytes
retained/fenced.64KiB lab cap is not production policy. Actual OSACL/encryption/hostile
user races/unconfigured aliases and real scanner type/structure/malware detection
remain unaccepted. QuarantineObjectReference binds object/version/owner/digest/size/media.
Stored original FileScanCommand/UUID/policy/time is insert-once in exact testing lab
studentity_test_fnd06_scan_commands_v1; no repair/TTL/down. PersistedScanWorker prepares/
reloads before scanner; current gates/policy/health/budgets remain mandatory. Separate
fresh32-byte attemptNonce on additive scanner capability rejects cross-attempt replay;
legacy adapter cannot receive persisted bytes. Ephemeral scans remain compatible.
No authenticity from nonce relabeling. Runtime scanner/storage defaults unavailable.
Evidence fnd06-quarantine-2026-11-19/, fnd06-original-scan-2026-11-19/,
fnd06-scan-worker-2026-11-19/, fnd06-scan-attempt-2026-11-19/; proposals/source/tests
carry exact contracts. Prior full handoff bytes retained in this slice's before/.

Readiness CLI studentity:readiness [--json] reads allowlisted local config/5registered
ports without probing providers;8operational evidence blockers always unaccepted,
advisory/blocked/activationAllowed=false, exit1 intentional. No config/approval flag
can grant readiness. Delivery design includes confidential recipient-content exclusion,
authenticated private links and encrypted/controlled/monitored/off-site backups with
restore tests (FR-NOT-004/005,NFR-OPS-002). No sender implemented. Evidence
operational-readiness-2026-11-19/ and fnd04_encrypted_delivery_v1.json proposal.

## Three-plan foundation work completed (20 November review checkpoint)

FND-06: optional ScanAttemptJournal admits before even scanner health I/O; latest head
becomes pending with new generation. Append-only per-object event sequence/hash chain,
original command/nonce/DB time; stable object key/reference and same-ID body binding.
Current completion atomically appends history, lab state, audit and outbox. Exact replay
idempotent; stale completion, older-head restore, orphan/corrupt evidence deny. Snapshot
read verifies full head/history/audit/outbox in both directions. Default-deny separate
current admission/policy and recovery gates; callbacks local, scanner I/O outside TX.
Original global scan-ID authority remains worker/store. Coherent whole-lab rollback
still needs independent authority. Raw DTOs depend on accepted trusted adapters.
New lab studentity_test_fnd06_scan_journal_v1:4strict collections/8indexes, pinned
fnd06_scan_journal_v1.json, explicit install, no repair/down/TTL/delete.1000events/object
is a lab cap only. Admission monotonic budget includes Mongo latency. Failure leaves
incomplete pending request; no negative scan fabricated. Local cleared never means
permission, academic authenticity, accepted metadata promotion or delivery. No dispatch.
Two processes both admit before completing; latest alone wins. Exact simultaneous
write overlap unmeasured. Accepted stored_objects/document_versions remain unchanged.

FND-07: unbound FoundationProblemResponse and test-only HTTP harness prove fixed safe
Problem shape, real CSRF419/throttle429,200 action and safe503/422/500. Fresh server
correlation UUID, no raw message/validation/header reflection; numeric Retry-After only.
No runtime handler/route/auth activation. Immutable OpenAPI unchanged; proposed419,
registration department/year, staff SelfUser System ID and200 login/logout corrections
in fnd07_problem_contract_v1.json await review. Redacted reporting/runtime integration
still needs approval; test Handler is not installed in the application.

FND-01: SourceArtifactManifest/CLI exports hashes of selected runtime/test/build inputs
and locks, excludes env/hidden/storage/vendor/docs; traversal/alias/hidden-file/tamper
negatives pass. Before/after captures match270selected inputs; unsigned/deployable=false,
not complete release/SBOM/secrets scan/cross-OS reproducibility proof. Local verifier
uses explicit PHP8.4 and isolated child preflight/PHPUnit XML/test DB environment;
initial URI/actual writable rs0/members must be local, SQL target in-memory. Parent
never loads Dotenv. Remote target/parent-dev-env regressions pass. No install/migration/
cleanup/deploy. CIprovider/runner/permissions/secrets/signing/M0signoff remain unaccepted;
requirements in fnd01_verification_v1.json. Minor omitted artisan coverage below.

Final verifier648tests/4,176assertions GREEN112.549s, selected input hashes unchanged;
Pint18PHPfiles passed. Vite7.3.8GREEN25modules841ms separately after approved minimal
ancestor-read escalation; final verifier build=not_requested uses that separate proof.
Earlier runner exposed parent Dotenv contamination192guard errors, fixed by child
isolation; failed log retained. No lease/test weakening. Fresh review no Important,
one manifest-coverage Minor.14immutable/reference/archive hashes,6runtime routes,
accepted62+ledger/128indexes match. No config/dependency/source-document changes.
Only existing service/handoff modified from280source baseline;20newfiles. Full report,
ledger, changed-files, before/ snapshots and verified-* evidence:
storage/app/private/fnd06-scan-journal-2026-11-19/. Included in20November review checkpoint. On rollback
retain all commands/bytes/history/audit/outbox/partial/corruption evidence; restore only
reviewed source files. Local consistency and green tests never sign operational M0.

## Latest two-plan work (20 November review checkpoint)

FND-06: PrivateFileDelivery buffers internal bytes only after current server actor,
exact view/download permission and immutable object/version/hash checks. Default-deny
JournalPrivateDeliveryAuthority binds current role/record and separate recovery
revision digests, current policy/scanner health and exact latest completed clean
journal head. Before/after witnesses, byte length/hash and total monotonic budget
must match. New pending/unsafe scans, revocation/recovery changes, policy changes,
scanner outages, corrupt bytes, oversize and expired budgets deny. Lab reader testing
only, using existing private synthetic store; no accepted schema changes or promotion.
64KiB/5000ms are fixture limits only. Trusted callbacks must resolve actual current
session/role/record/program/sensitivity scope; a digest is not external authenticity.
No URL/token/network stream/public route/audit dispatcher or real provider binding.
Byte-reader allocation/timeouts and post-return revocation require accepted adapters.

FND-07: explicit opt-in FoundationProblemIntegration uses configured Laravel handler
without replacing it or editing bootstrap. Finalizes earlier503/exception-owned render
responses and safely reports ordinary reportable exceptions with code/status/server
correlation only. Real CSRF419/throttle429, validation422, safe500/503 and success/outside
scope proved. FileSecurityUnavailable now maps to safe503. Source OpenAPI/DTOs unchanged;
corrections remain proposals. Important activation limit: Route::run returns
HttpResponseException responses directly, and controller/middleware direct responses
also bypass handler finalization. Failed probe retained; reviewed response boundary
and logging/self-report hooks/finalizer composition are still required. No universal
redaction or runtime API activation claimed.

Fresh independent review: no Critical/Important in unbound scope. Deferred Minor:
add still-authorized positive-to-positive access revision test; null-revocation and
positive-to-positive recovery revisions covered. Existing stale-local-lease test
reliability fixed: current provider reconciliation now precedes the fresh100ms claim;
stale-token rejection/fresh-result/one-effect assertions and production leases unchanged.
Timing probe proved old ordering expired during record; failed logs retained. New/factory
Pint9files pass; final full verifier664tests/4,237assertions GREEN129.402s,
278selected source inputs unchanged, deployable=false. Older handler test has identical preexisting Pint style violations
in its before-copy and was not reformatted. Focused33tests/179assertions pass. Evidence/report/ledger/before hashes:
storage/app/private/fnd06-delivery-fnd07-integration-2026-11-19/.
New proposal contracts: fnd06_private_delivery_v1.json and
fnd07_handler_integration_v1.json under docs/proposals/. Preserve prior dirty work.

## Latest response-boundary and manifest follow-ups (20 November review checkpoint)

FND-07: unregistered FoundationProblemBoundary outer middleware shares an explicit
FoundationProblemResponses registry with handler adapter. Direct controller/inner
middleware errors and router-caught HttpResponseException now yield fixed safe Problems
without reading bodies/running streams/copying arbitrary headers or cookies. Supported
error statuses preserve semantics, unknown errors become500, only numeric429Retry-After
survives. Success/outside-scope behavior preserved. Sealed private snapshots and weak
request-object contexts protect repeated normalization/reporting from downstream
mutation and keep server correlation shared within a request, fresh across requests.
Real pipeline probes cover direct/exception/middleware/stream, spoofed Problem/correlation,
mutation/repeated reporting, status/header negatives and success/scope preservation.
No bootstrap/routes/authoritative DTO or OpenAPI changes. Boundary must enclose actual
response-producing middleware; later headers/direct sends/self-report hooks/log channels/
finalizer composition/host lifecycle remain review gates. Handler alone still does not
cover direct responses; opt-in paired composition closes the demonstrated bypass.

FND-01: root artisan explicitly included in source-input allowlist. Exact hash and
change/removal comparison regression closes prior Minor. Source manifest still unsigned,
selected-input/nondeployable; CIprovider/permissions/signing and M0 signoff unaccepted.

Review caught mutable cached outgoing response -> repeated-report log poisoning; RED
regression proved it, fixed by private cache plus sealed clones. Request-context RED
also proved reused exception needs new correlation across requests; fixed with weak
request contexts. Fresh follow-up review: no remaining Critical/Important/new Minor.
Preliminary674tests/4435assertions passed92.906s, but manifest correctly rejected source
changes while review fix landed; retained log is not frozen-source acceptance. Final
frozen verifier677tests/4,449assertions GREEN96.284s;283selected inputs unchanged,
deployable=false. Focused30tests/303assertions and Pint7PHPfiles GREEN.
Evidence/report/ledger/before snapshot310files:
storage/app/private/fnd07-boundary-fnd01-coverage-2026-11-19/.

## FND-01 strict candidate preparation (20 November review checkpoint)

Existing verifier now supports --ci-candidate: requires Vite build and PHPUnit
--fail-on-all-issues/--fail-on-empty-test-suite; rejects preflight-only bypass and
unknown/duplicate options. Original default/preflight behavior and child environment/
local topology isolation remain intact. Real subprocess fixtures prove six issue
classes fail strict while ordinary mode allows them; clean strict suite passes and
empty suite fails. No installations, migrations, cleanup, upload, signing or deployment.
Summary profile=strict-ci-candidate, strictTests=true, frontendBuild=passed,
sourceInputsUnchanged=true, deployable=false. FND-01 host/secure CI acceptance not granted.

Focused13tests/32assertions GREEN10.271s; Pint4PHPfiles passed. Final frozen strict
688tests/4,477assertions GREEN100.022s; Vite25modules733ms;285inputs unchanged. Initial
sandbox build precheck could not resolve Vite config; minimal broader-filesystem-read
verification then passed. No source/test/lease weakening. Review no Critical/Important/
new Minor.314baseline files preserved except reviewed runner/test/proposal/handoff;
2newPHPfiles plus1newCIreviewpacket. Evidence/report/before hashes:
storage/app/private/fnd01-strict-verification-2026-11-19/.

CI packet records exact lock/catalogue hashes, host/runtime/permissions/secret isolation,
fresh-schema prerequisite (existing migration can collMod, never run as repair), strict
entry point, artifact/SBOM/signing and M0 acceptance evidence. No provider/runner/image/
action/signing identity or approval invented. No remote/workflow created. The next
required input is the owner's CI target; do not infer GitHub from available tools.

## 20 November review and commit checkpoint

Owner explicitly requested review and commit of current work.61source/test/proposal/
handoff files included; protected Identity/M0 test/archive/reference bytes unchanged
and excluded from staging. Review fixed stale preview clearance via fresh bound revision
witnesses/time budget, corrected UTC, aligned unbound SelfUser kinds/states to accepted
catalogue and removed invented System ID format. Nullable SelfUser remains proposed.
Three new producer callback count assertions failed; corrected exact invocation counts
without changing production behavior. Test hardening, opaque ID/revision/time negatives
and all candidate PHP formatting pass. Follow-up independent review no Critical/Important.

Final frozen strict710tests/4,550assertions GREEN94.985s, Vite25modules790ms,
291selected input hashes unchanged/nondeployable. Preliminary710/4549 and build passed
but source hash check rejected formatting changes during that run; retained evidence
is historical, final verified-* files are authoritative. Accepted62collections+ledger/
128indexes and6routes remain unchanged. No installs/migrations/active providers/routes/
contract approval/hosted CI/release grant. No push performed. Detailed review/report:
storage/app/private/precommit-review-2026-11-20/.

## Remaining gates and carried-forward work

- Real independent authority ownership/peer trust/keys/generations/current revoke,
  durable ACK/lookup/original command/cursor and approved recovery baseline/pruning.
  A second database on the restored Mongo server is not independence. Real encrypted
  transport needs two-instance/outage/restore/key-rotation acceptance; no fallback grant.
- Actual permanent ID issuer/format and restore-safe nonrecycling; approved production
  registration/issuance/MFA-replay schema diffs, negative proof and repair plan.
- Exact approved historical terms/notices, separate consent/legal basis, institutional
  approval, mail/hosting/region/budget/custody and measured operational acceptance.
- Credentials/verified aliases, reset/revocation/native rotation, privileged password+
  TOTP or approved extra MFA and coordinator invitations. IAM-07/IAM-08 remain separate.
  Remember-me/bearer shortcuts disabled. No student/upload/certificate features until gates.
- OpenAPI review: registration lacks department/year despite FR-ID-001; staff SelfUser
  cannot require student systemId; map CSRF419/uncertain failures; login/logout use200
  schema results, not harness204. Wider FND-01/04/05/06/07 acceptance remains open.
- Deferred test minors closed: observed zero DB writes in empty-genesis recovery;
  replaced flaky OTP substring with structural MAC; asserted registration entered markers
  absent before release; repaired negative test helpers to rethrow AssertionFailedError;
  asserted producer fault-stage and exact enqueue-hook counts; added still-authorized
  positive-to-positive access revision test in PrivateDeliveryLabTest.
- Protocol negative assertions split; proving intended parser failure with otherwise
  valid duplicate/oversize input remains a coverage Minor. Updated query wording states
  that only nonce/issuedAt/command context are hashed, with lower/freshness/observation
  bounds checked separately.
- Readiness wording minor closed: included SRS NFR-OPS-003 initial RPO/RTO design targets
  <=24 hours alongside measured institutionally approved deployed objectives.
  Diagnostic stays blocked; no acceptance or retention/budget value was invented.

- FND-06 unbound preview buffer foundation: distinct preview reference, original-buffer
  hash/size checks and fresh before/after clearance revision digests; changed/uncertain
  clearance or elapsed lab budget deny. Timestamp normalizes UTC. No storage writes,
  immutable stored-preview proof, actual type/scanner validation or ordinary access
  permission established. Generator and current scan/policy/recovery callback remain
  trusted unbound adapters; caller clock is not accepted operational clock authority.
- FND-07 SelfUserProfile is an unbound proposed projection, not approved API alignment.
  Uses accepted student/coordinator/system_admin kinds and catalogue account states.
  Student System ID is an opaque bounded issuer reference; no university ID format is
  invented. Nullable nonstudent systemId still requires authoritative OpenAPI review.
- Deferred FND-06 coverage Minor: platform-supported real ancestor/object symlink and
  hard-link negatives must reject and preserve target bytes. Configured-link cases
  covered; no actual filesystem-link test or implementation bypass demonstrated.

- FND-01 artisan manifest coverage Minor closed by latest slice; manifest remains
  selected-input/unsigned/deployable=false, not an accepted release artifact.
- Investigate earlier intermittent outbox lost-ACK record failure under100ms fixture
  lease (timing sensitivity suspected, not proven); retained scan-attempt failed log.
  Final three-plan runs passed without changing those tests or lease rules.

## Daily commands

Use explicit PHP84 or $env:Path='C:\tools\php84;'+$env:Path in that terminal.
php artisan serve --host=127.0.0.1 --port=8000; npm.cmd run dev in a second terminal.
MongoDB must run; Start-Service may need elevation. Never replace an existing service
or erase data to start development. Tests: & 'C:\tools\php84\php.exe' vendor/bin/phpunit.
Readiness: & 'C:\tools\php84\php.exe' artisan studentity:readiness --json
Exit1 is intentional while operational acceptance is blocked.
Read-only schema check: explicit PHP84 followed by
storage/app/private/laravel13-upgrade-2026-11-17/schema-inspection.php.

Local verification (read-only target preflight, then synthetic tests/input manifest):
& 'C:\tools\php84\php.exe' scripts/verify-local.php --preflight-only
& 'C:\tools\php84\php.exe' scripts/verify-local.php
Optional --build runs npm.cmd run build; Codex sandbox may need ancestor-read access.
Strict local candidate (tests fail on all issues plus mandatory build):
& 'C:\tools\php84\php.exe' scripts/verify-local.php --ci-candidate
It cannot be combined with --preflight-only and never grants release acceptance.
Input manifest: explicit PHP84 artisan studentity:artifact-manifest --json.
No dependency installs, migrations, cleanup, commit or deployment in these scripts.
