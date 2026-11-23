# Studentity canonical development handoff

Updated23November2026 (Asia/Shanghai). Repository master, starting HEADcc122c8.
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

1. Review/run the authorized manual synthetic CI candidate; record exact source, runner,
   runtime versions, dependency installation, fresh catalogue, tests/build and failure
   evidence. Fresh guard must reject existing state; never reset/repair it.
   No deployment, artifact publishing or signing added. Artifact/SBOM/exact provenance,
   signing identity/key custody and institutional M0 acceptance remain separate gates.
2. FND07: approved API scope/DTO and real middleware/log-channel/reporting/finalizer/host
   lifecycle review before binding. Factory/handler/outer boundary are unregistered.
   Registration department/year, nullable nonstudent SelfUser SystemID, CSRF419 and
   login/logout200 corrections remain PROPOSED; authoritative OpenAPI unchanged.
3. FND06: real authentication/current role/program/record/sensitivity authorization,
   independent recovery, private storage/ACL/encryption/scanner/proxy/audit acceptance.
   Lab clean status/preview reference is not delivery permission or academic authenticity.

## Foundations to preserve

Registration: P0 G05/G09 provenance, authority-first permanent IDs and purpose/recipient/
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
