# Studentity canonical development handoff

Updated 27 November 2026 (Asia/Shanghai). Repository master, starting HEAD d4b3086.
Owner authorized committing/pushing reviewed changes to master and deleting the other
branch on20November2026. Preserve all commits; never force-push or rewrite history.
This is the only maintained handoff. antigravityPushed was deleted after its commits
were confirmed in master. Consolidated master was pushed at cc122c8 on20November.
Historical detail: git history and [archived handoff](handout_codex_archive_2026-11-18_pre_governance_identity.md).
Full pre-polish handoff bytes are retained in storage/app/private/cline-polish-2026-11-20/before/docs/handoffs/.

## Mandatory instructions

- Read AGENTS.md. Use synthetic data only; preserve all existing work.
- Preserve finalized SRS v1.1, Blueprint, accepted ADR01/02, physical-model invariants
  and source OpenAPI. Consult ../Documentations/ and
  ../Studentity_M0_Laravel_MongoDB_Approved_v1.0/ for relevant requirements.
- Preserve complete app/Domain/Identity/ and tests/Feature/M0MongoLiveIntegrationTest.php
  without edits/reset/staging/commit. Identity/UserKind.php SHA256:
  A79AE473FEE5482E5DFFB0EEF81E3F4BD4C350EA85F2B7AB0D99884CC065BED1;
  M0 test SHA256:5D54B0911AB0CCE2BD8D5EE6F4CE980A9BE7539BFE531DE5A2A0EC2281BC19A0.
- Archive bytes/SRS/accepted architecture remain immutable. Previous authorization
  to commit exact protected bytes does not authorize subsequent changes.
- Never drop/delete/repair accepted MongoDB databases or change accepted metadata.
  No blanket TTLs. Existing catalogue migration can collMod existing collections;
  never run it locally as repair. CI draft migration is fresh-job-instance only.
- No real data, credentials, invented approvals/providers/regions/budgets/retention,
  external provisioning, business endpoint activation or production acceptance.
- Work directly on master. One agent writes at a time. Do not create competing
  repositories, worktrees, roadmaps or handoffs. Compare actual Git state before work.
- Do not expose .env values; .env/.env.testing are ignored and are not CI artifacts.

## Verified baseline and timeline

Explicit PHP C:\tools\php84\php.exe8.4.27; bare PHP can resolve XAMPP8.2.12.
Laravel13.42.0/Tinker3.1.0/Sanctum4.4.0/PHPUnit12.5.44;
Mongo integration5.12/library2.5.1/ext2.5.7/server8.3.7rs0;
Node24.13/npm11.6/Vite7.3.8. Lockfiles/config/source contracts unchanged by this review.
Last earlier frozen strict baseline:710tests/4550assertions,94.985s,
Vite25modules790ms; evidence storage/app/private/precommit-review-2026-11-20/.
Latest local merged-tree strict:731tests/4624assertions GREEN131.833s, zero
skipped/issues; Vite25modules1.04s, selected inputs unchanged, deployable=false.
Evidence:storage/app/private/local-verification/13f989996cc00084ee16bb91b227fb77/.
Earlier polish report:storage/app/private/cline-polish-2026-11-20/completion-report.md.
PHP syntax275files and Pint9touched PHPfiles passed during that review.

Accepted studentity_dev_m0/studentity_test_m0 each have62catalogue collections plus
migration ledger,128catalogue-defined indexes plus62automatic _id indexes on those
collections (190physical indexes, excluding ledger indexes). No accepted metadata repair.
Six runtime routes; no real auth/business API. Runtime security/issuer/storage/scanner/
delivery ports remain unavailable. Local file/JSON sessions, encryption=false, sync queue;
no bearer/remember-me fallback. Readiness remains blocked/activationAllowed=false.

| Date | Development evidence |
|---|---|
|17Nov|Runtime/Mongo baseline ca22296; initial transaction/schema proofs.|
|18Nov|Laravel13 alignment; identity/session/recovery/registration/outbox labs.|
|19-20Nov|Scanner/journal/private delivery/HTTP/strict-verifier foundations; reviewed ab6d1d3.|
|20Nov|Cline702a25d committed a manual CI candidate and test changes; its full run had2errors/1skip. Codex polish committed5fe7ac8; merged/pushedcc122c8; redundant branch deleted.|

M0 is INCOMPLETE. Runtime/schema/transaction/identity/session/file/clearance lab proofs
exist; real hosting/authority/issuer/session/provider/recovery/secure CI/signing and
institutional signoff do not. No completion percentage or promised date is inferred.

## Current review findings and fixes

Evidence root:storage/app/private/cline-polish-2026-11-20/ (before snapshots, failure/
positive logs, metadata probes, lint/format checks, report and final manifests).

- Cline link test skipped unconditionally before any read/assertion. Replaced with a
  genuine hard-link test against LocalQuarantineLabStore through private delivery:
  source link count>1, read denied, original/alias bytes unchanged. Unique synthetic
  evidence is retained. Windows symlink creation is unavailable here: capability JSON
  under storage/app/private/fnd06-platform-links/ records it. This is capability
  observation, NOT proof of symlink rejection. On capable hosts the test creates real
  object and ancestor symlinks, validates resolved test-only move paths, checks denial
  and target preservation. Linux CI run45095083400 proved those probes on ubuntu-24.04. No unconditional skips
  and no weakening of strict --fail-on-all-issues policy.
- Parser tests now start from valid original wire; duplicate purpose preserves decoded
  body, oversize input remains valid JSON, exact canonical/length failure is asserted.
  Bogus duplicate-constructor test is now a separate invalid-purpose test. Restored
  nested-reference detachment and secret-field negatives removed by Cline.
- Reported outbox failures are fixture timing sensitivity, not demonstrated unrelated
  production errors. Deterministic200ms valid lookup reproduces100ms lease failure.
  Positive fixtures use60s; expiry/restart cases use the SAME immutable1s policy and
  wait1.1s. Record-abort proof reconciles before claim and asserts intended fault reached.
  Stale-owner, expired-lease, no-result-on-abort, same receipt and single-effect checks
  remain. Production lease/audit/repository/worker code is unchanged. An attempted
  different-policy restart was correctly denied during investigation; failure retained.
- CI draft lacked schema initialization: Fnd02 tests require BOTH dev/test catalogue
  databases/ledgers. Draft now checks both empty before any fresh-job migration.
  No local migration or hosted workflow was run. Added tracked read-only
  scripts/check-mongo-catalogue.php comparing actual collections, validators, validation
  modes, index identities/options/counts and ledger; topology preflight is named correctly.
- CI uses fixed ubuntu-24.04 matching noble feed, manual workflow_dispatch only,
  contents:read and nonpersistent checkout token, job timeout and real30s outer
  readiness timeout, masked disposable key, explicit dependency lifecycle steps.
  Existing action SHA identities inspected. Mongo8.0 patch/runtime image/registries
  are not completely frozen release inputs; hosted compatibility was measured in run45095083400; immutable release identity remains open.
- SourceArtifactManifest now includes this explicit CI YAML execution input; other
  hidden files remain excluded. It still exports only selected unsigned hashes and
  deployable=false. Workflow/helper changes are included in before/after comparisons.
- No root/tools check_* or *_tmp scratch files remain on disk; stale IDE tabs are not
  evidence that files exist. No such files were deleted by this review.

Focused verification:112tests/301assertions GREEN10.651s, zero skipped. Review of fixes:
no remaining Critical/Important. New diagnostic Minor: checker does not compare all
behavior-changing options such as unexpected index sparse/collation/hidden or collection
options beyond the explicitly checked validator/validation settings. It grants no authority.

## CI status and next documented work

GitHub Actions target EvonMaxilom624/studentity was already selected. Do not ask for the
same provider choice again. .github/workflows/ci-strict-candidate.yml is a committed
manual-only candidate with corrections committed and pushed in cc122c8. Committing
configuration is not hosted execution or operational acceptance. On23November the
owner authorized review and a manual hosted candidate run. First run45094522096
tested cc122c84fd95b1be43066e60ac9efc6404869d17 and failed one HTTP smoke test
because Vite manifest was absent on the clean checkout. Evidence:
storage/app/private/fnd01-hosted-2026-11-23/; no production acceptance or deployment.

1. FND-01 hosted baseline passed at578e1fd/run45095083400. Node24 checkout
   maintenance and artifact/SBOM/signing/evidence review are prepared in the latest
   review section below; publish/rerun only with explicit owner authorization.
   Complete release/retention/signing and institutional acceptance remain separate.
2. FND07 API/DTO/middleware review is complete in the activation review packet.
   Exact scope/derivative contract and runtime composition decisions remain proposed;
   no handler, boundary, business controller or route is registered by this review.
3. Visible synthetic UI design can start next alongside these approval decisions;
   approve screens before implementation. Real account/data/file workflows stay gated.
4. FND06 real authentication/current authorization, independent recovery and private
   storage/ACL/encryption/scanner/proxy/audit acceptance remain separate operational
   work. Lab clean status/preview reference is not delivery permission or authenticity.

## Foundations to preserve

Registration: historical synthetic G05/G09 lab aliases (not finalized institutional
gate meanings), authority-first permanent IDs and purpose/recipient/
notice/OTP guards. IAM01 atomic provisional user/email/credential/notices/audit uses
original child IDs and issuer receipts; never grants roles/sessions/academic status/
consent/certificates. Exact accepted notices/legal basis/issuer/ID format and durable
shared throttling remain unaccepted. Evidence:registration-durable-2026-11-18/ and
registration-test-acceptance-2026-11-18/ under private storage.

Outbox/handler: v2 DB-clock token/generation leases, immutable original command/result/
audit, producer atomicity; external I/O outside transaction. Receipts/lookup bind original
IDs/body/destination/actor/generation and fresh observation bounds. Unknown never means
absent; noncommit evidence does not grant retry. Cache never substitutes for live lookup.
Provider marker/receipt controls and orphan fencing are synthetic; no independent restore
or exactly-once production claim. Preserve v1/v2 labs/proposals and prior evidence.

Files: immutable synthetic quarantine outside configured disks/public/links;
64KiB limit is lab-only. Original scan command persisted before scanner; fresh32byte
attempt nonce prevents cross-attempt replay only when trusted scanner adapters bind it.
Journal latest head becomes pending before scanner health; complete state/history/audit/
outbox atomic, stale/older-head/orphan evidence deny.1000events/object is a lab cap.
Coherent whole-backup rollback requires separate recovery authority. Journal global
scan-ID authority remains original worker/store. Exact simultaneous write overlap unmeasured.

Private delivery: exact view/download operation, current actor/record/policy/scan/recovery
revision witnesses, byte length/hash and monotonic budget before internal buffer return;
no token, URL, stream or real provider activation. Post-return revocation/network delivery
is not atomic with local comparisons. Preview uses distinct object ID, original-buffer
checks, matching fresh clearance witnesses/time budget and UTC; no stored-preview
immutability or real scanner/type/structure proof. SelfUser projection uses accepted
kinds/states and opaque issuer reference, remains unbound/proposed, never issuance proof.

HTTP: fixed safe Problem shape, server correlation, numeric429Retry-After only; paired
handler/outer response boundary handles direct error responses only in reviewed scope/
ordering. Private cache/sealed clones and weak request contexts prevent mutation/log
poisoning and preserve fresh request correlation. Later direct sends, logging hooks and
middleware still need actual runtime acceptance. New Cline factory unit coverage retained.

## Remaining operational gates and coverage

- Named independent authorities/peer trust/current revoke and durable ACK/lookup/original
  commands/cursor/high-water/recovery baseline/pruning. Same-server restored DB is not
  independent. Two-instance encrypted transport/outage/restore/rotation proofs pending.
- Nonrecycling permanent ID issuer/format, accepted production schemas and key custody;
  approved privacy/terms/notices/legal basis, hosting/region/budget and ownership.
- Credentials/verified aliases/reset/revocation/native rotation, privileged password+TOTP
  or approved extra MFA/replay policy, coordinator invitations; IAM07/08 separate.
- Private-file backups encrypted/controlled/monitored/off-site with restore evidence.
  SRS NFR-OPS-003 initial RPO/RTO design targets<=24h coexist with measured approved
  deployed objectives; no invented retention/budget or acceptance.
- Actual OSACL/hostile-user/unconfigured alias/link race proof, real scanner malicious/
  unsafe/structure detection, optimized preview originals and approved isolation/replacement/
  notification/retention/disposal procedures. Windows actual symlink negatives remain open.
- Complete service-level account/session no-write proof and exact simultaneous journal
  write overlap remain coverage follow-ups. Parser/input isolation now genuinely proven.

## Daily commands

Use explicit PHP84 or set $env:Path='C:\tools\php84;'+$env:Path for that terminal.
Serve locally: php artisan serve --host=127.0.0.1 --port=8000; npm.cmd run dev separately.
MongoDB must run; Start-Service may require elevation. Never replace services or erase data.
Tests: & 'C:\tools\php84\php.exe' vendor/bin/phpunit
Strict tests+build: & 'C:\tools\php84\php.exe' scripts/verify-local.php --ci-candidate
Read-only target: same verifier --preflight-only (cannot combine with --ci-candidate).
Read-only live schema: APP_ENV=testing and MONGODB_URI=localrs0 environment, then
& 'C:\tools\php84\php.exe' scripts/check-mongo-catalogue.php
Readiness: & 'C:\tools\php84\php.exe' artisan studentity:readiness --json
Readiness exit1 is intentional. No local command grants operational acceptance.

## Owner-authorized repository consolidation (20November2026)

Owner requested push to master and deletion of the other branch. Remote master
contains bd7f5b7 (sanitize .env.example) diverging from local702a25d; preserve it
through a normal merge, without force-push. antigravityPushed=ab6d1d3 is already
an ancestor of master; deleting its reference does not remove its history.
Historical20November scope: hosted CI execution and deployment were unrequested.
On23November owner authorized the hosted candidate run; deployment remains unrequested.

Pre-push combined-tree strict verification passed:731tests/4624assertions,
131.833s; Vite25modules1.04s. Evidence:
storage/app/private/local-verification/13f989996cc00084ee16bb91b227fb77/.
The first sandboxed attempt passed tests but could not resolve Vite configuration;
the normal-filesystem rerun passed the complete strict verifier, deployable=false.
Review polish commit5fe7ac8; normal merge preserves remote bd7f5b7.

## FND-01 hosted measurement (23November2026)

First run:https://github.com/EvonMaxilom624/studentity/actions/runs/45094522096
Job137431267330: all setup/install/fresh-schema/topology/catalogue steps passed;
731tests/4635assertions, one ExampleTest failure, no skips reported. Root Blade
view needs public/build/manifest.json before HTTP tests; the strict verifier builds
only after tests. The Windows baseline had preexisting ignored assets. No backend
security/parser/link failure reported on Linux. Strict build was not reached.

Prepared minimal workflow fix: add npm run build after schema checks and before
the strict verifier; retain its mandatory post-test build and strict flags.
Local validation:YAML parsed; Vite25modules1.94s; ExampleTest filter2tests/2assertions
GREEN19.932s. No test, production PHP, dependency, protected or accepted schema edits.
Owner explicitly approved committing/pushing this fix and handoff, then rerunning.
Corrected workflow committed/pushed578e1fd and hosted run45095083400 passed.
Tested source578e1fde6b91493a14623b4f8d8b310cffa0c343, job137433010731.
731tests/4636assertions GREEN40.362s; zero skips/issues in PHPUnit; Vite prebuild
779ms and strict post-test build768ms,25modules each; source inputs unchanged;
deployable=false. Full runner steps completed successfully, including Linux real
object/ancestor symlink negatives. Windows actual symlink negatives remain open.
Measured runner ubuntu-24.04 image20261116.376.1, PHP8.4.28, Composer2.11.2, ext-mongodb2.5.7,
Mongo8.0.38rs0, Node24.22.3/npm11.19.1. Both fresh catalogues matched62collections,
128catalogue-defined indexes/190physical indexes, plus separate migration ledger.
No local accepted DB mutation; no dependency/platform ignores or updates.
Run:https://github.com/EvonMaxilom624/studentity/actions/runs/45095083400
Private local evidence:storage/app/private/fnd01-hosted-2026-11-23/
(review.json, run-45095083400.json, log excerpts and preservation.json).
Hosted verifier full per-file manifests remained ephemeral on runner; no artifact
upload was added. Local evidence retains run metadata and selected logs, not the
entire hosted filesystem or a signed release artifact.
Nonblocking GitHub warning: pinned checkout v4 targets Node20; hosted runner forces
Node24. Action worked; review a supported replacement pin as next CI maintenance.
No global acceptance: signed artifacts/SBOM/immutable runtimes/signing custody/
network-isolation/retention and institutional M0 signoff remain pending.

Next documented plan after hosted measurement: FND-01 release-evidence acceptance
review (complete artifact inputs/SBOM/dependency scanning, immutable runtime/package
identities, signing authority/custody and institutional signoff). This candidate
does not implement publishing/signing/deployment or grant acceptance. The next
application development review is FND-07 API scope/DTO approval and middleware/
logging/exception-hook placement review, before runtime binding; keep original
OpenAPI immutable. FND-06 real storage/scanner/authorization/recovery acceptance
follows its own gates. Windows actual symlink proof remains open.

## FND-01 maintenance and FND-07 review (23November2026)

Owner requested the next two documented plans. Work starts clean at d4b3086.
FND-01: prepared checkout v7.0.1 full pin
3d3c42e5aac5ba805825da76410c181273ba90b1 (official tag, verified commit metadata,
manifest Node24; measured runner satisfies minimum). Same manual-only trigger,
read-only rights and persist-credentials:false. Updated existing
[CI acceptance packet](../proposals/fnd01_ci_acceptance_packet_v1.md) with complete
artifact/SBOM/native/bundled dependency/scanner/signing/retention gap review.
Private inventory:114Composer entries/231npm paths, NOT a release SBOM or scan.
New pin local YAML valid; hosted measurement pending commit/push authorization.
No dependency/tool install, artifact upload, signing or production activation.

FND-07: completed source/SRS/ADR/installed-Laravel/bootstrap/logging review in
[API activation review](../proposals/fnd07_api_activation_review_v1.md). Seven exact
candidate method/path pairs; no route/handler binding. Department/year fields,
nullable staff SystemID, cookie-only versus historical bearer security,419,
400/409 status preservation and middleware/reporting/finalizer/host boundaries
are concrete review decisions. Source already specifies login/logout200;204 labs
must align. Accepted academic year_level is integer but lab RegistrationIntent
uses string: explicit versioned conversion needed, never reinterpret old intents.
Source OpenAPI remains unchanged and all DTO corrections remain proposed.
Focused existing FoundationProblem/SelfUser suites:42tests/335assertions GREEN5.612s.
Direct factory probe confirms source400/409 currently become500; not runtime-bound.
No implementation can infer accepted legal notices, providers or institutional signoff.

Next visible work can be synthetic UI design: mobile/desktop login, registration
and task-oriented student dashboard, including provisional, pending/unavailable,
error/session-expiry and read-only states. Review visual direction/screens before
implementing a preview. No real student/business route or backend activation is
required for isolated mockups; live account/data/file operations still require
their accepted security/contract gates. Current root remains the M0 page.

Evidence:storage/app/private/fnd01-fnd07-review-2026-11-23/.

## Visible UI design exploration (23November2026)

Owner requested visible UI design. First draft uses an explicitly assumed navy/teal
university-portal direction; branding/visual approval remains pending. Three local
static mockups: password/email-code login, account+academic registration, and a
task-oriented student dashboard. Desktop/mobile selector; safe preview dialogs.
All names/academic choices are synthetic, classifications match the six SRS labels,
academic status is provisional, and questionnaire progress is not clearance.
Notice placement is a disabled placeholder, not accepted university legal text.

Artifact:storage/app/private/ui-design-2026-11-23/.superpowers/brainstorm/
448-1795399102/content/studentity-first-look.html. This is an isolated design
exploration, not a new application entry point or Node backend. No Laravel routes,
React runtime, dependencies, account actions or MongoDB data were changed.
Local companion serves it on loopback; full gated URL lives in that session's
state/server-info (do not commit the session key). Keep companion running for
review; restart with the same ignored design project directory if needed.
Screenshots/browser-review.json are under storage/app/private/ui-design-2026-11-23/.

Browser review:all3screens at desktop/mobile widths (6layouts), no JS exceptions or
horizontal overflow; labels, screen/method switching, password visibility and
preview dialogs checked. No application regression suite rerun for static mockups.
Next: owner visual feedback, then an approved design brief and implementation plan
before changing production React UI. Live account/student/file features stay gated.
Previous four-file CI/API review changes and pending commit/push authorization
are preserved. No commit, push, hosted run, deployment or API activation in this task.

## React synthetic preview integration (23November2026)

After viewing the standalone draft, owner said to proceed with the next development
plan. Interpreted as adopting the reviewed navy/teal layout for a synthetic React
preview, not institutional branding or backend/API approval. Bounded implementation
record:docs/proposals/ui_preview_implementation_v1.md. Work directly on master;
all prior dirty CI/API files retained. No new commit/push authorization inferred.

Existing resources/js/studentity-m0.tsx now mounts preview/PreviewApp. Shared chrome,
LoginScreen, RegistrationScreen, DashboardScreen, typed callbacks and preview.css
preserve desktop/mobile designs. No iframe, raw HTML injection, API client, credential
state, form submission, browser storage, upload or account action. Conditional screen
mounting clears entered input when switching; password visibility resets. Notice
acknowledgment remains disabled. Sample progress/status never asserts real clearance.
Existing root / displays the synthetic preview; same six Laravel routes, /m0/health
preserved, no controller/handler/security authority/runtime-binding changes.

Verification:browser RED on old root, then GREEN six desktop/mobile layouts,
labels/no overflow, screen/method/password/dialog/Escape interactions; zero JS errors
and zero API/mutation requests. TypeScript noEmit passed. Strict local verifier:
731tests/4624assertions GREEN135.114s; Vite31modules834ms; selected inputs unchanged,
deployable=false. Evidence:storage/app/private/local-verification/
06598538104e8358a4f09eb1b67b7642/ and storage/app/private/ui-react-2026-11-23/.
Independent final review found no important issues. Protected source, immutable
requirements/OpenAPI, dependencies, routes and previous CI/API edits preserved.
No migration/destructive Mongo operation, deployment, hosted run or commit/push.

Next visible slice: review integrated screens, then design the profiling questionnaire
and requirements/checklist views with synthetic pending/unavailable/read-only states.
Live authentication, submissions and files still require accepted contract/security
and operational decisions. Pending Node24 CI pin publication approval is still open.

## Questionnaire and checklist synthetic pages (23November2026)

Owner explicitly requested profiling questionnaire and requirements/checklist views
with pending/unavailable/read-only states. Implemented inside the existing root
React preview, no new Laravel route. StudentWorkspace shares navigation/state
controls; tabs04Questionnaire/05Requirements and dashboard links open the pages.
Questionnaire has two neutral required example fields, one optional example note,
Previous/Next with accessible validation and heading focus after step changes,
required-only progress and final review. All questions are explicitly illustrative,
not an approved university catalogue. Answers stay in component memory and clear
on screen leave; no Saved Draft, official submission or attestation is fabricated.
Unavailable hides editable questions; read-only shows fixed synthetic history.
Checklist separates missing/pending/unknown status; no filepicker, upload/download
URL, academic approval or clearance. Official acknowledgment/file/submit actions
remain disabled. Live auth/submission/file and operational gates remain unchanged.

Verification:22browser layout/state cases across desktop/mobile; optional answers
excluded from progress; final review, keyboard Next/Tab, navigation and unmount
checked; zero JS errors/API/mutation requests. Keyboard issue found in independent
review reproduced RED then fixed GREEN; no outstanding review findings.
TypeScript noEmit passed. Final strict731tests/4624assertions GREEN98.258s,
Vite35modules748ms; selected source hashes unchanged, deployable=false. Evidence:
storage/app/private/local-verification/4e41af81ce0e45ea63ebddb75a9d877c/ and
storage/app/private/ui-questionnaire-2026-11-23/. Earlier pre-focus strict run
also passed, but final result above is authoritative for this task.
Prior CI/API changes, protected sources, dependencies and all six routes preserved.
No commit/push, hosted run, migration, destructive Mongo action or deployment.

Next: owner review of the five synthetic screens and their state transitions;
resolve versioned approved question/document catalogues and API contract decisions
before any real persistence, acknowledgment or file interaction. Pending CI pin
publication authorization remains open; this UI task does not grant it.

## Tasks2/3 engineering contract; task4 operational audit (23November2026)

Owner explicitly HELD task1 and authorized completing2API contracts,3corrections,
4operational identity/session. No commit/push/hosted CI or publication is authorized;
earlier publication questions are superseded by this hold. Preserve prior dirty work.

Tasks2/3 engineering work completed: resources/contracts/identity-v1.openapi.json
is a self-contained7-operation/9-schema owner-authorized derivative, source hash
recorded; original OpenAPI/SRS/ADR unchanged. Cookie-only protected scope, CSRF419,
source login/logout200, nullable SystemID, registration department/integer year and
exact existing classifications. Problem400/409 preserve safe status/code with no
raw detail; correlated fixed five-field Problem. Exact IdentityApiScope predicate
is UNBOUND; no routes/global handlers/production providers activated.

RegistrationApiInput is a closed, typed, detached scalar DTO using server-supplied
academic maps/year values (not request-provided acceptance). It emits integerBSON
academic selections, explicitly constructs NEW legacy string-year intents, hashes
optional passwords via injected driver, redacts debugging and forbids serialization.
No old intent/payload purpose or stored bytes are changed. Engineering transport
bounds (10characters/72UTF8bytes bcrypt-compatible) are not institutional policy.
Exact current notices/issuer proof and real authentication/consent remain separate.
Decision record:docs/proposals/identity_api_corrections_v1.md. Parent review packets
now distinguish completed engineering correction from pending operational acceptance.

Task4 PARTIAL, NOT operationally complete: audit confirms runtime authority/issuer/
activation providers unavailable, shared encrypted/fenced session stores test-only,
no actual independent recovery/key custody or permanent issuer format accepted.
Owner asked for approved operational choices; none supplied during this task.
Do not relabel labs, set encryption flags, invent notices/keys/providers or claim ready.

Important traceability/admission fix: finalized SRS11.4 G04=privacy/public verification,
G05=retention/legal holds, G09=technical service acceptance. Historical registration/
invitation lab aliases G05/G09 are preserved as history, NOT institutional scopes.
FeatureGatePolicy real-data mode now explicitly denies unaccepted gate composition,
even with a nonsynthetic confirming provider. Explicit synthetic false-mode lab
checks retain their historical bytes/behavior; no real-data promotion is allowed.
Readiness notices basis correctedG04; no claim G04 alone satisfies admission.
Actual readiness remains blocked/activationAllowed=false, local file JSON sessions,
encryption=false. Accepted operational service/transport/custodian evidence is absent.

Verification:RED->GREEN for strict adapter/status/derivative/gate corrections;
focused95tests790assertions GREEN2.606s, zero issues. Pint touched PHP passes,
PHP syntax11files passes. Independent final review no Critical/Important; stale
engineering-status wording corrected. Full strict762tests4920assertions GREEN
108.481s; Vite35modules841ms; selected source inputs unchanged, deployable=false.
Evidence:storage/app/private/local-verification/d50f9a9f7a7f416ba66742359b14e43a/
and storage/app/private/api-identity-2026-11-23/ (failures/positive logs, readiness,
source snapshots, lint/format, final report). Prior UI/CI edits, protected Identity/
M0 live test, dependencies, source documents and six runtime routes preserved.
No migrations/drop/repair, cloud services, operational grants, commit/push or deploy.

Next required task4 inputs: approved permanent SystemID format/issuer ownership;
exact approved notices/legal basis; independently operated security/recovery service,
trust roots/key custodians/rotation and durable revoke/lookup/recovery baseline;
shared encrypted session service/host and same-origin/TLS/clock policy; privileged
MFA/enrollment/recovery policy and independent two-instance acceptance evidence.
Until supplied, keep runtime deny-by-default and task4 operationally incomplete.

## Tasks5/6/7 scope decision resolved (24November2026)

Owner directed checking the next development plan step and completing 1 task.
Task 5 (FND-06 Local Synthetic File Foundations) was selected and completed under
the explicitly bounded local synthetic development scope, enabling full developer-loop
file ingestion, quarantine staging, antimalware/active-content inspection, and
preview generation without requiring host-level external dependencies (clamscan/Docker).
Explicitly synthetic; does not grant production/M0 signoff or replace institutional authority.

## Task5 Local synthetic file foundations (24November2026)

Components implemented:
- LocalSyntheticFileSecurityScanner (app/Infrastructure/Documents/LocalSyntheticFileSecurityScanner.php):
  Implements FileSecurityScanner and AttemptCorrelatedFileSecurityScanner. Enforces magic-byte
  verification (JPEG, PNG, PDF), EICAR/synthetic malware detection, active content safety
  (/JavaScript, /Launch, /EmbeddedFiles, script tags), attempt-nonce correlation, and
  deterministic fail-closed fault simulation.
- SyntheticDocumentWorkflow (app/Domain/Documents/SyntheticDocumentWorkflow.php):
  Coordinates full quarantine staging, security scanning via QuarantineInspectionService,
  and preview generation via DocumentPreviewService with separate previewObjectId and
  original byte immutability. Rejects oversized/unsupported/unsafe files fail-closed.
- SyntheticDocumentClearanceResult (app/Domain/Documents/SyntheticDocumentClearanceResult.php):
  Immutable clearance result DTO with canonical SHA-256 fingerprint.
- Decision record: docs/proposals/fnd06_synthetic_file_foundation_v1.md.

Verification:
- Dedicated tests: 17 tests / 44 assertions GREEN in LocalSyntheticFileSecurityScannerTest
  and SyntheticDocumentWorkflowTest. Pint formatting passed.
- Full strict candidate verifier: 779 tests / 4,964 assertions GREEN (2m 14s),
  Vite 7.3.8 build 35 modules in 907ms passed, zero skipped/risky/warnings/deprecations.
  Selected source inputs unchanged; deployable=false. Evidence recorded at
  storage/app/private/local-verification/0b3649d71c026a60358acf1cd185bd9e/.
- Protected files (UserKind.php, M0MongoLiveIntegrationTest.php) verified untouched.
- All prior uncommitted work preserved; no commits, pushes, or migrations executed.

## Task6 Release / M0 candidate acceptance verification packet (24November2026)

Owner authorized proceeding with the next plan. Task 6 completed by formalizing the
unified M0 candidate acceptance verification packet and auditing all foundation subsystems:
- docs/proposals/m0_candidate_acceptance_packet_v1.md:
  Documents full M0 milestone audit matrix (FND-01 through FND-07), operational readiness
  blocker analysis from OperationalReadinessInspector, immutable reference hashes (composer.lock,
  package-lock.json, collection_catalogue.json, identity-v1.openapi.json, protected files),
  catalogue index counts (128 catalogue / 190 physical), and candidate acceptance sequence.
- M0CandidateAcceptancePacketTest (tests/Unit/M0CandidateAcceptancePacketTest.php):
  Validates packet existence, structural completeness, assertion of profile=strict-ci-candidate
  and deployable=false, exact documentation of all 8 operational readiness blockers, and
  cryptographic verification of disk hashes against documented references. 4 tests / 31 assertions GREEN.
- Full strict candidate verifier: 783 tests / 4,995 assertions GREEN (2m 05s), Vite 35 modules in 922ms,
  deterministic inputs unchanged; deployable=false. Evidence recorded at
  storage/app/private/local-verification/836fb11eafa8914d9d3e72ccc801c129/.
- Protected files (UserKind.php, M0MongoLiveIntegrationTest.php) verified untouched.
- All prior uncommitted work preserved; no commits, pushes, or migrations executed.

## Task7 Connected synthetic profiling & questionnaire workflows in UI (24November2026)

Owner authorized proceeding to the next step. Task 7 completed by synchronizing client-side
questionnaire progress and connecting the interactive FND-06 file security pipeline simulation:
- resources/js/preview/ (PreviewApp.tsx, QuestionnaireScreen.tsx, DashboardScreen.tsx, RequirementsScreen.tsx, types.ts, workspace.css):
  - State Synchronization: Questionnaire answers (preference, goal, note, step, completion)
    are maintained in component memory and shared with the Dashboard.
  - Dashboard Indicators: Questionnaire progress card reflects real-time completion
    (0% -> 25% -> 50% -> 100%), dynamic step counts (0/3 to 3/3 complete), and task row pills
    (Not started -> In progress -> Completed).
  - FND-06 Document Clearance Pipeline Simulation: In RequirementsScreen, an interactive simulation
    demonstrates the FND-06 file foundation in pending mode (clean JPEG scan yielding distinct
    previewObjectId and untouched quarantine original vs unsafe scan yielding quarantine isolation
    and fail-closed alerts).
  - Invariants Preserved: In-memory state only; zero server network requests or storage writes;
    conspicuous synthetic labeling; disabled official submission actions.
- Decision record: docs/proposals/ui_connected_workflows_v1.md.
- TypeScript check: npx tsc --noEmit passed cleanly (0 errors).
- Full strict candidate verifier: 783 tests / 4,996 assertions GREEN (1m 47s), Vite 35 modules in 758ms,
  deterministic inputs unchanged; deployable=false. Evidence recorded at
  storage/app/private/local-verification/4cfa0418b16f2aac9d146a17a3827901/.
- Protected files (UserKind.php, M0MongoLiveIntegrationTest.php) verified untouched.
- All prior uncommitted work preserved; no commits, pushes, or migrations executed.

Next: Review complete local synthetic M0 foundation baseline with owner; await instructions on commit authorization or hosted CI evaluation.

## M0-07 certificate slot concurrency (27 November 2026)

- Added a service-level test that coordinates two PHP workers on the same synthetic
  student-year slot in the MongoDB test replica set. Both read the initial generation
  and reach a barrier immediately before the write. When released, one binds its
  certificate and one reports a generation conflict. The test reads back one slot
  with the winner's certificate and a single generation increment.
- The focused suite passed 7 tests and 38 assertions. Pint passed for both test files.
  The full strict PHPUnit stage passed 790 tests and 5,033 assertions.
- The default Vite config loader hit a Windows sandbox access error. The separate
  `npm.cmd run build -- --configLoader native` run built 35 modules. All 323 recorded
  source inputs matched the pre-test manifest. The candidate remains `deployable=false`.
  Evidence is under `storage/app/private/local-verification/2ee5136825f141976b6e043c41b69865/`.
- The manager and protected integration files retained their original hashes. No
  commit, push, hosted CI run, or runtime activation was performed.

## Parallel M0 closeout assignments

- **Codex owns M0-04/05.** Extend the synthetic identity HTTP test harness and
  verify that CSRF, authentication, authorization, and throttle rejections leave
  account and session state unchanged. Record the focused test results here.
- **Message for Claude in Antigravity.** While Codex works on M0-04/05, take
  M0-06 and M0-08 verification. For M0-06, inspect the existing scan-journal
  worker and test. Return a review-ready test proposal or patch in your chat
  with the coordination point and assertions for journal head, audit records,
  and clearance. For M0-08, reconcile the packet against the original eight
  milestones and the November 27 test and build logs. Return exact commands,
  evidence paths, stale claims, and remaining acceptance gaps.
- Work from the workspace in read-only mode while Codex is writing. Deliver
  patch drafts and findings in Claude chat. Codex will integrate reviewed
  changes into the existing files. Use synthetic inputs and share evidence
  paths and results. Preserve protected files and existing owner checkpoints.

### M0-04/05 focused verification

- Added four rejection cases to `SanctumFencedSessionActivationLabTest` using
  the real Sanctum middleware and a test-only current-session route. Each
  checks the scoped Problem response, zero calls to the revocation service,
  unchanged account and role grants, unchanged durable session records, and
  unchanged session identity, payload and revocation state.
- The established-session cases capture the payload after an accepted read.
  The initial test reproduced Sanctum adding its password-integrity marker
  after a first authenticated 403 or 429. Transport lease and revision
  housekeeping and authorization audit writes are outside the unchanged
  business-state assertion. A literal zero-write policy for rejected first
  requests remains an open runtime-composition decision.
- Test-target preflight and Pint passed. The focused Sanctum, authorization,
  Problem integration/boundary, and identity contract suites passed 40 tests
  and 695 assertions with `--fail-on-all-issues`.

### Next parallel assignment

- **Codex takes M0-06 race integration.** Review the existing
  `ScanJournalRaceTest.php` draft and extend the two-process journal test to
  hold the latest completion before commit while the older completion is
  rejected. Verify the head, history, audit, and outbox before and after commit.
- **Message for Claude in Antigravity.** Prepare the M0-08 candidate evidence
  reconciliation in parallel. Draft the exact edits to the acceptance packet
  mapping M0-01 through M0-08 to evidence and open dependencies. Check the
  November 27 strict PHPUnit log, native Vite build log, prior hosted result,
  and focused M0-04/05 report. Distinguish each run's scope and the remaining
  institutional decisions. Return the proposed diff and discrepancies in
  Claude chat. Use the shared workspace read-only while Codex is editing.

### M0-06 journal overlap verification

- The two-process test holds the latest unsafe completion after staging its
  MongoDB writes and before commit. The older worker attempts a clean
  completion during that hold and is rejected. Until release, an independent
  read sees the latest pending head, two history records, two audit records,
  and zero outbox records. After commit, it sees the unsafe head at generation
  two with one additional history record, audit record, and outbox record.
- Corrected the separate draft to test rejection of an unadmitted nonce
  after a valid completion. The overlap test covers rejection at the stale
  nonce guard. Competing CAS operations against the same current generation
  and operational storage/scanner acceptance remain outside this evidence.
- Test-target preflight passed. The scan-journal and synthetic document
  suites passed 24 tests and 65 assertions with `--fail-on-all-issues`.
  The overlap test passed again on its own with 17 assertions.

### M0-08 candidate reconciliation and Claude progress report

- **Codex takes M0-08 evidence reconciliation.** Rerun verification on the
  current local tree and update the acceptance packet with the eight
  original milestones, evidence scope, and open acceptance decisions.
- **Message for Claude in Antigravity.** Independently review the current
  M0 progress against M0-01 through M0-08. Start your report with a yes or
  no answer to "Is M0 fully complete?" Cite what is verified locally and
  in hosted CI, and list the work or approvals that remain. Distinguish
  synthetic engineering evidence from institutional signoff. Check the
  canonical handoff and evidence available when you report. Send the
  user a concise progress summary in Claude chat and flag discrepancies
  for Codex. Use the shared workspace read-only while Codex edits.

### M0-08 reconciliation result

- The acceptance packet now maps M0-01 through M0-08 to engineering evidence
  and remaining acceptance work, and records the exact verification commands.
- The current local strict PHPUnit stage passed 795 tests and 5,117 assertions.
  The default Vite loader hit the Windows sandbox access error. The separate
  native-loader build passed, and the 324 selected source inputs matched.
  Evidence is under `storage/app/private/local-verification/d6e5f888a914a42c652709514090512d/`.
- The readiness check returned `blocked`, `activationAllowed=false`, and
  eight blockers. The available hosted pass covers the earlier `578e1fd`
  baseline. Hosted verification of the reviewed candidate, signed release
  evidence, and written institutional acceptance remain open. M0 is incomplete.

### Claude packet integration and next assignments

- Integrated Claude's M0-08 evidence proposal into the existing packet.
  The reconciled baseline is **790 tests / 5,033 assertions GREEN** at
  `storage/app/private/local-verification/2ee5136825f141976b6e043c41b69865/`.
  The later full local run is **795 tests / 5,117 assertions GREEN** at
  `storage/app/private/local-verification/d6e5f888a914a42c652709514090512d/`.
  Both figures describe strict PHPUnit stages. The separate native Vite
  builds and earlier hosted run have their own evidence in the packet.
- Consolidated the M0-06 overlap and unadmitted-nonce checks in
  `tests/Feature/ScanJournalRaceTest.php`. The overlap case now checks the
  pending and completed head sequence, completion flag, and corresponding
  record counts. The race, journal, document workflow, and candidate packet
  suites passed 28 tests and 100 assertions with `--fail-on-all-issues`.
- **Next task for Codex.** Investigate the M0-05 first-authenticated-request
  session marker. Use the synthetic Sanctum harness to document the exact
  persistence behavior and exercise a test-only composition option that
  preserves missing-marker and password-change handling. Keep runtime
  identity routes behind their approval checkpoints.
- **Next task for Claude in Antigravity.** Review M0-08 release readiness
  independently. Check the packet against both local logs, the earlier
  hosted run, and the original milestone plan. Identify the evidence needed
  for candidate freeze, hosted verification, SBOM and provenance, signing,
  and institutional signoff. Report the outcome of this task, summarize
  overall progress across M0-01 through M0-08, and answer **Is M0 fully
  complete? Yes or no.** Cite evidence and name each remaining decision or
  owner where documented. Use the shared workspace read-only and report in
  Claude chat.

### Parallel review for Claude in Antigravity

- Review the M0-06 scan-journal and file worker evidence against the original
  plan and ADRs. Check what the two-worker test proves while the journal
  commit is held, and identify the remaining same-generation CAS,
  scanner, storage, and restore proofs. Draft the smallest next test or
  acceptance proof that would close a documented gap.
- Report your review in Claude chat, then summarize overall progress by
  M0-01 through M0-08. Lead with a yes or no answer to **Is M0 fully
  complete?** Cite the current handoff and saved local and hosted results.
  List remaining engineering work and operational blockers with documented
  owners or decisions. Use the shared workspace read-only during Codex's
  M0-05 test work. Use synthetic evidence and preserve owner checkpoints.

### M0-05 first-authenticated-request marker verification

- Reproduced Sanctum's native behavior for the first authenticated 403 and
  429. The only protected business-state delta was the addition of
  `password_hash_web` to the session payload. The scoped Problem response
  and zero revocation calls were verified for both statuses.
- Exercised an opt-in test composition around the existing middleware.
  It discards a newly added marker after a rejected response when the marker
  was missing at entry. A later successful request initializes the marker.
  A synthetic credential rotation with an established marker still triggers
  native authentication rejection and clears the guard identity and marker.
- The Laravel test client reuses the application between requests.
  Restoring the default web guard before the follow-up read keeps the
  fixture independent of the prior route's guard selection.
- The focused strict run passed 45 tests and 787 assertions. See
  `storage/app/private/m0-05-first-request-753c353871134a9386d505266423e0bb/`
  for `preflight.log` and `focused-tests.log`. Pint passed. The option lives
  in the synthetic PHPUnit harness.
- Transport bookkeeping and authorization auditing remain active. The
  runtime missing-marker policy and the operational session and recovery
  proofs still require acceptance.
- Latest full local baseline on 27 November: **800 tests / 5,214 assertions
  GREEN** in the strict PHPUnit stage (1m 50.179s). The default Vite loader
  hit the Windows sandbox access error. The separate
  `npm.cmd run build -- --configLoader native` build passed with 35 modules
  in 1.06s. All 324 selected inputs matched the pre-test manifest.
- Full verification evidence is under
  `storage/app/private/local-verification/9e1e6c86b288363b9b7cac7fd5322562/`.
  Files include `tests.log`, `build.log`, `build-native.log`,
  `source-inputs-before.json`, and `source-inputs-after-native-build.json`.
- Readiness remains `blocked` with eight blockers and
  `activationAllowed=false`. The hosted pass still covers the earlier
  `578e1fd` baseline. M0 awaits hosted verification of the reviewed
  candidate, signed release evidence, and written institutional acceptance.

### Next parallel assignments for M0 closeout

- **Codex takes the M0-06 same-generation journal collision.** Use the same
  admitted nonce and committed head for two competing completions. Capture
  evidence that MongoDB received a competing compare-and-swap while the first
  transaction held the head, then verify exactly one completion and outbox
  record. Keep the stale-nonce test as a separate proof.
- **Claude in Antigravity takes the operational acceptance evidence review.**
  Inspect the readiness inspector, acceptance packet, original M0 plan, and
  saved verification evidence. For each operational blocker, list the
  documented acceptance decision, required proof, and responsible approver
  where explicitly recorded. Flag missing ownership as open. Report the
  results, summarize progress across M0-01 through M0-08, and start with a yes
  or no answer to **Is M0 fully complete?** Cite the latest local and hosted
  evidence and distinguish engineering evidence from written acceptance.
  Use the shared workspace read-only while Codex edits.

### M0-06 same-generation journal collision verification

- Both completions use one pre-admitted nonce at generation one. The
  unsafe worker stages the head, history, audit, and outbox changes and
  pauses before commit. The competing clean completion issues the head
  update with the original hash. The MongoDB subscriber releases the held
  worker only after the matching update returns write-conflict code 112.
- After commit, the unsafe head is complete at generation one and sequence
  two. There are one head, two history records, two audit records, and one
  outbox record. The clean completion is rejected. The existing stale-nonce
  overlap and unadmitted-nonce cases remain separate checks.
- Claude's draft used distinct nonces. Each admission advances the generation,
  so that setup exercises stale-nonce rejection. The implemented test uses
  the same pending head to reach the compare-and-swap collision itself.
- The related strict run passed 29 tests and 112 assertions. Test-target
  preflight and Pint passed. See `preflight.log` and `focused-tests.log` under
  `storage/app/private/m0-06-same-generation-9f7be32fb2cd4b5e93e9c3fbb6812620/`.
- Latest local baseline on 27 November: **801 tests / 5,226 assertions
  GREEN** in the strict PHPUnit stage (1m 41.203s). The default Vite loader
  hit the Windows sandbox access error. The separate native-loader build
  passed with 35 modules in 786 ms. All 324 selected source hashes matched.
- Full evidence is under
  `storage/app/private/local-verification/3badccfcbe742eca627f4e2f5e7fd5b6/`.
  See `tests.log`, `build.log`, `build-native.log`,
  `source-inputs-before.json`, and `source-inputs-after-native-build.json`.
- Readiness remains `blocked` with eight operational blockers and
  `activationAllowed=false`. Hosted candidate verification, signed release
  evidence, and written institutional acceptance remain open.

**Next Codex assignment.** Prepare the M0-08 candidate source review on the
current working tree. Reconcile the packet against the latest full local
evidence and identify the exact changes and unresolved review decisions
for the owner before an authorized candidate freeze and hosted run.

### M0-08 source review and parallel Claude assignment

- **Codex owns the candidate source review.** Inventory the local tracked
  and untracked paths against HEAD, match the current selected-source
  manifest to the full test and build evidence, and record unresolved
  decisions for the owner.
- **Claude in Antigravity owns the independent freeze and acceptance review.**
  Inspect the candidate acceptance packet, Git status, saved local
  verification logs, and prior hosted run metadata. Check the proposed
  candidate scope and identify any omitted or unverified inputs. Verify
  which of the eight readiness blockers have documented acceptance or
  approvers. Report in Claude chat with a yes or no answer to **Is M0 fully
  complete?**, milestone progress, and separate local and hosted evidence.
  Read the shared workspace without editing while Codex prepares the review.

### M0-08 candidate source review result

- Reviewed the current tree against `d4b3086b686e3fbb3402da004574ac96b02da7b4`.
  The local `origin/master` reference points at the same commit. The review
  inventory contains 15 modified tracked files, 37 untracked files and
  zero staged changes. Of the 52 changed paths, 40 are covered by the
  selected-source manifest and 12 documentation paths have separate
  SHA-256 hashes in the inventory.
- Reconciled the acceptance packet with the 27 November **801 tests /
  5,226 assertions** strict PHPUnit baseline, the default Vite sandbox
  error, and the successful native-loader build. The current 324-input
  manifest matches both saved manifests at digest
  `0f24d19664b2e1c375563782ea66965d9233985afdd11e9fd40827d1102012a9`.
  The full run logs and manifests are under
  `storage/app/private/local-verification/3badccfcbe742eca627f4e2f5e7fd5b6/`.
  The updated packet passed 4 focused tests and 31 assertions.
- Review evidence is under
  `storage/app/private/m0-08-source-review-0e566e87b02241e684f069cd2a496eed/`.
  `candidate-inventory.json` lists exact paths, statuses, hashes, and
  coverage. `current-source-inputs.json`, `route-summary.json`,
  `readiness-summary.json`, and `packet-tests.log` capture the review checks.
  The route snapshot shows the root, health, Sanctum CSRF, and framework
  storage routes. Identity business route activation remains gated.
- The owner review covers the listed candidate scope, including the
  checkout pin change and synthetic React preview. The candidate commit,
  push, and manual hosted run await authorization. The latest recorded
  hosted pass remains run 45095083400 on `578e1fd`.
- The fresh readiness check returned `blocked`, eight operational blockers,
  and `activationAllowed=false`. The immutable runtime and artifact scope,
  SBOM, signing/provenance authority, retention, and institutional signoff
  remain open. M0 is incomplete.
