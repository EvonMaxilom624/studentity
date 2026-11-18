# Studentity — Unified Development Handoff

**Updated:** 2026-11-18
**Repository:** C:\Users\Evon\Downloads\Profiling\studentity  
**Branch:** master (owner-selected coding branch)  
**Baseline commit:** ca22296

## One maintained workstream

This is the single maintained development handoff. It combines the Gemini-provided
identity handout, the ChatGPT Laravel 13/FND-03 handoff, and Codex's repository
inspection. Update this document as work advances. Do not maintain competing
handoffs, duplicate roadmaps, or separate assistant branches for this same work.

Use master directly for coding in this checkout. Do not create feature branches,
duplicate repositories, or isolated worktrees unless the owner explicitly requests
a different workflow. On 2026-11-17, master and chore/laravel-13-alignment both
pointed to ca22296. The checkout was switched to master and the redundant alignment
branch was deleted with Git's safe branch deletion; no merge or commit was needed.
All existing uncommitted work was retained. No remote is currently configured.

Follow one sequence in this repository:

**Inspect → approve upgrade → Laravel 13 alignment → verify and review results
→ approve FND-03 → implement the approved foundation slice.**

**Current position:** After reviewing the upgrade report, the owner instructed
Codex to proceed with the next development plan. Upgrade acceptance is recorded;
the FND-03 synthetic authorization/session foundation is implemented on master
and was accepted for the next schema-reconciliation/design slice. The latest full
suite passes 281 tests / 3,028 assertions after page recovery/encrypted rollback verification.
The PHP development-server runtime is
corrected for this workspace. Registration and production authority configuration
remain excluded.
The activation-readiness decision and credential/MFA/IAM prerequisite plan below
are prepared for owner review; no operational or institutional gate is waived.

Execution record: the fresh Laravel 12 baseline passed 10 tests / 12 assertions,
including all three live MongoDB tests, and Vite 7.3.8 built successfully. The
compatible Composer dry-run and installation both produced 22 updates and two
PHPUnit dependency removals. Installed versions are Laravel 13.42.0, Tinker 3.1.0,
and PHPUnit 12.5.44. The upgraded suite also passed 10 tests / 12 assertions,
and the post-upgrade Vite build passed.
Snapshots and command evidence are in the ignored directory
storage/app/private/laravel13-upgrade-2026-11-17/. Composer application scripts were
suppressed during dependency installation; optimized autoload generation completed,
then the existing post-autoload-dump script was explicitly run to clear compiled
bootstrap files and discover packages. No setup, migration, or asset overwrite ran.

## Authority and constraints

- Studentity SRS v1.1 Final Consolidated is authoritative. Preserve it, Blueprint
  v0.1, and the existing architecture documents without modification.
- ADR-01/ADR-02 select PHP 8.4, Laravel 13 modular monolith, React/TypeScript/Vite,
  same-origin versioned JSON API, and official MongoDB integration.
- Use synthetic data only. Do not expose student information, credentials,
  secrets, or institutional records in code, tests, prompts, or logs.
- No destructive MongoDB operations, irreversible migrations, cloud provisioning,
  or deployments. No institutional production approval is implied.
- Preserve immutable histories and one certificate slot per student/year.
  Restoring a backup must not silently restore revoked privileges.
- Keep OpenAPI behavior compatible or document deviations for review.
- Do not enable student, administrator, upload, certificate, or public verification
  business endpoints before the applicable security gates pass.
- Never force dependency resolution or ignore platform requirements.
- Native local MongoDB is sufficient; Docker is not required.

## Protected existing work

| Location | Inspected state | Handling |
|---|---|---|
| tests/Feature/M0MongoLiveIntegrationTest.php | Modified; 84 added lines | Preserve |
| app/Domain/Identity/ | Untracked; contains UserKind.php | Preserve entire directory |
| docs/ | Untracked handoffs | Maintain this unified document; no automatic commit |

Do not delete, overwrite, reset, stash, stage, or commit the protected test or
Identity directory without explicit approval. Continue on master; do not recreate
the retired alignment branch.

Inspection SHA-256 values:

    tests/Feature/M0MongoLiveIntegrationTest.php
    5D54B0911AB0CCE2BD8D5EE6F4CE980A9BE7539BFE531DE5A2A0EC2281BC19A0

    app/Domain/Identity/UserKind.php
    A79AE473FEE5482E5DFFB0EEF81E3F4BD4C350EA85F2B7AB0D99884CC065BED1

Before implementation, recheck status and hashes. If they differ, inspect and
report the newer work; do not replace it with earlier contents.

Approved handling used: protected work was left in place, pre-upgrade dependency
files were snapshotted, and runtime source changes were limited to composer.json
and composer.lock. Protected-file hashes remained unchanged after the upgrade.
No files were staged or committed.

## Verified environment — 2026-11-17, after upgrade

| Component | Installed / locked version | Proposed action |
|---|---|---|
| PHP target runtime | 8.4.27 NTS x64 at C:\tools\php84\php.exe | Use for Composer, Artisan, and tests |
| Inherited bare php | XAMPP 8.2.12 at C:\xampp\php\php.exe | Correct session PATH |
| Composer | 2.9.5 | Retain |
| Laravel | 13.42.0 | Aligned; requirement ^13.0 |
| Tinker | 3.1.0 | Aligned; requirement ^3.0 |
| Sanctum | 4.4.0 | Added during FND-03; requirement ^4.4; cookie authentication only |
| MongoDB Laravel integration | 5.12.0; exact root pin 5.12 | Retain; explicitly supports Illuminate 13 |
| MongoDB PHP library | 2.5.1 | Retain unless reviewed resolution requires otherwise |
| MongoDB PHP extension | 2.5.7 on PHP 8.4; 2.5.2 on XAMPP | Use PHP 8.4 extension |
| PHPUnit | 12.5.44 | Aligned; requirement ^12.0 |
| Collision | 8.9.6 | Current constraints permit Laravel 13 / PHPUnit 12 |
| Pail / Pint / Sail | 1.3.0 / 1.30.4 / 1.70.0 | No root constraint change identified |
| MongoDB server | 8.3.7; local rs0 writable PRIMARY | Retain |
| Node / npm | 24.13.0 / 11.6.2 | Retain |
| Vite | 7.3.8 installed and locked | Retain |

### PHP / Composer diagnosis

The actual launcher C:\ProgramData\ComposerSetup\bin\composer.bat invokes bare php.
The inherited PATH selects XAMPP. No platform.php override is configured.

This session-only adjustment was verified successfully:

    Set-Location 'C:\Users\Evon\Downloads\Profiling\studentity'
    $env:Path = 'C:\tools\php84;' + $env:Path
    php -v
    composer --version
    composer show --platform | Select-String '^php\s|^ext-mongodb\s'

Repeat runtime selection in every fresh shell/tool subprocess. No permanent PATH
change or XAMPP modification is needed. Under PHP 8.4, Composer identifies the root
Laravel ^12.0 constraint and Tinker 2.11.1's Illuminate constraints as the original
blockers. These constraints have now been updated and the reviewed solution
installed. The project now requires PHP ^8.4, enforcing the selected runtime.

### Configuration, schema, and evidence

- Actual MongoDB keys are MONGODB_URI and MONGODB_DATABASE, not MONGO_URI.
- Local configuration selects studentity_dev_m0; .env.testing selects
  studentity_test_m0 for the explicit MongoDB connection.
- phpunit.xml selects in-memory SQLite as the default connection. Existing live
  tests explicitly read the MongoDB configuration. Verify effective test settings.
- Both synthetic databases have 62 validated application collections plus the
  migration ledger, with one recorded migration.
- Read-only comparison found no differences for all 62 validators and 128 distinct
  catalogued index definitions per database, including strict/error validation.
- Both databases have the compound unique certificate student/year index and the
  partial unique active-system_admin role index.
- Local sessions use files; test sessions use arrays. Neither proves production
  revocation/recovery readiness.
- User.php remains an unused legacy SQL scaffold. The active provider now selects
  AuthenticatedUser (MongoDB); Sanctum is installed, but login/registration and
  business endpoints remain unexposed. The added public primitive is Sanctum's
  standard GET /sanctum/csrf-cookie.
- The preserved concurrency test catches general exceptions. A pass alone does not
  prove the intended conflict cause. Editing that protected test requires approval.
- Independently run: Artisan reports Laravel 13.42.0; Composer validation exits 0
  with a warning about the deliberate exact MongoDB integration pin; platform
  requirements pass under PHP 8.4.
- The previously owner-supplied baseline was independently rerun before upgrading:
  Laravel 12 passed 10 tests / 12 assertions and Vite built. After upgrading,
  Laravel 13 passed the same 10 tests / 12 assertions and Vite built again.
  All three live MongoDB tests ran; no tests were skipped or failed.

## Single execution checklist

### 1. Inspect — complete

- [x] Read both handoffs, AGENTS.md, relevant code, configuration, and lockfiles.
- [x] Inspect branch, protected work, dependencies, and PHP/Composer launchers.
- [x] Diagnose and verify session-only PHP 8.4 selection.
- [x] Review official Laravel guidance and actual MongoDB package compatibility.
- [x] Inspect synthetic replica-set/schema metadata without database writes.
- [x] Report findings and proposed changes before implementation.

### 2. Laravel 13 alignment — implemented

- [x] Obtain approval for the scoped upgrade and protected-working-tree handling.
- [x] Confirm master is active; recheck status, protected hashes, PHP, and Composer.
- [x] Snapshot pre-upgrade composer.json and composer.lock outside tracked source
  files; record the snapshot location for rollback.
- [x] Establish fresh Laravel 12 test/build baseline. Ensure live tests target
  exactly studentity_test_m0; allow only synthetic fixtures and their existing
  narrowly scoped cleanup. Never run broad deletion commands.
- [x] Initially change only these root requirements:

  | Dependency | Existing requirement | Proposed requirement |
  |---|---|---|
  | php | ^8.2 | ^8.4 |
  | laravel/framework | ^12.0 | ^13.0 |
  | laravel/tinker | ^2.10.1 | ^3.0 |
  | phpunit/phpunit | ^11.5.50 | ^12.0 |

- [x] Retain the MongoDB integration pin and npm lockfile; do not add absent Pest
  or Boost packages.
- [x] Run a targeted Composer dry-run with necessary dependency updates and minimal
  changes. Review the entire package delta and Composer script effects.
- [x] Follow the dependency-review checkpoint before applying the solution.
  Diagnose incompatible or unexpectedly broad changes instead of forcing them.
- [x] Apply the reviewed solution; regenerate a consistent composer.lock.
- [x] Review Laravel 13 request-origin checks and PreventRequestForgery middleware.
  No direct legacy middleware references were found in application code.
- [x] Review JSON session serialization/cache hardening explicitly. Do not silently
  invalidate sessions or synchronize unrelated skeleton configuration.
- [x] Keep the MongoDB physical model unchanged unless a reproducible issue needs
  a separately reviewed repair. Do not run composer setup, automatic migrations,
  or migration rollback as an upgrade shortcut.

### 3. Verify and review the upgrade

With PHP 8.4 selected, run each command and inspect its exit code/output:

    php -v
    composer --version
    php artisan --version
    composer validate
    composer check-platform-reqs
    php artisan test
    npm.cmd run build
    git status --short
    git diff --stat

- [x] Confirm Laravel 13 boot and record exact resolved package/extension versions.
- [x] Report tests, assertions, skips, failures, and actual live MongoDB target.
- [x] Exercise compound uniqueness, transaction rollback, and existing concurrency
  tests against the synthetic test replica set.
- [x] Repeat read-only validator/index/migration-ledger checks in both synthetic
  databases. Do not reapply migrations merely to inspect their state.
- [x] Compare protected hashes and inspect every changed file.
- [x] Report changed files, dependency delta, actual verification evidence,
  limitations, remaining issues, rollback instructions, and next action.
- [x] Obtain owner acceptance of the upgrade results before FND-03 implementation.

**Rollback:** Diagnose failed gates before advancing. Restore only recorded
upgrade-file snapshots, then install their locked dependencies under PHP 8.4.
Do not reset the repository, restore protected files, or reverse database migrations.

### Upgrade result and evidence

This execution changed composer.json (four requirement constraints), composer.lock
(the reviewed resolution), and this handoff (progress/results/next plan). AGENTS.md,
the protected test changes, and the untracked Identity directory already existed
before the upgrade. Generated vendor, bootstrap cache, and Vite output are ignored.
phpunit.xml, package-lock.json, application source, routes, and MongoDB schema files
were not changed. No migrations, dependency force flags, or platform bypasses ran.

| Validation | Actual result |
|---|---|
| PHP / Composer | PHP 8.4.27; Composer 2.9.5 uses C:\tools\php84\php.exe |
| Framework / test runner | Laravel 13.42.0; PHPUnit 12.5.44 |
| Composer validation / platform | Pass; deliberate exact MongoDB pin warning remains |
| Installed/lock consistency | All 113 installed packages match the resolved lockfile; PHP requirement ^8.4; no platform override |
| Dependency security check during update | No vulnerability advisories found |
| Before-upgrade suite | 10 passed / 12 assertions; 0 failures or skips |
| After-upgrade suite | 10 passed / 12 assertions; 0 failures or skips |
| Live tests | Compound uniqueness, explicit transaction abort, concurrent issuance all passed in studentity_test_m0 |
| Vite baseline and upgraded build | Both pass; Vite 7.3.8, 25 modules; identical asset filename |
| MongoDB metadata | 8.3.7; rs0 PRIMARY; both synthetic DBs have 62 validated collections plus migration ledger |
| Post-upgrade schema comparison | 62 validators / 128 distinct catalogue indexes per database match; one M0 migration remains recorded |
| Existing data-integrity controls | Certificate student/year compound unique and active-admin partial unique indexes present |
| File preservation | Protected file hashes match; SRS, Blueprint, and accepted ADR hashes unchanged during verification |
| Request-forgery wiring | Runtime web middleware group contains Laravel 13 PreventRequestForgery; mutation negative tests remain FND-03 work |

The only baseline build failure was a filesystem sandbox denial while esbuild
resolved vite.config.ts. The unchanged build command passed with approved access;
the post-upgrade build used that same access. No frontend repair was necessary.

Installed dependency delta (all other locked versions retained):

| Package | Before | After |
|---|---|---|
| laravel/framework | 12.72.0 | 13.42.0 |
| laravel/tinker | 2.11.1 | 3.1.0 |
| phpunit/phpunit | 11.5.58 | 12.5.44 |
| phpunit/php-code-coverage | 11.0.12 | 12.5.8 |
| phpunit/php-file-iterator | 5.1.1 | 6.0.2 |
| phpunit/php-invoker | 5.0.1 | 6.0.0 |
| phpunit/php-text-template | 4.0.1 | 5.0.1 |
| phpunit/php-timer | 7.0.1 | 8.0.0 |
| sebastian/cli-parser | 3.0.2 | 4.2.1 |
| sebastian/comparator | 6.3.3 | 7.1.8 |
| sebastian/complexity | 4.0.1 | 5.0.0 |
| sebastian/diff | 6.0.2 | 7.0.1 |
| sebastian/environment | 7.2.1 | 8.1.2 |
| sebastian/exporter | 6.3.2 | 7.0.3 |
| sebastian/global-state | 7.0.2 | 8.0.3 |
| sebastian/lines-of-code | 3.0.1 | 4.0.1 |
| sebastian/object-enumerator | 6.0.1 | 7.0.0 |
| sebastian/object-reflector | 4.0.1 | 5.0.0 |
| sebastian/recursion-context | 6.0.3 | 7.0.1 |
| sebastian/type | 5.1.3 | 6.0.4 |
| sebastian/version | 5.0.2 | 6.0.2 |
| theseer/tokenizer | 1.3.1 | 2.0.1 |
| sebastian/code-unit | 3.0.3 | Removed; no longer needed by PHPUnit |
| sebastian/code-unit-reverse-lookup | 4.0.1 | Removed; no longer needed by PHPUnit |

MongoDB integration 5.12.0, PHP library 2.5.1, extension 2.5.7, and all Symfony
package versions were retained. There were no new package installations.

Execution decisions and remaining limits:

- Ruling: Use the owner's master checkout and this single handoff as the progress
  record; do not create feature branches or a second maintained plan. The cost is
  that approved changes remain together in a dirty working tree until reviewed.
- Ruling: Suppress Composer automatic scripts during installation, then explicitly
  run post-autoload-dump; this avoids setup/migration/asset overwrite side effects.
  Future package changes with required publishing must be reviewed separately.
- Ruling: Retain existing session serialization (Laravel's php fallback) and cache
  configuration during this dependency upgrade. JSON session serialization,
  explicit cache serialization hardening, cookies, and active CSRF negative tests
  belong in the approved FND-03 slice; current M0 health GET tests do not prove them.
- Known limitation: the protected concurrency test still catches general exceptions;
  its passing result is narrower evidence than asserting an expected conflict code.
  Tightening it remains subject to explicit approval for that file.
- Bare PHP still selects XAMPP in a fresh inherited shell. Always select PHP 8.4
  before Composer/Artisan; permanent machine configuration was not changed.

Final independent read-only review found no Critical, Important, or Minor upgrade
defects and judged the scoped change ready for owner acceptance. It inspected the
working dependency diff, all 113 installed/locked packages and source references,
saved verification evidence, protected/source hashes, and this report. It did not
rerun tests, alter the checkout, or perform database writes.

Review scope rulings: full FND-03/production-security readiness remains outside
this upgrade, so the passing M0 gates do not authorize business endpoints. The
inherited concurrency test's specific conflict-cause proof is also outside the
approved changes; its limitation is retained above, not treated as stronger proof.
No review defects or minor fixes were deferred.

Evidence directory: storage/app/private/laravel13-upgrade-2026-11-17/ (ignored,
local only). It contains composer.before.json, composer.before.lock,
before-hashes.json, source-document-hashes.json, baseline-tests.txt,
baseline-vite.txt, composer-dry-run.txt, composer-update.txt,
dependency-delta.json, package-discovery.txt, upgraded-tests.txt,
upgraded-vite.txt, upgraded-schema.json, and a read-only schema-inspection.php.
composer-validation.txt, platform-check.txt, and runtime-check.json record the
final dependency/platform/runtime gates.
Do not delete the dependency snapshots until the owner accepts the upgrade.

For rollback after review, copy composer.before.json to composer.json and
composer.before.lock to composer.lock, then run composer install under PHP 8.4
with automatic scripts suppressed and explicitly run post-autoload-dump. These
are application dependency restorations only; they do not touch the database,
protected work, or source requirements. Reverify after any rollback.

### 4. FND-03 — implemented synthetic foundation, ready for owner review

Execution contract: use MongoDB's existing validated collections with no migration;
keep all synthetic login/resource/logout routes inside tests only. Install Sanctum
4.4.0 (one addition, no locked package updates). Configure a MongoDB authentication
adapter, cookie-only Sanctum, JSON sessions and explicit cache hardening. Evaluate
current account/session/grant state with primary/majority reads and independent
authority evidence; do not enable a production grant or login workflow.

The default authority adapter always reports unavailable. Only a test-namespace
adapter can attest synthetic fixtures. Independent revocation is acknowledged
before the local revocation/audit transaction; uncertainty denies access and the
browser session is invalidated even when revocation cannot be confirmed. Missing
audit durability also denies protected access. Evidence/snapshots are retained in
storage/app/private/fnd03-2026-11-17/ (ignored). Domain policy tests were observed
failing before implementation, then passed; cookie/CSRF/MongoDB tests similarly
ran red before their implementation. No protected Identity or M0 test was edited.

#### FND-03 execution result

- [x] Snapshot touched tracked files and preserve protected M0/Identity hashes.
- [x] Run policy and feature tests red before implementing their behavior.
- [x] Add Sanctum 4.4.0; no other locked package updated or removed.
- [x] Configure MongoDB identity mapping, cookie-only Sanctum, JSON sessions,
  explicit cache hardening, secure production cookies and HttpOnly/SameSite rules.
- [x] Read current account/session/active grants on primary with majority concern;
  enforce role, Boolean permission, program, student ownership and staff MFA.
- [x] Require independent evidence matching subject, session digest, epoch and
  deterministic authorization fingerprint; unavailable/stale evidence fails closed.
- [x] Audit allow/deny decisions without raw session IDs, cookies or credentials;
  deny access when the audit cannot be persisted.
- [x] Anchor revocation first, then commit local session revocation and audit in
  one transaction; invalidate the browser even if revocation is unconfirmed.
- [x] Test cookie rehydration, fixation, replay, logout, stateful API origin rules,
  actual CSRF middleware, stale restored grants/sessions, MFA and audit outages.
- [x] Test competing admin transactions with specific conflict codes plus a
  committed-winner retry that must fail with duplicate-key code 11000.
- [x] Run scoped Pint, full tests, Vite, Composer validation/platform checks,
  schema comparison and route inventory without fixture-route leakage.
- [x] Final independent read-only review and any required fixes.
- [ ] Owner acceptance and approval of the next implementation slice.

Results: **56 tests passed / 181 assertions / zero failures or skips**. This adds
46 foundation cases to the ten-test M0 baseline: 25 policy cases, 20 live HTTP/
session/security cases, and one concurrent-admin case. Vite 7.3.8 builds; Composer
validation/platform checks pass with the existing deliberate exact MongoDB pin
warning. All 114 installed packages match the lock; all 113 pre-FND package records
are unchanged. Both synthetic DBs still match 62 validators / 128 distinct catalogue
indexes, with one M0 migration recorded. Protected files, phpunit.xml and
package-lock.json match pre-FND hashes. No migration, staging, or commit occurred.

Implemented file groups:

| Files | Responsibility |
|---|---|
| composer.json / composer.lock | Only add Sanctum 4.4.0 to the accepted Laravel 13 resolution |
| app/Models/AuthenticatedUser.php; config/auth.php | MongoDB identity adapter; preserve legacy User.php |
| config/sanctum.php; app/Providers/AppServiceProvider.php; bootstrap/app.php | Exact stateful hosts, cookie-only guard, bindings, stateful API middleware and generic 503 problem rendering |
| config/session.php; config/cache.php | JSON sessions, cookie flags, disable PHP-object cache deserialization |
| app/Domain/Security/{RoleGrant,AuthorizationContext,ResourceScope,AuthorityEvidence,SecurityStateAuthority,AccessDecision,AccessDecisionService,SecurityStateUnavailable}.php | Typed context/trust boundary and default-deny authorization; reuse existing AuthorizationRecoveryGate |
| app/Infrastructure/Security/{MongoAuthorizationRepository,AccessDecisionAuditRecorder,SessionRevocationService,UnavailableSecurityStateAuthority}.php | Current Mongo reads, audit events and transactionally recorded anchored revocation |
| app/Http/Middleware/EnforceCurrentAuthorization.php | Require trusted resolved resources; never accept query studentId as authority; record decisions and return contract-shaped 401/403/503 |
| app/Http/Middleware/AuthenticateSessionIdentity.php | Normalize identity-provider failures before Sanctum's eager stateful hydration; leave unrelated downstream HTTP failures unchanged |
| tests/Unit/Fnd03AccessDecisionTest.php; tests/Feature/Fnd03FoundationTest.php; tests/Feature/Fnd03ActiveAdminTest.php | Policy, cookie/security/integration and real uniqueness/concurrency evidence |
| tests/Support/InMemorySecurityStateAuthority.php | Test-only anchor; never registered in application code |

No file in the protected Identity directory or protected M0 test was edited.
All synthetic login/resource/logout handlers exist only in PHPUnit's in-memory
route table. No public login, registration, System ID allocator, credential
verifier, MFA enrollment, grant-management, or business API workflow was added.
The preexisting Laravel storage routes remain scaffold behavior, not new business
upload routes; the only added public route is Sanctum's GET CSRF-cookie primitive.

Execution decisions and remaining gates:

- Ruling: Default authority always returns unavailable. The test adapter must never
  be bound in application code. An approved provider must establish CURRENT trust,
  not replay old signed/cached authorization until expiry. Production authority
  hosting, authentication, recovery and shared-session backend remain unconfigured.
- Ruling: Independent revocation precedes local transaction/audit. If local commit
  fails, anchored revocation remains effective; operations need later reconciliation.
  If authority cannot acknowledge revocation, return 503 without claiming success;
  browser logout still runs. Never undo independent revocations during rollback.
- Ruling: Guard logout, cached-user clearing, session invalidation and CSRF rotation
  are attempted independently. A failing identity lookup during guard logout must
  never skip browser cleanup. Dependency exceptions become generic 503 responses;
  structured logs retain exception class only, not provider messages or credentials.
- Ruling: Future resource handlers must resolve ResourceScope from trusted storage.
  Client studentId and UI visibility are insufficient. Central decisions enforce
  MFA, so a duplicate RequirePrivilegedMfa middleware was not necessary.
- Ruling: JSON sessions intentionally invalidate prior synthetic PHP-serialized
  sessions. File sessions remain local synthetic-only; no production backend or
  institutional provider approval is implied by passing these tests.
- Laravel 13 origin/token semantics are preserved: same-origin fetch metadata may
  satisfy CSRF protection, and a valid token remains the fallback. Negative tests
  override only the unit-test bypass. Native rejection is 419; review/document this
  against the original OpenAPI before exposing mutation endpoints. New security
  401/403/503 problems include required code and correlationId without internal reasons.
- Event SHA-256 checks are per-event integrity hashes, not a signed immutable audit
  chain or independent audit anchor. Production auditing/recovery-run workflows
  remain separate gates. The protected M0 test's broad catch remains untouched.
- UUID/role schema contracts are reused; this is not proof that System ID allocation
  and nonrecycling behavior exists. IAM-01 notice/registration gates remain.
  Backlog correction: FND-02 is the original PostgreSQL schema/migration baseline;
  its implementation is interpreted under the accepted MongoDB ADRs, not as an
  identity-allocation story. Preserve the original backlog and record this mapping.

Independent review requested changes for two Important defects (no Critical/Minor
findings): Laravel guard logout could retry a failed identity lookup before session
cleanup, and Sanctum's early identity lookup could bypass the generic 503 boundary.
Both were verified against framework source and reproduced by two failing tests
in review-regressions-red.txt. Independent cleanup and a user-resolution boundary
fixed them; review-regressions-green.txt records both passing (19 assertions).
A third regression proves unrelated downstream 404 responses are not remapped.
The full suite then passed 56 tests / 181 assertions. No second reviewer run was
used; this is a fresh review followed by regression-verified fixes by the implementer.
No review finding was deferred.

Review scope rulings: production authority trust/recovery, shared-session deployment,
credentials/MFA enrollment, registration/business endpoints, independent immutable
audit anchoring, institutional approvals and preexisting scaffold routes remain
outside this synthetic slice. They remain explicit gates, not assumed approvals.

Current follow-on slice: schema reconciliation and the isolated shared-session
transport evaluation are complete within their documented limits. Next resolve
shared locking/logout-write coordination and the independent authority trust model,
while separately verifying identity allocation/nonrecycling prerequisites before
exposing real authentication. Do not start IAM-01
registration or configure a production provider merely because this proof passes.

#### Schema reconciliation and development-server runtime — 2026-11-17

The owner authorized this follow-on work and asked to fix the hosting terminal's
PHP version error. Bare php still resolved to XAMPP 8.2.12: php artisan --version
failed with Composer's >=8.4.0 platform requirement (exit 255). The same command
with C:\tools\php84\php.exe reports Laravel 13.42.0. No platform check was bypassed.

The workspace's ../.vscode/settings.json now selects PHP 8.4 for PHP validation
and prepends C:\tools\php84 to terminal.integrated.env.windows.PATH. This affects
NEW IDE terminals, not an already open shell or permanent Windows PATH. In an old
terminal, apply the existing session PATH command above, or open a new terminal.
Run Artisan from the studentity directory. No other editor settings existed or
were overwritten. Reverting these two workspace settings restores prior behavior.

No PHP server was running when inspected. A local server was started using PHP 8.4
at http://127.0.0.1:8000. Process inspection confirmed both Artisan and its PHP -S
child use C:\tools\php84\php.exe. GET / returns 200 and /m0/health returns
status=ok, phase=M0, mode=synthetic-only. The server is agent-started; no existing
IDE terminal was injected with commands or stopped. This is local development,
not a production deployment. For later manual startup after this server stops:

    Set-Location 'C:\Users\Evon\Downloads\Profiling\studentity'
    $env:Path = 'C:\tools\php84;' + $env:Path
    php artisan serve --host=127.0.0.1 --port=8000

New tests/Feature/Fnd02MongoSchemaTest.php provides regression evidence for the
EXISTING accepted schema. It does not migrate, repair, drop, or clear collections.
Both dev/test metadata are read-only; writes and UUID-scoped cleanup are restricted
to exactly local studentity_test_m0. It verifies all 62 validators, strict/error
settings, 128 distinct catalogue index definitions per DB, and the recorded M0
migration. Three invalid-user cases assert MongoDB validation code 121; a real
transaction proves valid identity insert/abort, and a synthetic duplicate System
ID asserts unique-index code 11000. Six new cases pass / 1,533 assertions; the
complete suite passes 62 / 1,714. These extend existing behavior coverage, rather
than claim a new runtime feature was developed through a red/green cycle.

Independent read-only review of the workspace settings and new schema tests found
no substantive defects. Required catalogue definitions are checked; this does not
assert that extra collections/indexes are absent. Formatting and protected/SRS/
Blueprint/ADR hash checks passed. The dev server remained healthy after the suite.

| Original FND-02 acceptance | Accepted MongoDB interpretation | Evidence / remaining gap |
|---|---|---|
| PostgreSQL migration/schema baseline | ADR-01/02 select MongoDB; source backlog remains archival and unchanged | Catalogue/validators/indexes match both synthetic environments |
| Uniqueness and FK checks | MongoDB unique/partial indexes plus application reference integrity | Student/year, active admin, and System ID uniqueness proven; general reference reconciliation remains open |
| Automated apply/rollback | Reviewed schema migration; down() deliberately no-op under accepted disposal constraints | Existing migration recorded; no fresh-environment apply or idempotent reapply was performed in this slice |
| Migration dry run | Review proposed MongoDB commands against the catalogue before execution | No schema-changing dry run/apply performed; do not equate metadata inspection with migration execution proof |
| Identity prerequisites | UUID/key and permanent System ID contracts are separate application responsibilities | No System ID allocator, nonrecycling registry/service, or full reference enforcement exists in app/; do not claim these from indexes alone |

FND-02 is therefore reconciled with the accepted ADRs but not declared completely
closed. The original SQL rollback/FK requirements are not silently marked passed.
UUID strings are specified by the catalogue; users._id currently has a BSON string
validator, not a UUID-format regex. Approved write paths still need UUID validation.
The existing unique System ID index does not alone prove historical nonrecycling.
No source requirements/ADR or physical catalogue/migration was changed.

#### Shared-session laboratory result — 2026-11-17

The owner authorized proceeding with the next development slice. The accepted
candidate transport was evaluated only in fully bootstrapped, separate Laravel
CLI processes. No application session configuration, provider binding, dependency,
schema catalogue, migration, or public endpoint was changed.

- New tests/Feature/SharedMongoSessionLabTest.php coordinates the evaluation.
- New tests/Support/session-lab-worker.php boots the installed Laravel application
  per process, selects the existing official MongoDB driver only in memory, and
  accepts synthetic input over stdin. It is CLI/testing-only, not a route or provider.
- Targets are fixed: local studentity_test_session_lab, session_payloads and barriers.
  Session IDs must be randomly generated synlab-prefixed 40-character fixture IDs.
  Dev/test business/security databases are never used for transport writes.
- No GC or TTL helper runs. Cleanup stops only owned test workers and removes only
  recorded fixture/barrier IDs. The two empty lab collections remain available for
  review; no database/collection drop or broad cleanup was performed.
- Seven evaluation cases add 57 assertions; the full suite passes **69 tests /
  1,771 assertions**, with zero failures/skips. Scoped Pint and diff checks pass.
  Independent read-only review found no Critical, Important, or Minor defects.
- Both existing synthetic environments retain the 62 validated collections plus
  migration ledger; all 128 distinct catalogue indexes match. Application config,
  dependency locks, schema catalogue, and protected-file hashes are unchanged.

| Evaluation | Observed result | Interpretation |
|---|---|---|
| Separate Laravel/PHP processes | One process writes; a different process reads the same binary JSON payload; child PHP matches parent | Shared transport works in this single-node local lab |
| Invalidation and expiry | Another process no longer reads invalidated/expired payload; ID and CSRF token rotate | Basic transport behavior verified |
| Unavailable transport | Worker reports a sanitized 503 result; unsafe DB/table/ID inputs are rejected before access | Worker boundary fails closed; this is not an HTTP/production-backend outage test |
| Concurrent unlocked increments | Both processes read zero and save one; final value is one, not two | Lost updates are reproducible; shared request locking/CAS is required before activation |
| In-flight write after invalidation | Held writer can upsert the old payload after another process invalidates it | Transport deletion alone is not durable security revocation |
| Restored owned payload | Synthetic payload becomes readable again while independent policy still denies access | MongoDB payload presence never establishes current authority |
| Isolation and cleanup | Only owned synthetic records deleted; no TTL index created | Accepted evidentiary/control collections remain untouched |

The green suite deliberately captures the unlocked driver's limitations; it does
not turn those limitations into acceptable production behavior. The parent test's
in-memory authority is outside the simulated MongoDB record restoration, but is
not proof of durable independent authority or a full disaster-recovery procedure.
The processes exercise Laravel session transport, not a two-server HTTP deployment;
FND-03's prior tests provide the separate HTTP/cookie evidence.

Execution rulings:

- Ruling: Use a dedicated additive synthetic lab database rather than change either
  accepted environment's 62-collection model. Leave empty lab metadata in place
  rather than issue destructive drops. No deployment schema change is approved.
- Ruling: Preserve the installed driver/vendor code and reproduce its unlocked
  races deterministically with bounded barriers. Do not enable it in the app while
  those limitations remain. A shared lock must cover read through final save,
  including logout, across every app instance and writer; a process-local lock is
  insufficient. Epoch/independent revocation still vetoes resurrected payloads.
- Ruling: Retain the unavailable production authority. Test-only trust assertions
  do not authorize a provider, cloud resource, external credentials, or student login.

Evidence: storage/app/private/session-lab-2026-11-17/ contains before-hashes.json,
red.txt (worker absent before implementation), first-run.txt, full-tests.txt and
schema.json. An initial test helper name collided with PHPUnit's final result()
method; it was corrected before the meaningful missing-worker red run. Workers do
not output session IDs, payloads, credentials or arbitrary exception messages.
Runtime app config remains unchanged; no production code was added by this lab.

Rollback is limited to removing these two new test files and reverting this handoff
section after review. Do not reset master, remove unrelated uncommitted work, clear
unknown lab records, undo authoritative revocations, or drop MongoDB databases.
The existing schema/application require no rollback.

The shared-lock evaluation below now covers contention, timeout, lease ownership,
and logout/final-save ordering. Its remaining expired-writer counterexample needs
storage-level fencing before a transport activation diff can be accepted. Durable
authority receipts, journal linkage and an independently approved trust/recovery
model remain required before real authentication.

#### Shared-lock evaluation and checked reference folders — 2026-11-17

The owner authorized the next step and explicitly requested checking both sibling
folders. Both are accessible and were consulted:

- ../Documentations/R2 Requirement Finalization/Studentity_Software_Requirements_Specification_v1.1_Final_Consolidated.docx:
  FR-ACT-018 (current scope on existing sessions), FR-ACT-020/021 (recovery and
  independent reconciliation), BR-050 (no restored revocation), NFR-SEC-014.
- ../Studentity_M0_Laravel_MongoDB_Approved_v1.0/ADR-01_FINAL_Laravel_Runtime.md,
  ADR-02_FINAL_MongoDB_Physical_Model.md, database-design/key_queries_and_invariants.md,
  README_START_HERE.md and M0_VALIDATION_REPORT.md. The package's earlier untested
  runtime status is historical; current runtime claims come from repository evidence.
- The accepted package requires isolated labs, no destructive schema operations,
  no student data, and no institution/vendor approval inferred from test results.
  All consulted SRS/Blueprint/ADR/invariant files remain unchanged. Reference hashes
  are saved with this slice's evidence. AGENTS.md records the owner's reference rule.

Changed code is test-only:

| File | Change |
|---|---|
| tests/Support/SessionLabHarness.php | Extract shared guarded environment/process/cleanup logic; stop owned workers before deleting only generated fixture IDs |
| tests/Feature/SharedMongoSessionLabTest.php | Reuse that harness; retain all prior unlocked transport/race tests |
| tests/Support/session-lab-worker.php | Add ephemeral CacheManager MongoDB lock store and bounded locked modes; acquire before session read, hold through final save/invalidation |
| tests/Feature/SharedMongoSessionLockLabTest.php | Seven lock/contention/expiry/logout/failure checks using separate Laravel processes and native MongoLock |

No application, environment file, dependency, accepted catalogue, migration, or
production authority binding changed. The fixed isolated database remains
studentity_test_session_lab; its additional locks collection contains only owned
synthetic lock records. Native lock pruning is disabled via lock_lottery=[0,0];
no TTL index or garbage collection is enabled. No forceRelease or broad cleanup
is used. Empty lab metadata remains; dev/test business schemas remain unchanged.

Six new worker-behavior cases were observed failing before implementation; the
seventh verifies the installed provider's existing lack of lease refresh. Scoped
Pint passes. **Full suite: 76 passed / 1,837 assertions / zero failures or skips**.
Both existing synthetic databases still match 62 validators and 128 distinct
catalogued indexes. Config/dependency/catalogue/protected/reference hashes match.
Independent read-only review found no Critical, Important, or Minor defects in
this isolated experiment. Durable authority, deployment failover and full HTTP
coordination remain unproven activation gates; the lease counterexample is retained.

| Case | Observed result | Limit |
|---|---|---|
| Concurrent read/modify/save | Shared native lock serializes two increments to two; second process reads the first process's saved value | All writers must participate from before read through final save |
| Contention timeout | Bounded lock timeout returns a sanitized lab 503; no payload mutation | Worker outcome, not an enabled HTTP API response contract |
| Logout ordering | Logout waits for the earlier locked save, then invalidates; later synthetic mutation refuses a missing payload | This lab guard is not an application login/authorization implementation |
| Expired owner after takeover | Old owner detects lost lease, refuses save and cannot delete the successor's lock | Ownership checks do not make the subsequent write atomic |
| Expiry AFTER last owner check | Deliberately paused old owner still overwrites successor data after resuming | Critical activation gap: storage must atomically reject obsolete writers (fencing/CAS), not rely on a separate check |
| Backend failure / thrown request | Lock outage does not mutate payload; callback exception releases lock; later request can acquire | No failover, clock-skew or partition-resilience claim |
| Lease refresh | Installed MongoLock inherits the unsupported-refresh exception | Do not assume automatic lease renewal exists |

Ruling: use the installed CacheManager/MongoStore/MongoLock provider in memory,
with an isolated lock collection and test-only timings (leases 1–15 seconds,
waits 0–2 seconds), rather than alter vendor code or choose production timeout
policy. Reuse one process harness and retain the unlocked counterexamples as
controls. Production configuration remains file sessions / JSON serialization /
unavailable security authority.

Ruling: preserve the expired-after-check counterexample as a passing evaluation
test that explicitly demonstrates unsafe transport behavior. A green evaluation
suite is not approval to activate shared sessions. The process pause models a
writer stalled across lease expiry; expiry is simulated only on its owned lab
lock, not by changing server clocks or any accepted security record.

The atomic fencing/generation prototype is now evaluated below. A pre-save owner
check, process-local mutex, or assumed lease refresh remains insufficient for the
native driver. Any actual transport/control-schema change or authority provider
still requires a reviewed diff, negative tests and recovery plan; no new vendor,
credentials or production approval is implied.

Evidence: storage/app/private/session-lock-lab-2026-11-17/ contains pre-change
copies of the existing worker/test, before-hashes.json, reference-hashes.json,
red.txt, green-attempt.txt, full-tests.txt and schema.json. Rollback restores the
two pre-change test files and removes only the two new test-support/test files
after diff review; revert only this handoff/AGENTS addition if requested. Preserve
master history, protected work, reference documents and all unknown DB records.
No database/schema rollback is required.

#### Atomic storage-fencing evaluation — 2026-11-17

The owner authorized proceeding with this next development plan. Requirements
were rechecked in both sibling reference folders: finalized SRS v1.1 FR-ACT-020/
021, BR-050 and NFR-SEC-014 in Documentations/R2 Requirement Finalization, and
ADR-02 plus database-design/key_queries_and_invariants.md in the approved M0
package. Source documents, the accepted model and existing protected work remain
unchanged. This is a lab-only proof, not a production session-driver activation.

| File | Change |
|---|---|
| tests/Support/FencedSessionLabStore.php | Test-only SessionHandlerInterface prototype with one atomic fenced envelope |
| tests/Support/SessionFenceRejected.php | Explicit rejected-write exception so Laravel Store::save cannot silently ignore a false handler return |
| tests/Feature/SharedMongoSessionFenceLabTest.php | Eleven multi-process, expiry, revision, tombstone, failure and restore checks |
| tests/Support/session-lab-worker.php | Opt-in fenced-* test actions; manually construct Laravel JSON Store and close handler in finally |
| tests/Support/SessionLabHarness.php | Add fixture-ID-only cleanup for the isolated fenced_sessions collection |

The envelope lives only in studentity_test_session_lab.fenced_sessions. Its fields
are _id (synthetic session ID), binary JSON payload, generation/revision (Int64),
opaque owner, lease_until/session_expires_at (BSON dates), and revoked. It has no
TTL or GC disposal behavior. Explicit synthetic fixture issuance is insert-only;
no missing or tombstoned session is implicitly created or upserted on final save.

Acquisition atomically requires unrevoked/unexpired state and an expired/released
lease, then advances generation, assigns a random owner and captures the payload.
Lease deadlines and comparisons use MongoDB server $$NOW. Reads use primary /
majority concern; writes request majority acknowledgment. These settings do not
by themselves prove deployment failover or backup safety.

Final save is ONE conditional update matching session ID, owner, generation,
payload revision, unrevoked state, and server-time lease/session expiry. Only that
matched update can replace payload and advance revision; it never upserts. A
rejected match throws. Invalidation atomically advances generation/revision,
clears payload/owner and retains a revoked tombstone. Closing an old handler
matches its own owner/generation only, so it cannot clear the successor's lease.

| Evaluation | Result |
|---|---|
| Separate Laravel JSON-store processes | Saved data is readable across processes; generation advances and revision persists |
| Pause across expiry/takeover | Successor can save 99; old writer is rejected and cannot clear successor ownership |
| In-flight invalidation | Old writer cannot save; future reads/issuance reject; tombstone remains |
| Lease expiry without takeover | Expired writer cannot save even without a successor |
| Session expiry before final save | Payload revision remains unchanged and further reads are denied |
| Missing envelope | Delayed writer cannot recreate it |
| Concurrent current owner | Contender cannot read payload or advance generation |
| Revision changes within a lease | Older captured revision cannot overwrite newer payload |
| Outage / thrown callback | Pre-acquisition outage fails closed; ordinary callback failure releases its lease without payload mutation |
| Garbage collection / target guards | No TTL created; gc is a no-op; other database names rejected |
| Restored old envelope | Transport may become readable after a controlled restore, but separately retained test authority still denies access |

Eight initial behavior tests were observed failing before implementation, then
passed. Three additional contention/revision/restore checks confirm the boundaries.
An initial helper named seed collided with Laravel's existing TestCase method;
it was renamed before the meaningful failing worker-mode run. After scoped Pint,
the full suite passes **87 tests / 1,939 assertions**, zero failures/skips. The
existing unlocked and lock-only counterexamples remain as controls: the official
native session driver was NOT patched or given fencing by this experiment.

Independent fresh-context read-only review found no Critical, Important or Minor
defects. It checked all five files, reference constraints, saved evidence and file
hashes without rerunning tests or modifying the checkout. Both business databases
still match 62 validators / 128 distinct catalogue indexes; existing application
configuration, bootstrap, dependency files, catalogue and protected-file hashes
match their pre-slice values. No migration, staging or commit occurred.

Execution rulings and limits:

- Ruling: Co-locate fencing state and payload in one laboratory envelope, rather
  than add another non-atomic pre-write lease check to the vendor's separate lock
  and payload records. Native vendor behavior and all earlier controls are preserved.
- Ruling: Reject missing/new IDs by default. This is pre-provisioned synthetic
  authenticated-session transport, not a drop-in general Laravel handler: anonymous
  session issuance, session-ID rotation/new-ID persistence and full HTTP middleware
  integration are not implemented. Promoting it requires an explicit lifecycle design.
- Ruling: Preserve tombstones/generations and disable disposal in the prototype.
  Fixture teardown deletes only its own generated IDs after stopping owned workers;
  no operational tombstone retention/disposal policy is invented.
- Ruling: Fencing guards concurrent local storage updates, not independent security
  recovery. The restore case replaces one owned envelope and uses in-memory test
  authority; it is not a real backup restore or durable authority demonstration.
  Runtime authority remains UnavailableSecurityStateAuthority.
- Outage evidence covers failure before acquisition and normal exception cleanup.
  Mid-request database outage, uncertain commit acknowledgment, server failover and
  release success during an outage remain unproven. A failed release may leave the
  lease until expiry; no automatic request replay/retry policy is assumed.

Evidence: storage/app/private/session-fence-lab-2026-11-17/ contains prior worker/
harness copies, before-hashes.json, red.txt, first-green.txt, full-tests.txt and
schema.json. Reference preservation was checked against the previous slice's saved
hashes. Empty lab metadata remains; no broad cleanup, collection drop or TTL ran.
Rollback restores only the two saved test-support files and removes only the three
new test/exception/store files after diff review; preserve all unrelated work,
reference sources, master history and unknown database records.

Completed follow-on slice below: specify the full session lifecycle and prove the fenced
handler behind test-only HTTP middleware, covering anonymous issuance, credential-
verified rotation, logout/new-ID persistence, and error mapping. Keep production
activation gated on a separately reviewed additive transport schema/configuration
diff and the independent authority's durable acknowledgment/receipt and recovery
trust model. Do not select or provision a production provider by implication.

#### HTTP session lifecycle lab — execution plan, 2026-11-17

Owner instruction: continue on master with the next documented development step.
Use the existing isolated fenced_sessions lab envelope behind test-only Laravel
StartSession middleware, encrypted cookies and active PreventRequestForgery. No
application driver, public route, business schema or authority binding is activated.

Design/rulings: issue random synlab IDs only on requests without a usable cookie;
never adopt or create a client-supplied missing ID. Rotation retires the old envelope
with a single current-owner/generation/revision/expiry CAS, then explicitly issues
and acquires a fresh empty envelope. A failure after retirement denies the request;
it must not undo the tombstone or send a successful replacement cookie. Both
regenerate(false) and regenerate(true) retire the old ID in this lab. Logout flushes
attributes, rotates the ID and CSRF token, and persists a fresh anonymous session.
The existing unconditional destroy remains a trusted fixture-invalidation primitive,
never the HTTP lifecycle retirement operation. There is no automatic retry.

Test credentials are a synthetic password hash checked by Laravel Hash::check in
an in-memory test route, not a user/credential provider or completed login workflow.
Transport success cannot establish current authorization. Keep unavailable authority.
Native downstream 4xx/5xx statuses remain recognizable; skip payload persistence
and session-cookie publication on 5xx. Fence conflicts map to generic 409, transport
exceptions to generic 503 problem responses without IDs, payloads or driver messages.
Release the owned lease after final save and on failures; a release outage also
prevents reporting success. Precognitive requests are rejected by this test adapter.

- [x] Write HTTP lifecycle/negative cases and observe missing-adapter failures.
- [x] Add tests/Support/FencedHttpLabSession.php (explicit issuance/rotation),
  tests/Support/FencedHttpLabMiddleware.php (native HTTP integration/error boundary),
  and current-lease retirement in tests/Support/FencedSessionLabStore.php.
- [x] Prove issuance/rehydration, password rejection/verified rotation, logout/replay,
  unknown/malformed IDs, active CSRF, stale save/retirement, expiry, backend outage,
  downstream failure cleanup and failed replacement issuance in
  tests/Feature/SharedMongoSessionHttpLabTest.php.
- [x] Complete fresh review; formatting, full suite and schema/hash checks pass.
- [x] Record exact results/limits and next activation prerequisite here.

Evidence/snapshots: storage/app/private/session-http-lab-2026-11-17/ (ignored).
Rollback: after reviewing diffs, restore only the saved fenced-store file, remove
the three new HTTP lab files and revert this section. No database rollback or drop;
teardown removes only generated fixture IDs. Preserve all unrelated dirty work.
Single handoff/master/no automatic commit rules override skill defaults for separate
plan files, worktrees and commits. Existing owner authorization covers this lab;
production activation and independent authority design remain separate approval gates.

Execution result: 13 new cases pass / 103 assertions. They were first run with the
adapter absent (13 missing-class failures, zero assertions); the implemented adapter
then passed all 13. Scoped Pint passes. Full suite: **100 passed / 2,042 assertions /
zero failures or skips** (48.66 seconds). Both existing synthetic business databases
retain 62 validators / 128 distinct catalogue indexes with no mismatches and one
M0 migration. All 13 saved protected/config/dependency/catalogue/reference hashes
match. Application route inventory contains no _session_lab or _fnd fixture routes.
No migration, TTL, broad cleanup, dependency change, staging or commit occurred.

| File | This slice's change |
|---|---|
| tests/Support/FencedSessionLabStore.php | Add current-lease retirement CAS; preserve existing transport/worker behavior |
| tests/Support/FencedHttpLabSession.php | Laravel JSON Store adapter, explicit replacement issuance and acquisition |
| tests/Support/FencedHttpLabMiddleware.php | Native StartSession lifecycle, request-scoped handler, failure/cookie boundary and release |
| tests/Feature/SharedMongoSessionHttpLabTest.php | Thirteen real HTTP-kernel/encrypted-cookie/CSRF/MongoDB lifecycle cases |
| docs/handoffs/handout_codex.md | Plan, evidence, rulings, results and remaining gates |

Laravel Routing\Pipeline can render route exceptions before unwinding middleware.
The lab boundary inspects its attached exception and normalizes session/MongoDB
failures, including failed replacement issuance; unrelated 404 and downstream 500
statuses are retained. A staged session cookie is discarded with a failed-save
response; no success is reported when the final CAS fails. Native cookie encryption
and active CSRF checks are exercised, including missing/wrong token rejection.

Limits: these are in-process kernel requests with real isolated storage, not browser
automation or two deployed HTTP servers. The test fixture verifies one synthetic
hash and carries a synthetic marker, not an institutional identity or authorization
decision. This adapter uses Request::session(); integration with SessionManager's
cached driver, session.store injection, real Auth/Sanctum guards and long-running
workers is not yet proven. No production transport or credential workflow is enabled.
Rotation is fail-closed sequencing, not a multi-document atomic issuance transaction:
the old tombstone remains if replacement issuance fails; an unreturned empty envelope
can remain if a later acquisition/save fails. Owned test fixtures are cleaned without
inventing an operational orphan/tombstone disposal policy. Backend failure is proven
before acquisition and during replacement issuance; mid-save/release outage, uncertain
acknowledgment, failover, streaming responses and recovery remain unproven. The adapter
handles release failures defensively but this suite does not simulate one. Laravel's
native reporting may run before response normalization; this lab does not prove a
production log-redaction policy. Application authority remains unavailable by default.

Evidence: red.txt, green-attempt.txt, full-tests.txt, schema.json, routes.json,
before-hashes.json and FencedSessionLabStore.before.php in the directory above.
The initial combined PowerShell hash-check expression nested two arrays and reported
a false mismatch; explicit per-file enumeration verified all 13 original hashes.
Neither protected file nor source reference changed.

Independent fresh-context read-only review found no Critical, Important or Minor
defects. It inspected the three new files, the exact saved-store delta, installed
Laravel lifecycle/pipeline code, SRS and accepted ADR/invariant requirements, and
saved test evidence without rerunning tests or writing files/database records.
Review exclusions were ruled as retained gates: production activation/providers,
institutional credentials, independent authority/recovery, Auth/Sanctum integration,
business schemas/routes, operational disposal, browser/deployed servers, failover,
streaming and uncertain acknowledgments. No defect or review fix was deferred.

Completed in the two-task slice below: integrate this lifecycle with real Laravel
SessionManager/session.store and synthetic Auth/Sanctum guards inside the isolated
test harness, proving that guard writes/rotation/logout use the fenced store and
that the existing current-authorization gate still denies unavailable/revoked trust.
Keep all login/resource routes test-only, reuse existing synthetic security fixtures
with generated-ID cleanup, and preserve application defaults. Prepare the exact
additive activation diff and independent authority acknowledgment/receipt/recovery
contract for review before any production/shared-driver activation. Provider choice,
institutional credentials and registration remain outside this authorization.

#### Two-task guard integration slice — execution plan, 2026-11-17

Owner requested completing two tasks/milestones in this pass. These are two scoped
foundation tasks, not completion of all M1 or production FND-03 readiness.

1. Guard transport integration: test-only request boundary binds the current fenced
   Store into SessionManager's temporary custom driver and session.store before
   resolving actual web/Sanctum guards. Clear guard/driver caches at entry and exit,
   restore the previous binding/configuration, and prove native attempt/login,
   cookie rehydration, fixation/CSRF rejection, helper writes and logout. A synthetic
   EloquentUserProvider subclass retrieves real MongoDB fixture identities and checks
   only an in-memory fixture password hash; never add password fields to users or
   implement production credentials/remember-me. New tests/Support/BindFencedLabGuard.php,
   FixtureCredentialLabProvider.php, GuardSessionLabHarness.php and
   tests/Feature/FencedSessionGuardLabTest.php. Write/run failing cases first.
2. Current-security integration: new tests/Feature/FencedSessionAuthorizationLabTest.php
   exercises existing EnforceCurrentAuthorization and SessionRevocationService through
   those real guards and the fenced transport. Require allow/audit only with current
   test authority; test changed program/account/MFA/epoch, unavailable authority,
   revocation/logout and owned-record restoration that cannot undo independent denial.
   Use exact local studentity_test_m0 for generated UUID security fixtures and the
   existing isolated lab for payloads. Clean only owned IDs/correlation events.

Pre-flight: task 2 consumes task 1's guard/session/fixture harness; both preserve
production file/JSON sessions, unavailable authority, source documents, protected
work and dependencies. No new public route, schema/TTL, provider selection or commit.
Run each scoped task, full suite and formatting; verify schema/reference/protected
hashes and route inventory, then one fresh read-only review for both tasks.
Evidence: storage/app/private/session-guard-lab-2026-11-17/. Rollback removes only
the five new files after diff review and reverts this handoff section; no DB rollback
or destructive operation. If fixes require an existing lab file, snapshot it first.

- [x] Task 1: real guard/session-manager transport integration and negative tests.
- [x] Task 2: current authorization/revocation/recovery-denial integration evidence.
- [x] Full verification, independent review, final results/limits and next plan.

Task 1 result: **8 passed / 66 assertions**. Eight initial cases failed with the
adapter absent. The first implementation attempt exposed an installed-framework
contract: SessionManager::extend expects a SessionHandlerInterface and wraps it in
a NEW Store. Returning the already-started lifecycle Store produced a type error.
Ruling: use an anonymous test-only SessionManager subclass's normal create-driver/
cache path to return the exact existing Store; do not patch vendor code or discard
the explicit lifecycle adapter. Bind session, session.store, helper/facade access
and web guard to that Store during the request; clear cached guards, auth.driver
and Session facade references, then restore the previous manager/store/configuration.
Tests warm those caches before login and prove a new anonymous request receives no
previous identity. Real SessionGuard::attempt retrieves a MongoDB fixture and checks
its fixture hash, rotates natively, saves its actual login key and rehydrates through
both web and Sanctum guards. Helper writes, native logout, CSRF, wrong credentials,
old-cookie replay and failed final-login save are covered. No remember-me enabled.

Task 2 result: **12 passed / 77 assertions**. This is additional integration evidence
for existing production policy/services, not a claim that new authorization behavior
was added through a red/green cycle. The initial run passed 11 cases; the owned
restore fixture failed strict validation because small native PHP integers re-encoded
the saved BSON long as int32. Explicit Int64 preservation for the security epoch
and transport counters fixed that fixture without changing any validator/runtime.
The corrected run passes all 12 cases: anonymous/bearer denial, current allow/audit,
scope reduction with forged parameters, deactivation, MFA pending, changed epoch,
default unavailable authority, independent revocation, transactional security logout,
owned transport/control restoration denied by independent state, unacknowledged
revocation, and a real unavailable audit backend. No mock audit backend is needed.

| File | Responsibility in this slice |
|---|---|
| tests/Support/BindFencedLabGuard.php | Request-scoped framework manager/store/guard binding and restoration |
| tests/Support/FixtureCredentialLabProvider.php | Real MongoDB fixture retrieval with in-memory synthetic hash validation; no credential/rehash writes |
| tests/Support/GuardSessionLabHarness.php | Guarded generated-ID security/payload fixtures, in-memory routes, active CSRF and owned cleanup |
| tests/Feature/FencedSessionGuardLabTest.php | Eight native guard/lifecycle/cache integration cases |
| tests/Feature/FencedSessionAuthorizationLabTest.php | Twelve current-security/audit/revocation/restore integration cases |
| docs/handoffs/handout_codex.md | Unified task plan, results, rulings, limits and next step |

Full verification: **120 passed / 2,185 assertions / zero failures or skips**, 54.45s.
Scoped Pint passes. Both synthetic business DBs retain 62 validators / 128 distinct
catalogue indexes, zero mismatches; all 13 saved protected/config/dependency/catalogue/
reference hashes match. Normal application route inventory contains no fixture routes.
Only the five new test/support files and this handoff changed in this slice; no
existing runtime/lab source, application config, lockfile, schema, migration, TTL,
staging or commit changed. Vite was not rerun for this PHP-test-only change; the
previous verified frontend build remains historical evidence, not a new build claim.

Limits/rulings: this proves actual Laravel guard APIs against the fenced Store in
an in-process kernel lab. It is not an activated session driver or a complete
Sanctum stateful-origin API deployment; the earlier FND-03 origin tests remain
separate evidence. The fixture provider's memory hashes/disabled rehashing are
explicitly unsuitable for real credentials. Current authorization reads/audit and
local logout transactions are real, but the independent authority is still a
test-only in-memory fake, not durable or operationally independent infrastructure.
The controlled restore changes only two owned records; it is not a backup/DR test.
Concurrent long-running-worker/container request scoping, production log redaction,
mid-save/release outages, acknowledgment uncertainty, failover and orphan/tombstone
disposal remain activation gates. A rejected final-login save may leave an unreturned
empty envelope and synthetic security record; no success/cookie or guard identity
survives, but operational reconciliation is still needed before activation. Native
logout alone is not independent revocation; only the separate service route tests
that stronger behavior. Neither FND-03 production readiness nor all M1 is closed.

Evidence: task1-red.txt, task1-green-attempt.txt, task1-green.txt,
task2-first-run.txt, task2-green.txt, full-tests.txt, pint.json, schema.json,
preservation-check.json and routes.json in the ignored directory above. Existing
middleware snapshot was precautionary and was not used/modified. The handoff's
pre-slice copy is also retained there; rollback needs no application or DB repair.

Fresh independent read-only review found no Critical, Important or Minor defects
across all five new files. It checked installed framework binding/cache/pipeline
behavior, both reference folders, saved test/schema evidence, independently recomputed
the 13 preservation hashes and matched the unchanged HTTP middleware snapshot.
It did not run tests, modify files/index/branch, write database records or commit.
Review exclusions match the explicit limits above: production/credential/MFA workflows,
durable authority and actual recovery, operational concurrency/outage behavior,
business routes/schema and retention/disposal remain gates. No defect was deferred.

The next two design tasks, completed below: (1) prepare a reviewable additive activation patch/design
for the fenced transport, including complete first-party middleware ordering,
request-scoping, safe failure reporting and reconciliation of unreturned sessions;
do not enable it or run a schema migration. (2) Specify the independent authority's
durable idempotent acknowledgment/receipt and recovery contract, journal linkage,
failure/repair acceptance cases and required operational-owner decisions; retain
UnavailableSecurityStateAuthority until a concrete trust model/provider is approved.
Keep both in this handoff. Provider selection, institutional credentials, production
hosting, registration and public business routes require their existing gates.

#### Activation and authority designs — two deliverables, 2026-11-18

Owner authorized the next two documented tasks. This pass prepares the designs for
review; it does not implement/activate the proposed runtime files, change the authority
port, apply a migration, select infrastructure or expose authentication/business routes.
The existing 120-test / 2,185-assertion result is evidence from 2026-11-17; this
documentation-only pass does not represent a new runtime test run.

- [x] Task A: concrete additive transport activation design, failure/repair and rollback.
- [x] Task B: durable authority acknowledgment/receipt/recovery and journal contract.
- [x] Fresh design review, preservation verification and recorded review rulings.

Design basis: finalized SRS v1.1 FR-ACT-018/020/021, NFR-SEC-004/014, BR-050/051;
accepted sibling ADR-01/02 and physical-model invariants; actual installed Laravel
13.42.0 / Sanctum 4.4.0 middleware/SessionManager and existing security source.
Consulted both sibling folders and preserved sources. Prefer the existing fenced
envelope candidate over the native unlocked handler: its known races block activation.
Introducing Redis would add unselected infrastructure without resolving independent
recovery. This is a design choice proposed by Codex, not institutional approval.

##### Task A — proposed additive activation design

Future change manifest (a reviewable specification, NOT files added in this pass):

| Proposed path | Exact responsibility / change |
|---|---|
| config/session_transport.php | enabled=false; mandatory explicit connection/database/collection, lease/session lifetime, operation deadlines and payload-key configuration before enablement; no fallback to business/control collections |
| app/Infrastructure/Session/FencedSessionHandler.php | Guarded envelope issuance/acquisition, server-time fencing/CAS, authenticated payload encryption, fenced retirement and conditional release; no implicit upsert/GC |
| app/Infrastructure/Session/FencedSessionStore.php | JSON lifecycle Store; explicit issuance and retire-before-replacement for both regenerate modes; invalidate clears attributes and rotates CSRF |
| app/Infrastructure/Session/FencedSessionManager.php | Native SessionManager driver-creation/cache path returns the request's exact lifecycle Store; extend(handler) alone would create the wrong Store |
| app/Http/Middleware/FencedStartSession.php | Acquire/start before any guard, bind identical session/store/manager, save before returning response and release in finally; no boot-time request/session capture |
| app/Http/Middleware/SessionTransportBoundary.php | Outer request boundary and typed sanitized problem mapping, cookie withholding and correlation; preserve unrelated HTTP failures |
| app/Providers/SessionTransportServiceProvider.php | Register request-scoped services; when explicitly enabled, bind native StartSession class to FencedStartSession so web and Sanctum's hardcoded inner class resolve consistently |
| bootstrap/providers.php; bootstrap/app.php | Register gated provider and outer boundary/report callbacks; keep statefulApi, middleware priority and exact single-start rules below |
| config/session.php | Enabled candidate selects the custom driver, JSON and payload encryption; keep secure/HttpOnly/lax flags; disabled local synthetic mode remains current configuration |
| database/mongodb/session_transport_v1.json | Separate proposed strict validator/index contract below; do not edit the accepted 62-collection catalogue or source ADRs |
| database/migrations/mongodb/<reviewed-date>_create_session_transport_v1.php | Future additive create/verify only; compare existing metadata and refuse mismatches; down never drops data; never auto-run on deploy |
| app/Infrastructure/Session/SessionTransportReconciler.php | Explicit dry-run/repair of known staged envelopes, through authority-acknowledged security cleanup when needed; no broad deletion |
| tests/Feature/FencedSessionActivationTest.php; tests/Feature/FencedSessionFailureTest.php | Future acceptance matrix below through actual web/first-party API middleware, not a manually simplified chain |

Transport resides in a separately named database selected explicitly by approved
configuration. Synthetic activation work is restricted to local
studentity_test_session_activation.envelopes_v1. No production database name/host
or operational policy is chosen. Same-server separation isolates payloads from the
accepted model; it DOES NOT make the security authority independently recoverable.
Provider refuses user_sessions, audit_events, security_decision_journal and existing
business databases as payload targets. It also refuses lab IDs/config outside testing.

Proposed envelope validator: strict/error, additionalProperties=false; all IDs below
are transport identifiers, not the application's UUID business identifiers.

| Field | BSON contract / invariant |
|---|---|
| _id | string, lowercase 64-hex SHA-256 of raw session ID; raw ID never persisted as an identifier |
| lifecycle | string enum staged/live/revoked; no other state or implicit missing-state default |
| payload | binData; authenticated encrypted JSON only; required for live, absent for staged and revoked |
| generation; revision | long, 0..9223372036854775807; increment without wrap; overflow rejects and requires a new reviewed issuance |
| owner | string empty or 32 lowercase hex characters; server-generated opaque lease owner |
| lease_until; created_at; updated_at; expires_at | BSON UTC Date; deadlines derived/compared using server $$NOW |
| issuance_operation_id | lowercase UUIDv4 string; immutable server-generated correlation for staged issuance/reconciliation |
| schema_version | int exactly 1 |

Validator oneOf enforces staged/live/revoked shapes, including revoked having no
payload, owner='' and lease_until at epoch. Required fields include all except
payload. Indexes: native unique _id; non-TTL ix_transport_staged on
{lifecycle:1,lease_until:1}; non-TTL ix_transport_expiry on {expires_at:1}.
No TTL, GC/disposal or unique-institutional-user index. Lifetimes/deadlines are
session access controls, not a retention policy, and remain owner approval inputs.

New raw IDs use bin2hex(random_bytes(20)), satisfying Laravel's 40-character shape;
client-supplied missing IDs are never created. Lookup uses their digest. Encrypt a
versioned object containing the same digest plus payload_json through the installed
Laravel Encrypter::encryptString/decryptString methods; encode/decode JSON explicitly.
The handler owns exactly one encryption layer: the custom JSON Store must not wrap
it in native EncryptedStore or use decrypt's default PHP-unserialize path. On read,
authenticate/decrypt and verify the bound digest and JSON shape before hydration.
Never introduce PHP unserialize or trust a copied payload
under another digest. Payload key ownership/rotation is an activation prerequisite;
it is independent of authority verification keys. No raw cookie/token in logs.

Lifecycle contract: server creates a staged envelope and captures its initial lease
as one explicit issuance operation. Only that issuing request may save it. Existing
cookie acquisition requires live, unexpired state and an available lease, then atomically
advances generation and captures payload. Final save matches digest, owner, generation,
revision, unexpired lease/session and staged-or-live state, writes payload, advances
revision and sets live in one update with upsert=false. Retirement applies the same
lease/revision/expiry conditions, increments counters, clears payload/owner and leaves
a revoked tombstone. Replacement is a new staged ID; old retirement is never undone.
All acknowledged writes require majority durability with actual deployment journaling
and failover evidence; write/read deadlines are mandatory. A preliminary owner check
cannot replace the conditional mutation. No handler-level replay of a request callback.

Effective middleware ordering:

1. Outer SessionTransportBoundary before routing/inner exception rendering.
2. For web/session-cookie bootstrap: EncryptCookies -> AddQueuedCookiesToResponse ->
   FencedStartSession -> PreventRequestForgery -> AuthenticateSessionIdentity ->
   route authentication -> trusted ResourceScope resolution -> EnforceCurrentAuthorization.
   Standard framework middleware may remain where compatible; route bindings used
   to resolve resources must finish before authorization and enforce their own scope.
3. For API: EnsureFrontendRequestsAreStateful first classifies the approved exact
   frontend hosts. Its installed frontend chain is encryption, queued cookies,
   native StartSession binding (now gated FencedStartSession), CSRF and configured
   AuthenticateSessionIdentity. Then the route's authentication/resource/authorization.
   Do not add web to these API routes or start a second session. Non-first-party
   requests get no session hydration; bearer fallback stays disabled and protected
   access is denied. A forged/missing Origin/Referer never proves identity.
4. Bind the same lifecycle Store into request session, session.store, SessionManager
   driver, Session facade and auth.driver before ANY identity resolution, including
   Sanctum eager hydration. Forget guard/driver/facade caches at request boundaries.
   Snapshot/restore temporary bindings and release in finally on every exit.
5. Inspect the returned/rendered response AND attached exception before saving.
   A downstream 5xx skips payload persistence and all session/XSRF success cookies,
   clears guard bindings/releases the lease and preserves the original failure
   status; a staged replacement remains for reconciliation and prior retirement is
   never undone. Inner-rendered typed transport errors follow the same sanitized
   failure path. For an eligible non-5xx response, save successfully and release
   successfully before permitting response publication and its session cookie.
   Wholly replaced failure responses must not
   inherit any staged session/XSRF success cookies. Expiring a rejected cookie is
   allowed as a deliberate cleanup action; never publish a replacement as success.

This design initially supports request-isolated application lifecycles. No Octane/
concurrent-container, streaming, long-poll or precognitive session route is enabled
until its own scoped-lifetime/final-save behavior is proved. Merely using scoped()
does not establish concurrency safety. Existing '/sanctum/csrf-cookie' is included
in the web-path proof. Sanctum's exact host classifier is a browser/session boundary,
not an authorization decision; existing server-side checks remain mandatory.

Reporting/error contract: wrap transport driver exceptions inside the handler as
typed SessionTransportUnavailable; allowlisted logs contain only class, static code,
operation/correlation UUID and approved nonsecret metadata. Register report callbacks
which stop default detailed reporting for these typed exceptions BEFORE inner routing
can report them. Do not blanket-suppress all MongoDB/application errors. No exception
message/trace arguments, DSN, cookies, raw payload, credential or proof logged. Return
generic 503 security_state_unavailable for uncertainty; known invalid/revoked cookies
401 authentication_required; contention/stale-save 409 session_conflict. Preserve
unrelated 404/422/5xx and current authorization 401/403. Native CSRF rejection is 419;
419/409 code/schema mappings need an explicit reviewed OpenAPI delta before any new
mutation route is exposed. Source OpenAPI is not changed by this design.

An HTTP/session failure does not roll back a business mutation already committed
downstream. Withhold cookies/response success, record a safe operation reference,
and require that future mutation handlers use reviewed idempotency/outcome lookup
before retries. Never automatically run a controller again after uncertain save.

Reconciliation: a process crash before save can leave staged data and a local security
record. Enumerate only staged envelopes with expired creator leases, then atomically
match that same lifecycle/generation/revision/lease before tombstoning. Map any owned
user_sessions record by the exact unique session_digest; revoke through Task B first,
then journal/audit locally. Unknown ownership, authority outage or changed generation
denies repair/access; report for review rather than delete. Keep all tombstones/evidence.
Once an envelope is live, HTTP delivery cannot be inferred from storage: a response
may have been lost. Never label a live envelope orphaned or delete/revoke it solely
because cookie delivery is unconfirmed. Its expiry and current authority still apply.

Future activation tests (not claimed executed): actual middleware inventory has one
session start on each path; allowed/forged/missing frontend headers and bearer-only
requests; CSRF cookie/missing/wrong token; real guard rotation/logout/helper/cache
isolation; invalid/missing IDs cannot upsert; encrypted payload swapping fails; stale
save AND retirement cannot affect successor; crash-staged repair cannot race a live
save; released/expired lease cases; backend failure at read/save/retire/release;
lost acknowledgment leaves outcome unknown and no controller replay; a successful
guard/login mutation followed by a downstream thrown/rendered 500 cannot save its
identity or publish a success cookie; sanitized log
sink and cookie headers on every error; business idempotency; two isolated app
instances and deployment failover. Keep original counterexamples as negative controls.

Activation/rollback: inspect proposed metadata diff, pin config/provider/middleware
manifest, validate in a dedicated synthetic environment, obtain transport/authority/
operational ownership and security acceptance, then schedule an explicit cutover with
a new cookie namespace and reauthentication. Do not carry old file/serialized sessions
forward. Emergency repair first disables affected business routes, keeps independent
revocations and reconciles outcome-unknown operations. Rolling back code must not
drop collections/tombstones, undo authority acknowledgments or restore old cookies;
file sessions remain synthetic-only. Reopen privileges only after current trust is proved.

##### Task B — independent authority receipt and recovery contract

Current limitation is exact: SecurityStateAuthority::revoke returns void;
AuthorityEvidence has no authenticated issuer/checkpoint/version proof; repository
revokeWithAudit writes no security_decision_journal receipt and is not yet retry-idempotent
after local success. Leave all three unchanged until concrete adapter implementation
is separately authorized. A proposed AcknowledgedSecurityStateAuthority port replaces
the call boundary in a future version; it does not extend/override the incompatible
void method or treat the current memory fake as an implementation of durability.

Proposed methods and DTOs (provider-neutral signatures, not new PHP files):

```php
evidence(CurrentAuthorityQuery $query): CurrentAuthorityEvidence;
revoke(RevocationCommand $command): RevocationReceipt;
lookupOperation(string $authorityNamespace, string $operationId): OperationStatus;
changes(AuthorityCursor $after, int $limit): AuthorityChangePage;
checkpoint(string $authorityNamespace): AuthorityCheckpoint;
```

CurrentAuthorityQuery binds configured authority namespace, UUID subject, 64-hex
session digest, current account epoch and authorization digest, trusted minimum
checkpoint/version, and a fresh unpredictable server nonce. The adapter authenticates
the configured service/root of trust, not a caller-selected URL/key/algorithm. Response
must echo query binding/nonce, report CURRENT state, issuer generation, subject version,
checkpoint and credentials/reauthentication/revocation facts. Verify canonical proof,
configured key/algorithm, validity/time bounds and current read guarantee before allow.
No positive evidence cache is accepted just because its timestamp/signature is valid.
Receipt/nonce freshness alone cannot repair an authority reading stale internal replicas.
Concurrent revocation can still occur after an access check: high-risk mutation designs
must define authority-version revalidation at their durable action boundary; this
contract does not invent a transaction across independent services.

RevocationCommand's exact properties are purpose (string
studentity.session-revocation.command.v1), schemaVersion (JSON integer 1), operationId,
authorityNamespace, subjectUserId, sessionDigest, securityEpoch, authorizationDigest,
action, expectedIssuerGeneration, expectedCheckpointRef and correlationId (all strings).
IDs/correlation use server-generated lowercase UUIDv4; session/authorization digests
are lowercase 64-hex; securityEpoch is a canonical nonnegative BSON-long-bounded
decimal string; action is session.revoke. The remaining strings are configured/
authenticated authority references, never caller-selected endpoints. Normalize
once into canonical request bytes and SHA-256 requestFingerprint; no raw ID, credential
or full user record. Same namespace/operationId plus same fingerprint returns the SAME
durable receipt, including after disconnect/restart. Same key plus different fingerprint
is a conflict with no new side effect. Different operations revoking an already-revoked
session remain monotonically denied; each operation can have its own stable receipt.
Account/grant/credential/recovery changes need separately typed, authorized commands;
do not generalize this session operation into a permission-grant API.

| RevocationReceipt field | Contract |
|---|---|
| purpose; schemaVersion; operationId; receiptId | purpose studentity.session-revocation.receipt.v1; integer version 1; operation UUID echoed; globally unique UUIDv4 receipt reference |
| authorityNamespace; issuerGeneration; signingKeyId | preconfigured namespace; nonreused generation; authenticated allowlisted key ID |
| subjectUserId; sessionDigest; action; requestFingerprint | exact command binding, action session.revoke and canonical-request digest |
| correlationId | original command's correlation UUID, signed/echoed and stable across retries/recovery |
| securityEpoch; subjectVersion; checkpointRef | nonnegative integer versions encoded as canonical decimal strings bounded to BSON long; genuine independently verifiable committed checkpoint reference |
| committedAt; result | canonical UTC timestamp; irrevocably committed revoked result (not pending/accepted/queued) |
| proof | authenticated canonical receipt proof under approved trust/algorithm; never a client-supplied trust root |

Canonical encoding uses RFC 8785 JCS over the exact named receipt fields above,
excluding proof; reject unknown/duplicate properties rather than silently omit them.
All receipt values are strings except schemaVersion, the JSON integer 1. Versions/
epochs are decimal strings without leading zeroes, UUID/hex fields are lowercase,
and committedAt uses fixed three-fraction RFC3339 UTC Z form (exact BSON millisecond
precision, so reconstructing the stored timestamp cannot change the audit hash). JCS defines property
ordering/escaping and performs no Unicode normalization. The command fingerprint
uses the same JCS rules over all named command fields, with purpose
studentity.session-revocation.command.v1 and schemaVersion=1; no proof/fingerprint
field hashes itself. Cross-language golden vectors must pin exact bytes before
implementation. Hash canonical bytes, not a driver's BSON/JSON representation.
Proof transport/authentication, key custody/rotation and numeric operational limits
are owner decisions; reject configuration that has not resolved them. Opaque authority
and checkpoint references are data, never automatically followed as arbitrary URLs.

Receipt validity proves an acknowledged denial was committed; it NEVER supplies
current positive authorization. Signed historical receipts remain audit evidence
under approved retired-key verification, while only the configured current issuer
generation/checkpoint may establish live trust. Missing/offline/unknown/rollbacked
authority is unavailable, not an unrevoked account. Versions/checkpoints cannot
decrease or be reset by restoring MongoDB or restarting the authority. Authority
recovery needs independent, authenticated continuity of revocations, compromised
credentials and pending reauthentication; without it, fence affected access. A new
generation is not permission to forget old denial. No receipt/journal TTL is proposed.

The independent deployment must prove durable idempotent operation storage and
current-state ordering across its actual failures/restores. A second collection or
DB restored from the same MongoDB backup fails this independence requirement.
MongoDB majority reads by themselves are not a real-time-currentness proof; its
documented linearizable option is limited to supported uniquely addressed single-
document primary reads and requires deadlines. Do not apply that label to the existing
separate users/session/grants reads or to arbitrary transactions. The concrete
authority must specify and test an equivalent current-state guarantee, not inherit
one from this abstract contract.

Receipt-to-local commit protocol, proposed within the existing journal schema:

1. Establish current authenticated subject/session and operation binding. Send the
   command to the independent authority. Before verified durable receipt, do not
   mark local revocation confirmed or fabricate anchored journal references.
2. Verify the receipt. In ONE local transaction, mark the matching user_sessions
   revoked, insert immutable security_decision_journal and a revocation audit event.
   If already locally revoked, continue the same idempotent journal/audit completion;
   do not turn an already-revoked predicate miss into failed security recovery.
3. Journal _id = operationId (UUIDv4), subject_user_id = receipt subject,
   event_kind = session.revoked, authority_ref = verified receipt reference within
   its configured namespace/generation, recovery_anchor_ref = actual checkpointRef,
   event_hash = Binary(SHA256(canonical receipt bytes)), occurred_at = committedAt,
   schema_version = 1 and external_anchor_status = anchored. No placeholder anchors,
   raw cookie, token or unverified request content. Existing ix_security_history
   remains; _id supplies local idempotency, so no journal-validator/index edit needed.
4. Audit _id also uses operationId in its separate collection. Its correlation_id
   is the ORIGINAL signed receipt.correlationId, never a retry/reconciler UUID;
   occurred_at is receipt.committedAt, never the repair worker's current time.
   The deterministic document uses action=session.revocation.confirmed,
   target_type=user_session and schema_version=1. Omit optional actor_user_id:
   the receipt proves an affected subject, not an initiating human; staged cleanup
   can be system-initiated. Use event_redacted with exactly
   subject_user_id=receipt.subjectUserId, hash_scheme=jcs-receipt-audit-v1,
   ability=session.revoke, allowed=true, reason=revoked, journal_id=operationId,
   receipt_hash=lowercase SHA256 canonical receipt bytes and security_epoch equal
   to the receipt's decimal string. No variable local target ID/worker identity
   enters that original event. event_hash is Binary(SHA256(JCS of this full audit
   document excluding event_hash, with occurred_at represented by the receipt's
   canonical UTC string))); persist the same time as BSON Date. Compare that exact
   projection/hash on retry. Refactor the recorder with an explicit receipt method;
   do not change random-ID general access events or their existing hash semantics.
   Identical existing journal/audit records are success; different hash/binding for
   the same operation is a conflict and remains denied. A repair attempt may have
   its own separately keyed operational event; it never rewrites the original event.
   Original request authorization/actor provenance must be separately evidenced from
   the verified caller and correlation; do not infer a human actor from the affected
   subject. A future requirement to carry initiator identity in receipts needs an
   explicitly signed/authorized actor contract, not an invented repair-worker actor.
5. Guard logout/forgetUser, fenced transport retirement, CSRF rotation and browser
   cleanup still run independently on every outcome. Report success only with
   verified authority acknowledgment and the required local transaction confirmed.
   On uncertainty return generic 503; neither retry nor rollback can un-revoke.

The full authenticated receipt stays durably retrievable by immutable reference
at the independent authority; the local hash/reference is linkage, not a signed
immutable local chain. If external proof cannot be retrieved/verified later, recovery
does not infer current trust from the journal's anchored string or event hash alone.
Current schema permits the mapped fields; implementation must test their exact BSON
types and transaction idempotency against the validated synthetic schema.

| Failure / repair acceptance case | Required outcome |
|---|---|
| Authority offline before commit | 503; browser/transport cleanup attempted; no confirmed local revocation/anchored journal claimed |
| Authority commits, response/ACK lost | operation status initially unknown; lookup SAME operationId/fingerprint or independent change-feed recovery; never undo denial or replay a business action |
| Forged receipt, wrong binding/version/key, stale evidence nonce or reused operation with changed request | reject/fail closed; no journal marked anchored; static sanitized diagnostic |
| Receipt verified, local transaction fails | independent revocation remains; journal/audit absent or old transaction intact; reconcile from durable authority operations |
| Local commit result uncertain / process restart | read existing operation IDs with suitable consistency and verify hashes, then complete missing work idempotently; no new audit for the same operation |
| Local journal/control/outbox/cursor restored older | enumerate authoritative changes from a verified checkpoint, including operations absent locally; local missing records never erase external denial |
| Authority rollback, cursor gap, old issuer generation or unknown checkpoint | isolate affected access and require trusted continuity/reconciliation; no positive cached fallback |
| Repair sees a missing local subject/session | record the verified independent denial/history for review without creating an active session/grant; refuse unsafe upsert |

OperationStatus is a discriminated committed(receipt), definitivelyNotCommitted or
unknown. Only a current authenticated authority can assert definitivelyNotCommitted;
timeouts/404 from an intermediary are unknown. Retrying a definitive noncommit uses
the same key and fingerprint. Change pages include namespace/generation, strictly
ordered operation positions, receipts and an authenticated next cursor/checkpoint.
The cursor/checkpoint must prove continuity; empty pages and restored local cursors
are not evidence of completeness. Authority must support complete replay or an
authenticated baseline plus subsequent gap-free changes, including deny state whose
local journal never existed. Data retention and replay horizon need owner approval;
the client must fail closed when continuity exceeds that horizon.

Recovery gate: quarantine affected access, verify authority/key/checkpoint continuity,
reconcile through a trusted barrier and then recheck CURRENT evidence on each decision
to cover later changes. Invalidate affected coordinator/admin sessions and enforce
reauthentication plus applicable MFA; students survive only if account/credential/
revocation/grant state is current and trustworthy. No bulk timestamp reset or automatic
grant reactivation. Preserve decisions/evidence and require approved recovery sign-off.

Required owner decisions before activation (unassigned; these are responsibilities,
not additional application roles): deployment/security owner and independently
authenticated failure/backup domain; key custody/rotation and trust bootstrap;
durability/current-read/failover guarantees; operation/change-feed replay and approved
retention/disposal; session lifetimes, bounded deadlines, consistency/performance
targets and request runtime; incident/reconciliation operator and recovery approval.
Provider, region, budget, real credentials and institution policy are not supplied
by this design. UnavailableSecurityStateAuthority remains the actual default binding.

Implementation tasks completed in the slice below:

1. Add a test-only full Sanctum stateful-middleware activation harness using Task A's
   single native StartSession binding; prove exact first-party classification/order,
   guard/helper/cache behavior and sanitized error reporting. No production enablement.
2. Add provider-neutral receipt DTO/canonicalization/verification and idempotency
   contract tests against a clearly labeled synthetic authority; prove invalid binding,
   duplicate/conflicting operations, lost-ACK status and journal BSON mapping. Do not
   claim that a fake establishes durability or change the active void port/provider.

Independent read-only design review completed with no remaining Critical, Important
or Minor findings. Two Important design gaps were corrected during review: explicitly
skip persistence/success cookies after downstream rendered 5xx, and bind immutable
retry audit fields to signed original correlation/commit time with deterministic
projection/hash rules. Minor clarifications specify string encryption APIs without
unserialization, exact command/JCS types, payload-absent staged state, and separation
of affected subject from initiating human actor. Canonical timestamps use BSON-exact
millisecond precision. No runtime fix or test result is implied by these doc changes.
The reviewer consulted SRS, both sibling references, installed framework APIs and
actual journal/session validators. Runtime activation, real durable authority and
operational/provider/credential policies remain explicit unwaived gates above.

Preservation verification: all 110 inventoried existing repository files remain
present, with only this handoff modified and no new source file; the eight saved
protected/config/dependency/catalogue hashes and five immutable source hashes match.
Git diff --check passes. No runtime tests, MongoDB writes, dependency install,
configuration change, staging or commit occurred. The independent reviewer also
recomputed preservation hashes without modifying files or running tests/DB actions.

Plan verification: map each matrix row to a named negative test in the future files;
run scoped tests red/green, full suite and schema/hash/route checks only when runtime
work begins. This design pass requires source/preservation/diff review, not tests
which assert prose or a misleading rerun claimed to validate unimplemented behavior.
Evidence/rollback: storage/app/private/activation-authority-design-2026-11-18/ holds
the pre-design handoff and a 110-file repository hash inventory. Revert only this
section/top date after diff review if requested; no code or database rollback exists.

Primary references checked 2026-11-18: [Laravel Sanctum first-party session/CSRF](https://laravel.com/framework/docs/sanctum),
[MongoDB linearizable read limits](https://www.mongodb.com/docs/manual/reference/read-concern-linearizable/),
[RFC 8785 canonical JSON](https://www.rfc-editor.org/rfc/rfc8785).
Installed vendor code remains the source for exact 13.42.0/4.4.0 middleware behavior;
current web manuals do not change locked versions or establish deployment guarantees.

#### Stateful middleware and receipt implementation — execution plan, 2026-11-18

Owner authorized the next two bounded implementation tasks above. Execute inline
on master with test-first gates, one final read-only review and no automatic commit.

- [x] Task 1: tests/Feature/SanctumFencedSessionActivationLabTest.php and test-only
  native StartSession bridge/outer boundary. Use actual api/web/Sanctum middleware,
  existing guarded fixture/transport helpers, real CSRF, first-party classifier,
  cache/store/guard checks and sanitized reporting. No application provider/config
  activation, new transport schema or public route. Reuse the existing lab database.
- [x] Task 2: isolated app/Domain/Security/Authority/ command/receipt/canonical JSON/
  verified mapping DTOs and provider-neutral verification. Tests/Support supplies
  ONLY a synthetic in-memory idempotent authority with ephemeral test proof key.
  Unit tests pin canonical bytes/fingerprint, malformed/boundary input and proof/
  binding rejection, conflict/retry/lost-ACK status. Feature tests validate mapped
  journal/audit BSON against exact local studentity_test_m0 using generated IDs.
  No new live port, provider, credentials or claimed durable external service.
- [x] Scoped/full tests, Pint, schema/hash/route verification and independent review.

Files: new tests/Support/StatefulSessionLabStart.php,
StatefulSessionLabBoundary.php, SessionLabTransportUnavailable.php; new authority
DTOs and tests/Support/ReceiptAuthorityLab.php, tests/Unit/AuthorityReceiptContractTest.php,
tests/Feature/AuthorityReceiptJournalLabTest.php. Existing app/lab files remain
unchanged unless a reproduced defect requires a separately snapshotted repair.
Task 1's response/trace/report hooks are test instrumentation, not selected production
observability. Task 2's proof callback is trusted server configuration; no algorithm,
key, authority endpoint or verification callback comes from receipt/request input.
Canonicalization supports JCS strings/objects/arrays/Booleans and safe integer input;
floats and unsafe integers are rejected, with versions represented as decimal strings.
Canonical wire encoding is required at the DTO JSON ingestion boundary to reject
duplicate keys without relying on PHP's lossy JSON object decoding. This is a stricter
wire subset, not a claim that general noncanonical JCS input is accepted.

Evidence: storage/app/private/stateful-receipts-2026-11-18/. Rollback removes only
new files identified by the final manifest after diff review and reverts this section;
retain unrelated dirty work, protected/source files, unknown database records and
authoritative revocations. Fixture cleanup uses generated IDs only, no drops/TTL.

Execution result: Task 1 adds **8 passed / 64 assertions** through the installed
Sanctum api group and native web CSRF-cookie route, with the single StartSession
binding bridging the existing isolated fenced handler and guard-binding helper.
Actual CSRF stops before identity/route execution; first-party headers hydrate once,
missing/forged frontend headers deny protected access without hydration, and disabled
bearer fallback stays denied. Helpers/current authorization/logout use that chain.
A typed failure after native guard mutation is sanitized in body, log sink and
cookie headers; a downstream 500 leaves no saved guard identity or success cookies.
The trace/identity wrapper delegates the actual installed identity middleware.

Task 2 adds **32 passed / 64 assertions** (30 unit cases plus two real-MongoDB cases),
including the final two review regressions. Six new isolated domain files provide
closed scalar command/receipt schemas, a JCS subset encoder, canonical wire ingestion,
strict proof/issuer/key/command/clock binding and verified-only journal/audit mapping.
These types have no application/provider/route registration and expose no current
positive-authority decision. Trusted proof callbacks and clock/key/issuer policy must
be supplied by server-side configuration. The only proof implementation added is
the test-namespace memory model with an ephemeral HMAC key, not a vendor selection.

Canonical vectors pin exact command bytes and the SHA-256 fingerprint
1add097165400fc1bd655319e952e39bd16185fed1105df9a2b92f187c882045,
independently checked with Node's JSON/crypto for the ASCII command vector. Other
tests pin UTF-16 property ordering, escapes and decimal BSON-long boundaries. Wire
decoding requires exact canonical bytes: whitespace/ordering variants and duplicate
properties are rejected rather than accepted after PHP's lossy member decoding.
Candidate protocol bounds are 16 KiB wire, namespace 64 characters, other scalar
fields 256 bytes and proof 2,048 bytes, with reference syntax explicitly restricted;
these are defensive DTO bounds, not an institutional retention/deadline policy.

The memory authority models identical/conflicting operation IDs, distinct repeated
denials and commit-before-lost-ACK lookup. It cannot prove persistence, process
restart, cursor continuity, failover or external independence. The verified mapper
preserves original signed correlation and millisecond commit time, excludes an
unproven human actor, and emits deterministic hashes. Real local validated journal/
audit inserts commit together under snapshot/primary/majority transaction settings;
duplicate journal IDs assert code 11000 and invalid audit rollback asserts code 121.
This is mapping/transaction evidence, not a runtime idempotent reconciliation service.

Test-first/review record: eight missing-bridge cases were run red before Task 1.
Task 2 missing types were run red; an initially absent expected-exception type
confused the reporter until that exception stub existed. Initial implementation
passed 24/25 unit cases; a backslash in the hand-written expected JSON literal was
corrected (encoder output was valid). A NUL-byte timestamp regression then reproduced
PHP's raw ValueError; a strict timestamp shape check now emits the sanitized contract
exception. Independent review found one Important integrity defect: readonly arrays
retain caller-owned scalar references. Both DTO constructors now copy scalar values
before validation/assignment. Two separate tests failed before that correction and
pass afterward (4 assertions), protecting command fingerprints and verified journal
subject/hash from post-verification mutation. No review defect remains deferred.

Changed-file manifest (all new except this handoff):

| Files | Responsibility |
|---|---|
| app/Domain/Security/Authority/{CanonicalAuthorityJson,ReceiptSchema,ReceiptContractViolation,RevocationCommand,RevocationReceipt,VerifiedRevocationReceipt}.php | Provider-neutral immutable receipt contract and BSON mapping; active v1 port untouched |
| tests/Support/{StatefulSessionLabStart,StatefulSessionLabBoundary,SessionLabTransportUnavailable}.php | Full-chain native binding, test-only error/report/cookie boundary |
| tests/Support/{AuthorityReceiptFixtures,ReceiptAuthorityLab}.php | Synthetic vector/memory acknowledgment model only |
| tests/Feature/SanctumFencedSessionActivationLabTest.php | Eight actual-chain cases |
| tests/Unit/AuthorityReceiptContractTest.php; tests/Feature/AuthorityReceiptJournalLabTest.php | Thirty unit cases and two schema/transaction cases |
| docs/handoffs/handout_codex.md | Unified execution/results/limits/next tasks |

Final scoped tests: **40 passed / 128 assertions**. Post-review full suite:
**160 passed / 2,313 assertions / zero failures or skips**, 61.70 seconds. The
pre-review 158/2,309 run is superseded by that fresh run including both alias regressions.
Scoped Pint and diff checks pass.
Both synthetic business databases still match 62 validators / 128 catalogue indexes;
all 110 pre-slice repository files remain present, with only this handoff modified.
The fourteen new files are recorded in file-manifest.json; all preexisting runtime/
test/protected files, configs, lockfiles, accepted catalogue/migration and five source
references retain their hashes. Normal route inventory contains no test routes.
No dependency installation, schema migration, TTL, broad cleanup, staging or commit
occurred. Frontend files did not change; Vite was not rerun for this PHP-only slice.

Independent read-only review inspected all fourteen files, approved plan/SRS/ADR
constraints, native middleware/pipeline behavior and validators. It verified the
110-file preservation inventory without rerunning tests or writing files/DB records.
After inspecting the reference-alias correction it reported no remaining Critical,
Important or Minor findings. Runtime activation, real durable authority/currentness,
deployment recovery and operational approval remain unwaived requirements.

Limits: no new encrypted/staged transport schema was implemented; Task 1 deliberately
reuses the old isolated lab envelope. Log proof covers the new typed exception, not
arbitrary framework/application errors. Receipt verification is a pure binding/proof
contract and does not establish a trustworthy external checkpoint/operation ledger,
serve current authorization, reconcile local state, or automatically retry mutations.
No active void authority port, default unavailable provider or production session
configuration changed. Registration, credentials/MFA delivery, business endpoints,
independent recovery and operational retention/disposal remain separate gates.

Evidence directory above contains initial red/green attempts, timestamp-boundary-red,
review-alias-red/green, scoped-green, full-tests before/after review, Pint/schema/route
results, workspace-before/preservation-check and exact file manifest. Rollback removes
only those fourteen new files after diff review and reverts this section/top status;
no application configuration or database rollback is required. Preserve unrelated
dirty work and independent revocations.

Completed in the following slice: (1) implement a synthetic receipt-journal transaction/replay
service that completes duplicate/partial/uncertain local outcomes idempotently and
rejects conflicting immutable hashes, using verified receipts and owned MongoDB
fixtures; leave the active authority port/provider unchanged. (2) implement the
encrypted staged/live/revoked transport candidate in a dedicated synthetic activation
database, with strict additive lab validator, digest IDs, safe string encryption,
stale-writer rejection and staged repair tests; do not enable a production driver,
modify the accepted 62-collection model or select a real authority/provider.

#### Receipt replay and encrypted transport — execution plan, 2026-11-18

Owner authorized the next development plan. Continue the documented pair inline
on master, using synthetic fixtures and test-first gates; no active provider/port,
business route, accepted catalogue or application session configuration changes.

- [x] Receipt replay: add tests/Support/ReceiptJournalReplayLab.php and
  tests/Feature/ReceiptJournalReplayLabTest.php. Transactionally ensure immutable
  verified journal/audit records and local session revocation; identical repeats
  succeed, missing pieces are completed, conflicting bodies/types reject without
  rewriting history. Subject/epoch mismatch refuses; missing local session records
  permit history only, never an active upsert. Hooks simulate pre-commit abort and
  lost ACK after commit; recovery replays verified STORAGE work only, never a
  controller or independent authority command. Fixed local studentity_test_m0.
- [x] Encrypted transport: add tests/Support/EncryptedSessionLabSchema.php,
  EncryptedSessionLabStore.php and tests/Feature/EncryptedSessionTransportLabTest.php.
  Fixed local studentity_test_session_activation.envelopes_v1; strict/error validator,
  digest IDs, staged/live/revoked states, encrypted digest-bound JSON, majority CAS,
  server-time lease/expiry, counter overflow guards, fenced retirement/release and
  version-matched expired-staged repair. No GC/TTL or broad cleanup. No HTTP activation.
- [x] Scoped/full tests, formatting, schema/source/protected preservation and final
  independent read-only review; record results and remaining gates here.

Rulings: replay compares full canonical EXTENDED BSON, not just an untrusted stored
hash; BSON-long/date types matter. Recovery does not overwrite an existing revoked_at.
Unknown commit outcomes return an explicit uncertain failure and can be reconciled
by a fresh idempotent call; no general automatic retry policy is invented. Candidate
transport creation is explicit server issuance (the only upsert-capable operation);
the new random issuance UUID filter plus unique _id prevents recycling existing IDs.
Normal cookie reads/final saves never upsert. Lab-only lease/lifetime are 10/600 seconds,
not production timeout/retention decisions. New metadata is additive and retained
empty after owned-ID teardown; existing mismatched metadata is refused, not collMod'ed.
Staged transport repair here tombstones owned envelopes only; coupling to independent
revocation/security-record cleanup remains an explicit later integration gate.

Evidence: storage/app/private/replay-encrypted-transport-2026-11-18/ stores the
pre-slice handoff and existing-file hash inventory. Rollback removes only the new
test/support files after diff review and reverts this section/top status. No application
or database rollback/drop is required; preserve empty lab metadata, unrelated dirty
work, protected/source files and independently acknowledged revocations.

Execution results: both tasks are implemented only in the test namespace. Existing
domain DTOs, application middleware/providers/configuration, active void authority
port, default unavailable authority, accepted catalogue/migration, dependencies and
all earlier labs remain unchanged.

| New file | Responsibility |
|---|---|
| tests/Support/ReceiptJournalReplayLab.php | Snapshot/primary/majority local transaction, verified denial reconciliation, complete typed BSON comparison and explicit uncertain result |
| tests/Feature/ReceiptJournalReplayLabTest.php | Duplicate/partial/restored records, control conflicts, immutable-body/type conflicts, preserved revocation time, abort/lost-ACK recovery, history-only and target guards |
| tests/Support/EncryptedSessionLabSchema.php | Additive fixed-local validator/index setup and closed metadata rejection before writes; no collMod/drop |
| tests/Support/EncryptedSessionLabStore.php | Digest-keyed staged/live/revoked envelopes with one string-encryption layer, server-time CAS and staged repair |
| tests/Feature/EncryptedSessionTransportLabTest.php | Issuance/read/save/retirement/encryption/swap/lease/expiry/overflow/repair/validator/metadata negative cases |

Receipt replay compares immutable FULL documents using the installed driver's
Document::fromPHP()->toCanonicalExtendedJSON() followed by canonical JSON, preserving
date/binary/number types and ignoring field order. A matching stored hash alone never
authorizes overwriting a conflicting body. Both journal and audit are checked; an
otherwise equivalent epoch stored as BSON long instead of the signed projection's
string is rejected. Local revocation and missing immutable records commit together.
Already-revoked control keeps its original revoked_at, including an earlier timestamp;
missing control yields history-only without any session upsert. Wrong subject/epoch
aborts. New calls may replay verified local storage work after lost acknowledgment;
no authority request or controller is executed/retried by this service.

Transport metadata was added ONLY in
studentity_test_session_activation.envelopes_v1. It has a strict/error validator
with staged/live/revoked oneOf shapes and BSON long/date/UUID/digest constraints;
indexes are _id_, ix_transport_staged and ix_transport_expiry, all non-TTL.
The raw session ID is never persisted; _id is its SHA-256 digest. Explicit issuance
uses a new issuance-operation UUID and unique _id to reject reuse, acquiring server-
time lease state without storing a payload. A staged envelope cannot be read by a
cookie; only its issuing handler can publish it with a current conditional save.
All normal reads/saves/retirements/repairs refuse implicit creation. Encryption uses
Laravel encryptString/decryptString and explicit JSON containing schema version,
bound digest and payload text; wrong keys/copied ciphertext are rejected, with no
PHP unserialize path. Counter limits fail closed before BSON-long overflow.

Takeover cases show old writers cannot save, retire or clear a successor's ownership.
Staged cleanup matches the exact generation/revision and an expired creator lease;
it cannot tombstone a published live envelope. Retirement preserves an opaque-ID
tombstone without payload. GC is a no-op. Lab-only 10-second leases/600-second
lifetimes and ephemeral cipher keys are not production policies. Owned test IDs are
removed after closing handlers; empty lab metadata remains, never dropped.

Test-first record: 11 initial receipt and 11 transport cases failed with their
implementations absent. Receipt replay initially used removed free BSON functions;
installed extension 2.5 uses the Document API above. Transport initially passed ten
cases and exposed a collision exception taxonomy mismatch: findAndModify raises a
driver CommandException for code 11000, rather than BulkWriteException. It now maps
driver code 11000 to a static rejection while preserving other errors. No dependency
or schema relaxation was used to resolve either issue.

Review found one Important metadata defect: checking only validators/modes could
accept a capped collection (automatic eviction would defeat tombstones) or modified
index semantics. Read-only metadata guards were extracted; six fixture cases first
failed, then passed after requiring an ordinary collection with ONLY the expected
validator/mode/action options and closed index-option allowlists. Unknown capped,
collation/time-series, hidden/storage-engine and other modifiers are refused BEFORE
index addition/transport writes. The pinned driver's public IndexInfo metadata
snapshot is inspected so unknown flags cannot be silently ignored. Tests use metadata
fixtures; no capped collection or altered unknown metadata was created. The Minor
review request for BSON-type conflict coverage is also implemented. No validator,
index or source document was rewritten as part of these guard fixes.

Final scoped results: **31 passed / 100 assertions**. Post-review full suite:
**191 passed / 2,413 assertions / zero failures or skips**, 45.91 seconds. The
earlier 184/2,406 run is retained as pre-review evidence, not the final result.

Preservation/verification: all 124 existing repository files remain present and
unchanged except this handoff; the five new files are in file-manifest.json. Both
existing business DBs retain 62 validators / 128 distinct catalogue indexes with no
mismatches. Protected/config/dependency/catalogue hashes and the five immutable
SRS/Blueprint/ADR/invariant reference hashes match. Route inventory contains no
synthetic probes. Scoped Pint and Git diff checks pass; no staging/commit or dependency
installation occurred. No frontend change was made; Vite was not rerun for this
PHP-test-only slice. This adds one isolated lab collection and no accepted-model
or production configuration change.

Independent read-only review covered all five new files, existing DTOs, both sibling
requirements folders, validator/catalogue and saved evidence. It independently
recomputed the 124-file preservation inventory. After checking the metadata guard
fix and BSON regression it reported no remaining findings. The reviewer performed
no mutations, test runs or database actions. The final full-suite rerun is the
implementer's verification, recorded separately below.

Limits: lost commit ACK is simulated after a real local commit; actual network-level
UnknownTransactionCommitResult, competing processes, server failover and durable
external authority continuity remain unproven. Storage replay accepts an already
verified receipt; authentic current trust/checkpoint/cursor recovery is still required
from an approved external adapter. The encrypted candidate is not yet wired to an
HTTP/Laravel lifecycle adapter. It cannot defeat same-digest backup rollback without
independent authority, and staged envelope repair is not yet coordinated with the
security-control cleanup workflow. No operational deletion/retention, key-rotation,
production deadlines, credentials/MFA or provider approval is inferred.

Evidence: storage/app/private/replay-encrypted-transport-2026-11-18/ holds initial
red/green attempts, review-metadata-red/green, scoped-green, full-tests before/after
review, Pint, schema/lab-schema/routes, file-manifest and preservation-check outputs.
Lab metadata evidence recorded zero remaining documents after owned teardown. Rollback
removes only the five new files after diff review and reverts this section/top status;
preserve empty lab metadata and unknown records. No DB or configuration rollback.

Completed in the following slice: (1) connect the encrypted candidate to a test-only Laravel
session lifecycle/native Sanctum harness, proving anonymous issuance, verified
rotation/logout, guard/helper identity and final-save/error cookie behavior without
enabling the application driver. (2) coordinate owned staged-envelope repair with
independent acknowledgment and the verified receipt replay service; test missing/
unknown/failed authority outcomes and partial local completion. Keep the external
authority synthetic, the active port/default provider unchanged and all routes
test-only. Real provider/trust/operational and business-feature gates remain in force.

Reference used for schema keyword verification:
[MongoDB $jsonSchema supported keywords](https://www.mongodb.com/docs/manual/reference/operator/query/jsonschema/).

#### Encrypted HTTP lifecycle and coordinated repair — execution plan, 2026-11-18

Owner authorized the next documented development pair. Execute inline on master
with failing tests first and one fresh final review. Reuse the two guarded local
synthetic databases; no new metadata, production config/provider/port or public route.

- [x] Add tests/Support/EncryptedHttpLabSession.php and EncryptedStatefulLabStart.php,
  tests/Feature/EncryptedSessionLifecycleLabTest.php. Native StartSession binding
  uses the encrypted candidate through actual web/API/Sanctum middleware. Explicit
  newly-issued stages start with empty request attributes; cookie rehydration reads
  live envelopes only. Both migrate modes retire before issuing a new stage, keeping
  attributes in request memory until final save. Prove guard/CSRF/helper behavior,
  logout/rotation/replay and failure-cookie suppression. Snapshot and widen only
  tests/Support/BindFencedLabGuard.php to accept the second approved test Store type;
  use the generic Illuminate Store type in its manager adapter, preserving old cases.
- [x] Add tests/Support/StagedSessionRepairLab.php and
  tests/Feature/StagedSessionRepairLabTest.php. First CAS-tombstone an expired owned
  stage; if live/busy/stale, do NOT contact authority. Resume only the same tombstone
  version/issuance UUID. Stable command operationId equals issuance UUID and binds
  digest/control subject/epoch. Query synthetic operation status, obtain/verify a
  durable-ACK-shaped receipt and replay local storage only. Unknown authority or
  incomplete local commit never reports repaired and never restores transport.
- [x] Scoped/full tests, Pint, schema/hash/route checks and final read-only review;
  record failures/fixes, limits and next tasks in this handoff.

Rulings: the encrypted Store's explicit issue already holds its stage lease; Laravel
must not call normal live-only read for that fresh ID. The bind helper remains
testing-only and accepts exactly the existing fenced and new encrypted Store classes.
Repair must not revoke externally from a stale snapshot before winning transport CAS.
Absent local control and no known command yields transport-only cleanup, not a claim
that no late control can ever appear; retain the tombstone for later reconciliation.
Receipt commands are trusted original job inputs, not reconstructed grants or user
request parameters. This slice does not implement a durable work queue/change feed,
current external trust, or production authority storage/keys.

Evidence: storage/app/private/encrypted-lifecycle-repair-2026-11-18/ contains original
file hashes and the bind-helper/handoff snapshots. Rollback restores only that helper,
removes new files after diff review, and reverts this section/top status. Never reset
master, delete metadata/unknown records, change protected files or undo acknowledged
revocations. Fixture cleanup uses generated IDs only.

Result: **24 new cases / 155 assertions** pass. The complete suite passes
**215 tests / 2,568 assertions / zero failures or skips**, 47.29 seconds. Scoped
Pint and Git diff checks pass. Nine initial HTTP and thirteen repair cases were run
red before implementation; the first implementation runs then passed. Two additional
HTTP cases prove default regenerate mode and end-to-end repair of an actual failed
HTTP login's orphan. No functional review fix was required.

| File | Change in this slice |
|---|---|
| tests/Support/EncryptedHttpLabSession.php | Explicit fresh-stage JSON start; retire-before-issue migration for default/true modes; no implicit ID generation |
| tests/Support/EncryptedStatefulLabStart.php | Test-only native StartSession binding, candidate read/save/release and generic failure-cookie handling |
| tests/Support/BindFencedLabGuard.php | Existing helper accepts exactly FencedHttpLabSession or EncryptedHttpLabSession; manager holds their shared Illuminate Store type |
| tests/Support/StagedSessionRepairLab.php | Expired-stage CAS first, original operation/receipt lookup/verification and local replay coordination |
| tests/Feature/EncryptedSessionLifecycleLabTest.php | Eleven web/API/guard/CSRF/rotation/logout/failure/cache/end-to-end repair cases |
| tests/Feature/StagedSessionRepairLabTest.php | Thirteen eligibility, acknowledgment, local failure, missing/late-control and binding cases |
| docs/handoffs/handout_codex.md | Unified execution/results/limits/next tasks |

The HTTP adapter uses the installed Sanctum API group and actual web CSRF-cookie
route; the application's provider/configuration is untouched. An explicit fresh
stage already owns its lease, so it starts with empty in-memory attributes rather
than attempting a prohibited staged-cookie read. Normal cookie rehydration reads
only live encrypted payloads. Native credential-fixture login preserves attributes,
rotates ID/CSRF, and saves the new stage only on successful final write. Both native
and independently anchored synthetic logout retire the old ID and persist an
anonymous replacement. Helpers, SessionManager/session.store and both guards use
the same Store. Non-first-party or unknown-cookie requests cannot gain identity.
Downstream 500 or expired final save leaves no success cookie/saved guard identity;
staged envelopes remain available for cleanup. Old cookies stay tombstoned.

Repair first wins an expired-stage generation/revision CAS, then resumes only its
exact next-generation/revision tombstone and immutable issuance UUID. No independent
revocation is requested for busy/live/stale input. Commands must match the snapshot
digest and issuance UUID; matching local controls must match subject and epoch.
Scalar task fields are copied before work, avoiding caller-reference aliasing.
Stable original commands/correlation are caller-supplied trusted job inputs, not
inferred current grant authority. Committed operation lookup reuses the original
verified receipt; only a definitive synthetic noncommit can invoke revoke. Unknown
authority/lost acknowledgment returns authority-unknown; aborted/uncertain local
completion returns local-incomplete. Neither path restores the transport or reports
repaired. Subsequent calls complete immutable journal/audit/control state idempotently.

Missing local control plus no known command yields transport-only, not a guarantee
that a late control can never appear. A later inserted owned control is detected on
retry and requires the original command before authority acknowledgment/local cleanup.
Known-command/missing-control completion retains denial history without creating a
session. The HTTP integration case starts with a real guard login followed by a
rendered 500, then uses its actual staged envelope/local control to obtain a synthetic
receipt and complete cleanup. All test routes and proof adapters remain in memory
under Tests; no repair endpoint or worker was registered by the application.

Verification/preservation: all **129 pre-slice repository files** remain present;
only this handoff and the snapshotted test bind helper changed. The five new files
are recorded in file-manifest.json. All protected/config/dependency/catalogue and
five immutable SRS/Blueprint/ADR/invariant reference hashes match. Both existing
business DBs still match 62 validators / 128 distinct catalogue indexes. No transport
validator/index, application port/provider/configuration, dependency, migration or
frontend source changed. Normal route inventory contains no synthetic probes. No
TTL, broad deletion, staging or commit occurred; Vite was not rerun for PHP-only work.

Independent fresh read-only review found no Critical, Important or Minor defects.
It inspected all five new files and the exact helper delta, both reference folders,
installed middleware and saved evidence, and independently recomputed the 129-file
inventory. It performed no mutations, tests or database operations. Exclusions remain
unwaived gates: production activation, durable authority/queue continuity, actual
failover/network-ACK uncertainty, post-restore external currentness, operational
policy and arbitrary downstream exception-log sanitization.

Limits: all integration evidence is from isolated local synthetic fixtures and
in-process kernel requests. Original repair commands live in caller/test memory;
there is no durable work queue, authenticated change-feed cursor or real external
service. Losing that context requires trusted recovery, never manufacturing a new
grant/receipt from restored MongoDB. Same-digest backup rollback, multi-instance
request timing, mid-save/release outages, key custody/rotation, production deadlines
and real credential/MFA delivery remain unproven. A repair status concerns denial
cleanup only and never establishes current positive authorization or institutional
approval. Native logout alone remains distinct from independent revocation.

Evidence: storage/app/private/encrypted-lifecycle-repair-2026-11-18/ contains
http-red/green-attempt, repair-red/green-attempt, scoped-green/full-tests, Pint/schema/
routes, file-manifest/preservation-check and original helper/handoff snapshots.
Rollback restores only the helper and removes only five new files after diff review;
preserve all unrelated dirty work, empty lab metadata and independent revocations.

Next bounded pair: (1) add separate-process verification of the encrypted lifecycle
and repair resumption across application boots, with deterministic owned barriers and
no raw token/key logging; do not claim this is deployment failover. (2) implement
provider-neutral authority checkpoint/change-page contracts and synthetic gap/replay/
issuer-rollback rejection tests, documenting how original command/cursor continuity
would be recovered. Keep the real provider, active port/configuration and business
routes gated until their trust/operational approvals and evidence are supplied.

#### Process verification and authority continuity — execution plan, 2026-11-18

Owner authorized the next bounded pair. Continue inline on master with test-first
gates, unchanged protected work/source requirements and one final read-only review.

- [x] Add a CLI/testing-only encrypted-process worker and feature harness. Separate
  bootstrapped Laravel processes exercise encrypted JSON Store issuance/read/save,
  migration/logout, deterministic held-writer rejection and receipt repair resumption.
  Fixed local synthetic DBs only; existing owned barriers in session_lab. Secrets,
  raw IDs, payloads, receipts and original commands travel only via stdin, never argv
  or output. Workers emit bounded status/counters. Parent owns teardown and stops
  workers before generated-ID-only cleanup. This is process isolation, not two HTTP
  deployments, crash durability or real authority recovery.
- [x] Add provider-neutral AuthorityCheckpoint/AuthorityChangePage schema, policy
  and immutable cursor contracts plus synthetic proof fixtures/unit tests. Pin a
  fresh authenticated checkpoint barrier, replay only from genesis while full history
  is available, verify every consecutive original command/receipt and chained entry,
  and reject gaps/duplicates/forks/nonce replay/issuer or checkpoint rollback. An
  advanced signed barrier cannot skip missing entries. No positive-authorization API.
- [x] Scoped/full tests, Pint, unchanged metadata/schema/hash/route checks and final
  review; record exact evidence/results/limits and next step here.

Files: new tests/Support/encrypted-process-worker.php,
AcknowledgedReceiptAuthorityFixture.php, ReceiptAuthorityLabPort.php and
tests/Feature/EncryptedSessionProcessLabTest.php. Snapshot-only changes to the existing
test ReceiptAuthorityLab/StagedSessionRepairLab introduce that TEST port so a worker
can consume an independently supplied pre-acknowledged fixture without issuing a new
authority command. The application SecurityStateAuthority port/provider stays untouched.
New app/Domain/Security/Authority/Authority{Checkpoint,ChangePage,StreamSchema,StreamPolicy,StreamCursor}.php
and tests/Support/AuthorityStreamFixtures.php, tests/Unit/AuthorityStreamContractTest.php.

Rulings: the worker's resumed authority fixture may report only its known committed
operation or unknown, never infer authoritative noncommit from its incomplete memory.
The original command, signed receipt and trusted synthetic key are supplied by the parent to model
an approved independent lookup; this does not prove a persistent authority. Stream
documents require canonical wire, closed shapes and detached scalar snapshots;
positions are decimal BSON-long-bounded strings. Page hashes bind namespace/generation,
position, prior hash and original command/receipt fingerprints. Cursor advancement
means verified continuity only; local application must complete every receipt before
persisting an applied cursor. No durable cursor store or complete-baseline shortcut
is implemented. Operational trust, replay retention and real provider decisions remain gates.

Evidence: storage/app/private/process-continuity-2026-11-18/ stores pre-slice hashes
and both test-helper/handoff snapshots. Rollback restores those two helpers and removes
only new files after diff review; no database/schema rollback, unknown-record cleanup,
reset, staging or commit. Retain independently acknowledged revocations and lab metadata.

#### Process verification and authority continuity — implementation results, 2026-11-18

Both bounded tasks are implemented on master. Five new process tests pass with
172 assertions; 42 new stream contract cases pass with 63 assertions. The combined
scope including the existing staged-repair tests passes **60 tests / 300 assertions**.
The complete suite passes **262 tests / 2,803 assertions**, no failures or skips,
under PHP 8.4.27 / PHPUnit 12.5.44 in 57.522 seconds. Pint passes for all 13 PHP files
in this slice. Dependencies remain Laravel 13.42.0, Tinker 3.1.0, Sanctum 4.4.0,
MongoDB Laravel 5.12/library 2.5.1/extension 2.5.7, server 8.3.7 rs0 PRIMARY.

Changed prior files: this handoff and two snapshotted TEST helpers. ReceiptAuthorityLab
implements the new ReceiptAuthorityLabPort; StagedSessionRepairLab consumes that
test port rather than its concrete memory implementation. All existing behavior is
retained. Eleven new files are listed in file-manifest.json: five isolated domain
stream contracts, two test suites, one CLI worker and three fixture/interface helpers.
The application's active SecurityStateAuthority contract and unavailable adapter,
providers, routes, auth/session configuration and dependency lockfiles were not changed.

Process evidence: separately bootstrapped Laravel JSON Store instances share only
the fixed encrypted transport collection and supplied synthetic key. Parent-selected
IDs/keys go through bounded stdin rather than process arguments. Fresh workers prove
payload continuity, counter updates, rotation with a new CSRF token, tombstoned old
IDs, logout to an anonymous session, and rejection of a held writer after owned lease
expiry and successor save. A stage without a published payload is repaired using the
original pre-acknowledged command/receipt supplied independently of the local DB;
a post-commit synthetic exception yields local-incomplete and fresh boots reconcile
the same operation to repaired with one journal and one audit event. The fixture's
revoke method always throws, so this resume path cannot issue a replacement command.
Wrong cipher keys and attempted target overrides fail with sanitized output. All
workers are stopped before generated-ID-only teardown. No TTL or collection drop.
Token comparisons use hashes and the leak assertions use static failure messages
so test failures do not print raw synthetic token/key needles. The final full-suite
rerun above includes this assertion hardening.

Continuity rules: checkpoint/page proofs bind a trusted namespace, issuer generation,
configured signing keys, fresh request nonce and server-supplied time window. Shapes
are closed and byte/count/depth bounded; canonical wire rejects duplicate JSON members;
detached nested scalar snapshots prevent reference mutation after validation. Stream
positions remain exact decimal strings through the BSON-long maximum. Each entry
binds its position/prior hash and original command/receipt fingerprints, and each
receipt independently passes the existing command-binding/proof verifier. Tests cover
gap/duplicate/fork, forged page or receipt, mismatched endpoint hashes/refs, stale/future
responses, nonce replay, issuer/checkpoint rollback, truncated history, overflowing or
unsafe shapes, and exact large-number arithmetic.

A signed barrier at position N starts the cursor at genesis, never N. Full original
history must be verified before isFullyVerified() becomes true; empty pages cannot
hide a gap. Refresh to a higher barrier is allowed only after the old barrier is
fully verified, retaining that verified prefix. A test-first refinement added this
rule because otherwise replacing an unfinished barrier could discard its committed
hash and conceal an intermediate fork. Equal-position refresh must preserve both
hash and reference. Returned changes contain the original commands and verified
receipts for local transactional replay; completeness is a cryptographic history
property, never a local-commit acknowledgment or current positive authorization.

Recovery limits: losing the in-memory cursor requires a fresh authenticated barrier
and replay from genesis while minimumPosition is zero. Missing/pruned history denies
recovery until a separately approved complete baseline exists. Neither original
commands nor applied cursors are durably stored by this implementation. A partial
fixture reports only its known committed operation or unknown; it cannot prove
noncommit. HMAC is a test fixture, not a chosen production algorithm/key custody model.
There is no external authority service, new authority command endpoint, durable work
queue/cursor, two HTTP deployments, crash/failover proof or production transport
activation. In-memory operation/nonce sets grow with the verified prefix; a production
feed needs a separately designed bounded durable reconciliation model. Historical
continuity alone never satisfies current authorization after restore.

Preservation checks: all 134 pre-slice source files remain, and only the three intended
paths above changed. All 13 protected/config/dependency/catalogue and immutable
SRS/Blueprint/ADR/invariant hashes match. Both existing application databases retain
62 strict validators and 128 catalogue indexes with no mismatches. Normal route
inventory contains no synthetic probe or repair endpoint. No files were staged or
committed. Vite was not rerun for this PHP-only slice; its prior successful build
remains the frontend baseline. Production gates, identity allocation and real
registration/MFA workflows remain open.

Fresh independent read-only review found no actionable defects. It inspected the
scoped code/plan, independently reran all 42 stream unit cases (63 assertions),
confirmed the exact two helper deltas and pre-slice preservation inventory, and
reviewed process input/output, cleanup, continuity and receipt binding. It ran no
MongoDB mutations or feature tests. The reviewer saw the full-suite log while it
was still running; completion was subsequently confirmed by the parent from the
finished process exit 0 and final 262-test/2,803-assertion result above.

Evidence: storage/app/private/process-continuity-2026-11-18/ contains pre-slice
workspace/helper/handoff snapshots, process-red/green, stream-red/green and
stream-barrier-red (then passing scoped/full) logs, Pint/schema/routes, immutable and
134-file preservation checks, and the exact changed/new file manifest. Retain these
for selective rollback of this slice only; never reset unrelated/protected work or
remove independent revocation evidence.

Next bounded pair for the owner's next continuation: (1) connect verified pages to
the existing transactional receipt-replay helper in a test-only recovery coordinator;
retain the prior applied position on any partial/local-commit uncertainty, reconcile
the same originals and advance only after every page receipt is confirmed. Prove
multi-receipt failure/retry and history-only handling without a durable cursor claim.
(2) run owned synthetic rollback drills using previously captured envelope/control
snapshots and independently supplied revocation fixtures: restored local payloads
must not establish current access; unknown authority remains denied. Preserve all
accepted metadata and avoid real backup/restore/destructive commands. Real provider,
trust/key custody, durable cursor ownership and production activation remain gates.

#### Page recovery and encrypted rollback — execution plan, 2026-11-18

Owner authorized the next documented pair. Continue inline on master, using only
synthetic fixtures and the existing exact local test databases. No production
binding, dependency, schema, route, identity or immutable source changes are planned.
The finalized SRS FR-ACT-020/021, BR-050 and NFR-SEC-014 and approved ADR-02/invariants
were reread from both sibling reference folders before coding.

- [x] Task 1: add tests/Support/AuthorityPageRecoveryLab.php and
  tests/Feature/AuthorityPageRecoveryLabTest.php. Construct a private genesis cursor
  from a trusted checkpoint/policy rather than accept a caller's advanced cursor.
  Verify the whole page before any database replay. Apply original verified receipts
  through ReceiptJournalReplayLab; publish the new applied position only after all
  local controls/history are confirmed. Test multi-page progression, second-receipt
  abort/lost commit acknowledgment, identical-original retry, malformed-page no-write,
  immutable-history conflicts, missing local controls and empty history. Rebuild from
  a fresh genesis checkpoint after losing this in-memory coordinator; no durable claim.
- [x] Task 2: add tests/Support/ReceiptDenyListLabAuthority.php and
  tests/Feature/EncryptedSessionRollbackLabTest.php. Test-only denial overlay consumes
  verified negative receipts and can only restrict the existing current-state fixture.
  Real encrypted middleware/native guards must rehydrate an owned old envelope after
  its envelope/control snapshots are replaced, yet the authorization gate must deny
  known revocation, missing/offline authority, restored old grant scope and externally
  required privileged reauthentication. Reconcile restored local control using the
  original receipt without duplicate history. Replace only generated owned fixture
  IDs, preserve BSON types, never use database backup/restore/drop commands.
- [x] Run scoped/full tests, Pint, schema/routes and protected/reference/source hash
  checks, then one fresh read-only review. Update results and next bounded work here.

Pre-flight: Task 1 consumes the prior immutable stream cursor and receipt replay;
Task 2 consumes the same replay helper plus the existing encrypted native HTTP lab.
Both keep current authorization separate from historical receipt reconciliation.
No existing PHP file needs modification; all four files above are new. A pending
signed response is pinned by its wire hash through failure/history-only retries;
the coordinator rejects response replacement and reentrant application. After loss
of this in-memory context, start a fresh genesis verification instead of importing
an unsigned local position. No production retry deadline/provider is selected.

Ruling: a history-only result holds the applied page position until the original
receipt can also reconcile its local session control — this conservative synthetic
coordinator must not label a missing control as confirmed. Journal/audit history is
still retained and later arrival is reconciled idempotently. Cost: a legitimately
absent control can block this prototype indefinitely; production feed/baseline
semantics remain an explicit design gate, not an invented permission to skip it.

Ruling: the denial overlay delegates current-state availability and never creates
positive evidence from a historical receipt — an offline/missing current authority
still denies even if all page receipts have been locally replayed. The owned snapshot
drill proves application enforcement only, not independent durable service recovery.

Evidence/rollback: storage/app/private/page-recovery-rollback-2026-11-18/ contains
145 pre-slice source hashes and the handoff snapshot. Selective rollback restores
only this handoff and removes the four new files after diff review; preserve all
unrelated dirty work and independently acknowledged revocations. No automatic
staging, commit, cleanup of unknown records, metadata rollback or source-doc edits.

#### Page recovery and encrypted rollback — implementation results, 2026-11-18

Both documented tasks are implemented on master. The two new suites pass
**19 tests / 225 assertions**: 12 recovery cases / 138 assertions and seven encrypted
rollback cases / 87 assertions. The complete suite passes **281 tests / 3,028
assertions**, no failures or skips, under PHP 8.4.27 / PHPUnit 12.5.44 in 60.251
seconds. Pint passes for all four new PHP files. Dependency versions remain
Laravel 13.42.0, Tinker 3.1.0, Sanctum 4.4.0, MongoDB Laravel 5.12 / library 2.5.1 /
extension 2.5.7, server 8.3.7 rs0 PRIMARY. No dependency installation occurred.

The only modified prior source file is this handoff. Four new test files implement
the planned coordinator, negative-evidence overlay and their integration suites;
file-manifest.json lists their exact paths. No existing PHP code, active authority
port/provider, configuration, migration, metadata, route or frontend source changed.
The production unavailable authority and file/JSON session settings remain in force.

AuthorityPageRecoveryLab creates its own private genesis cursor from authenticated
checkpoint inputs and verifies the entire candidate page before the first local
write. Each verified original receipt is reconciled through the existing snapshot /
primary / majority transaction helper. Only after every control/history transaction
is confirmed does it publish the candidate position. Tests prove that aborting the
second receipt or losing its post-commit acknowledgment holds both initial zero and
previously nonzero applied positions. Retrying the original response reconciles the
committed prefix without duplicate journal/audit records. A pending response's wire
hash is pinned, replacement is rejected, and reentrant callbacks cannot publish a
cursor. Invalid second-entry bindings cause no first-entry write; conflicting immutable
history is retained and blocks advancement. Missing controls preserve history-only
evidence but hold the page until an owned late control is reconciled. Losing the
coordinator's PHP memory starts a new verified genesis replay, including previously
applied originals; no unsigned position or durable cursor is imported.

ReceiptDenyListLabAuthority accepts only VerifiedRevocationReceipt objects. It
delegates current-state lookup to the existing synthetic current authority and can
only set revoked=true for the receipt's exact subject/session digest. It preserves
credential/reauthentication restrictions and cannot create current evidence from a
historical acknowledgment. Missing/offline current authority still denies, and an
unrelated digest's receipt does not revoke an otherwise current session.

The encrypted rollback tests use actual native Laravel/Sanctum middleware, active
CSRF validation, encrypted JSON transport and native web/Sanctum guard rehydration.
After a successful synthetic login and protected decision, they capture BSON-typed
owned envelope/control snapshots, confirm a signed independent-fixture revocation
locally, and tombstone the old envelope. The old cookie first fails transport with
409. Replacing only those owned records revives the old encrypted payload and both
guards, but protected access still fails: 401 for the verified negative receipt,
503 for missing/offline current authority. Replaying the same original receipt
re-revokes the restored local control with one journal and one audit record. Separate
cases show that restoring an older program grant cannot override the independent
current authorization fingerprint, and a restored privileged MFA timestamp cannot
clear an external reauthentication requirement. These are enforcement proofs, not
implemented credential/MFA recovery workflows or production authority approval.

Preservation: all 145 pre-slice source files remain, with only this handoff changed.
All 13 protected/config/dependency/catalogue and immutable SRS/Blueprint/ADR/invariant
hashes match. Both accepted application DBs retain 62 strict validators and 128
catalogue indexes with no mismatch. The normal route inventory has no synthetic
recovery/rollback endpoint. No data source documents, unknown records, collection
metadata or TTL indexes were modified. No files were staged or committed. Vite was
not rerun for this PHP-only slice; the previous successful frontend build is retained.

Limits: the coordinator and denial overlay live under Tests and keep state in PHP
memory. isFullyReconciled() reports the last confirmed replay through its signed
barrier, not current authorization or protection against a later unobserved MongoDB
rollback. A recovery run must start fresh against trusted current authority after a
restore. Missing controls can hold this conservative prototype indefinitely; complete
baseline/pruned-history semantics, durable positions and operational recovery ownership
remain gates. The owned replaceOne drills simulate stale local records without real
database restore/backup, external service durability or deployment failover. Receipt
fixtures are supplied independently of the restored records but are not persistent
independent infrastructure. Current account/grant/credential/session trust and affected
privileged reauthentication/MFA are still required before any access resumes.

Fresh read-only review resumed after a reviewer usage-limit interruption and found
no defect in cursor publication, original-page retry, receipt binding or the rollback
denial overlay. It performed no edits, feature tests or MongoDB mutations.

Final: minor (deferred): AuthorityPageRecoveryLabTest's empty-genesis case asserts
that its fixture operation array is empty, which does not observe database writes.
Code inspection confirms the current empty-page path performs no replay, but the
test alone cannot detect a future unexpected write. A scoped command observer would
strengthen that assertion. This remains a test-coverage limitation, not an observed
write or authorization defect; no claim of automated empty-page write monitoring.

Evidence: storage/app/private/page-recovery-rollback-2026-11-18/ contains the 145-file
baseline and handoff snapshot, recovery/rollback red-green logs, scoped/full results,
Pint, schema/routes, immutable and preservation checks, and file-manifest.json.
Selective rollback removes only the four new files and restores only the handoff
snapshot after diff review; preserve unrelated/protected work and independent
revocations. No whole-worktree reset or database/schema rollback is authorized.

Next documented step: consolidate the existing proof into a reviewable activation
decision in this handoff: trace each transport/authority acceptance requirement to
its evidence, specify the smallest proposed provider/configuration diff and rollback,
and distinguish demonstrated local behavior from the remaining durable authority,
key custody, currentness, operational ownership and real deployment evidence. Do not
keep adding isolated laboratory features in place of those decisions. Then prepare
the concrete credential/MFA and IAM-01 prerequisite plan against the final SRS/backlog,
including unresolved identity allocation/nonrecycling behavior; no real authentication,
registration or business-route implementation until the applicable gate is approved.

#### Activation-readiness decision and IAM prerequisite plan — 2026-11-18

**Deliverable/status:** owner-requested design and prerequisite planning are complete;
production activation is NOT ready. This section is the current decision package in
the one maintained handoff. It supersedes exploratory next-slice suggestions above,
without changing the finalized SRS, Blueprint, ADRs, backlog or OpenAPI candidate.
No application code, schema, dependency or environment settings were changed by this
step. Local runtime was freshly booted with PHP 8.4.27: Laravel 13.42.0, unavailable
authority, file sessions with JSON serialization, session.encrypt=false, Eloquent
AuthenticatedUser identity retrieval and sync queue. The encrypted/fenced candidate
still exists only under Tests. A local file store remains permitted for synthetic M0.

Sources checked: final SRS FR-ACT-002–014/018–021, FR-ID-001–016, NFR-SEC-003/010/011/014,
AC-001–007/091/092/100/104/107; the original 71-story XLSX backlog; OpenAPI v0.1
paths/components; approved ADR-01/02 and key_queries_and_invariants.md. Historical
backlog statuses and its PostgreSQL wording remain unchanged provenance, not current
progress claims. IAM-01 depends on FND-03 AND FND-05 and carries G-05; IAM-02 follows
IAM-01; IAM-03 follows IAM-02; IAM-04 depends on FND-03/IAM-02 and carries G-09.
System ID behavior is a prerequisite within identity foundation, not a relabeled IAM-01.

**Approach recommendation, not an infrastructure selection:** carry the existing
encrypted MongoDB envelope candidate into a production-adapter design only after the
decisions below. It reuses the accepted MongoDB stack and tested fencing semantics.
A university-approved Redis transport remains a genuine alternative if its owner,
recovery model and supported deployment are supplied; it requires another operational
dependency and equivalent encryption/atomic-invalidation/concurrency evidence.
Neither transport supplies independent current authority merely by storing sessions.
Do not adopt the installed MongoDB handler unchanged: the prior lease experiments
and its delete/GC behavior do not satisfy this candidate's tombstone/fencing contract.
No vendor, paid service, region, key custodian or production owner is selected here.

**Acceptance/evidence matrix** (test paths are under tests/ unless qualified):

| Requirement | Current evidence | Activation gap / disposition |
|---|---|---|
| Same-origin first-party cookies, active CSRF, native web/Sanctum guard identity | Feature/SanctumFencedSessionActivationLabTest.php; Feature/EncryptedSessionLifecycleLabTest.php | Proven under test bindings/in-process HTTP. Port request-local lifecycle/bindings without referencing Tests, verify deployed HTTPS/cookie/origin policy. No bearer fallback. |
| Encrypted shared JSON payload, explicit issuance, rotation and logout | Support/EncryptedSessionLabStore.php, EncryptedHttpLabSession.php; Feature/EncryptedSessionProcessLabTest.php | Proven in fixed local synthetic collections, separate CLI boots. No production driver, chosen key custody/rotation or complete deployment outage evidence. |
| Concurrent/expired owners cannot resurrect or overwrite sessions | Feature/SharedMongoSessionFenceLabTest.php; Feature/EncryptedSessionProcessLabTest.php | CAS generations/revisions and server-clock leases demonstrated. Two independent HTTP deployments, mid-save/release failures, operational lease deadlines and deployment topology remain acceptance gates. |
| Current account/scope/session/epoch/credential/reauth trust on every protected decision | app/Domain/Security/AccessDecisionService.php; Feature/Fnd03FoundationTest.php; Feature/EncryptedSessionRollbackLabTest.php | Synthetic evidence only. Real independently authenticated authority, prompt revocation/currentness and issuer/key rollback protection remain absent; a short assertion expiry alone is insufficient. |
| Durable, acknowledged, idempotent independent revocation | Unit/AuthorityReceiptContractTest.php; Feature/AuthorityReceiptJournalLabTest.php | Canonical receipt/proof/binding rules demonstrated. ReceiptAuthorityLab is memory only; active revoke port still returns void. Durable command/lookup and real lost-network-ACK behavior are not implemented. |
| Authority acknowledgment followed by transactional local denial/audit/journal | Support/ReceiptJournalReplayLab.php; Feature/ReceiptJournalReplayLabTest.php; Feature/AuthorityPageRecoveryLabTest.php | Idempotent local repair and uncertain commits tested. Runtime SessionRevocationService still uses the older void port/local audit path and is not this receipt coordinator. Production command persistence/recovery integration is missing. |
| Consecutive authenticated recovery history, gap/fork/replay rejection | Unit/AuthorityStreamContractTest.php; Feature/AuthorityPageRecoveryLabTest.php | Genesis-only in-memory cursor works. No durable applied cursor, original-command recovery service, approved complete baseline/pruned-history policy or production reconciliation owner. |
| Restored payload/control/grants cannot establish current access | Feature/EncryptedSessionRollbackLabTest.php | Owned snapshot replacements prove denial despite guard rehydration. Independent fixture survives only in PHP memory; no real externally durable authority restore or production failover drill. |
| Affected privileged sessions require reauth plus applicable MFA | Feature/Fnd03FoundationTest.php; Feature/EncryptedSessionRollbackLabTest.php | Gate enforces external reauth/MFA state. No password verifier, TOTP/email-MFA enrollment/verification, recovery workflow or real MFA delivery. |
| Audit excludes raw secrets and survives reconciliation | Feature/Fnd03FoundationTest.php; receipt/replay and encrypted-chain tests above | Redacted known-path events tested. Production writer privileges, append-oriented tamper evidence, retention, key/secret handling and arbitrary downstream exception paths require deployment review. |
| Institutional/privacy/provider/recovery acceptance | SRS AC-100/104/107; institutional_approvals catalogue | Metadata exists; no FND-05 approval evaluator or supplied institutional release evidence. Synthetic success never marks an institutional gate Approved. |

Known minor carried forward: the empty-genesis recovery test does not observe DB
writes directly. It does not invalidate the implemented empty loop, but must not be
cited as an instrumented no-write guarantee. Strengthen it before claiming that proof.
The last verified code baseline is 281 tests / 3,028 assertions; this document-only
step does not claim a new full-suite run or completion of all M0/M1 stories.

**Smallest proposed activation diff — NOT applied:** stage/review the following
cohesive change after transport and authority decisions are accepted. Merely setting
SESSION_DRIVER or rebinding the unavailable adapter is insufficient.

| Proposed files/area | Concrete responsibility / constraint |
|---|---|
| app/Infrastructure/Session/{MongoSessionEnvelopeStore,SharedJsonSession,SharedSessionManager}.php | Promote the tested staged/live/revoked CAS + encrypted JSON lifecycle into runtime classes; no Tests imports, implicit issuance, payload/control mixing or generic Store wrapper that loses migration behavior. The manager must supply the exact request-local Store to the request, helpers, facades and both guards. |
| app/Http/Middleware/{StartSharedSession,SessionTransportBoundary}.php; bootstrap/app.php | Bind native StartSession for web and Sanctum's first-party chain exactly once; release leases on every exit, suppress identity saves/success cookies after failed mutations, sanitize known backend/fence failures. Preserve active CSRF and guard order; no public auth route in this diff. |
| config/session.php; config/database.php; app/Providers/AppServiceProvider.php | Proposed driver name studentity_encrypted, JSON serialization, server-selected transport connection/collection and reviewed middleware/manager bindings. Keep local file mode for the current synthetic checkout. Unknown/missing transport/key configuration must refuse activation, never silently fall back on authenticated file sessions. |
| database/mongodb/session_envelopes_v1.json; new additive migration | Proposed separate session_envelopes_v1 collection, using the lab's strict/error closed staged/live/revoked validator and exact BSON-long/date/binary types. _id_ plus ix_transport_staged(lifecycle,lease_until) and ix_transport_expiry(expires_at), no TTL. Preserve revoked tombstones and issue-ID uniqueness; do not alter the accepted 62-collection catalogue or point SESSION_TABLE at user_sessions/audit/journal. Approve final target connection/schema and metadata checksum before apply; no runtime collMod repair. |
| app/Domain/Security/Authority/AcknowledgedRevocationAuthority.php; reviewed concrete adapter under app/Infrastructure/Security/Authority/ | Proposed separate acknowledged command/lookup port alongside current-state evidence. revoke(RevocationCommand): RevocationReceipt; lookup(operation UUID) must distinguish authenticated committed, definitively-not-committed and unknown. Returned receipts require existing configured proof/binding verification. Do not simply change the void interface and leave its service/callers on ambiguous semantics. Provider-specific code is unplannable until a real authenticated service/trust contract is selected. |
| config/security_authority.php; AppServiceProvider.php; SessionRevocationService.php | Pin server-trusted namespace/generation, key IDs/verifier, peer identity, currentness and bounded failure behavior; record original canonical command/operation before external submission; verify acknowledgment before transactional local journal/audit/control replay. Bind a real adapter only after its acceptance; absence/unknown trust retains UnavailableSecurityStateAuthority. No environment or request flag grants trust. |
| Runtime recovery coordinator and durable command/applied-position repositories (schema pending) | Persist/recover original commands and reconcile local uncertainty using authenticated authority lookup/pages. Never trust a restored local cursor as external high-water evidence. Account/grant/credential changes need their own authorized protocols; session-revocation receipts alone cannot reconcile them. Complete baseline/pruning and legitimately absent-control semantics require explicit design before durable activation. |
| tests/Feature/{SharedSessionActivationTest,AuthorityAdapterContractTest,AuthorityRecoveryIntegrationTest}.php | Run actual runtime bindings without test-only provider substitutions for the selected boundary; two HTTP processes, key rotation/wrong key, delayed writes/logout, authority offline/revoke-before-expiry, ACK uncertainty, restore/issuer rollback, and redacted logs/cookies. Keep fixture IDs owned and isolated; do not destructively restore accepted DBs. |

Proposed schema fields for the transport remain the lab's exact set: _id,
issuance_operation_id, lifecycle, payload (live only), generation, revision, owner,
lease_until, created_at, updated_at, expires_at, schema_version=1. DB-clock CAS must
guard every mutation and close; live reads cannot issue a missing session. Encryption
must authenticate the wrapper and bind it to the digest. The lab's 10-second lease,
600-second expiry, hardcoded proof clocks, AES test key handling and localhost/testing
guards are evidence parameters, not approved production settings. Key rotation must
not make an old restored envelope authoritative or reissue a retired ID. No Composer
update is assumed; any selected proof/TOTP client library gets its own compatibility,
lockfile and platform review, without ignored requirements.

**Activation order / rollback:** first review exact additive schema/config/provider
diff with offline refusal behavior; then run synthetic integration against the chosen
deployment and independently recoverable authority; then record operational ownership,
currentness and recovery acceptance; enable only the separately approved API slice.
On uncertainty, fence protected access and stop new authenticated issuance, preserve
authoritative revocations and original commands, disable the candidate adapter through
reviewed deployment configuration, and require reauth/applicable MFA after repair.
Do not restore old authority keys/generations, old local privileges or file sessions
as an availability shortcut. Keep additive collection/tombstone/history data for
reconciliation; no down()/drop/blanket cleanup. Restoring this proposal document does
not roll back independently acknowledged security decisions.

**Decisions still required for activation** (none invented or implicitly approved):

| Decision/evidence | Needed outcome |
|---|---|
| Transport operating owner and deployment | Accept encrypted MongoDB candidate or supply university-approved Redis alternative; exact environment/backup boundary, topology, deadlines and encryption-key rotation/custody. |
| Independent authority operating/trust owner | Authenticated service/protocol and location outside the MongoDB restore domain; durable idempotent operations, current account/grant/credential/session evidence, prompt revocation, key/issuer lifecycle, recovery baseline/cursor and alert ownership. Same-server second DB is insufficient. |
| Delivery and privileged MFA policy | Approved mail/delivery arrangement and templates, TOTP or policy-approved additional email OTP, key custody, challenge lifetimes/attempt/retry/rate limits and incident/escalation ownership. No real secrets belong in this handoff. |
| Identity and privacy decisions | Permanent System ID format/issuer plus nonrecycling recovery design; exact approved notice/terms versions/legal bases, real processing approvals and named authorized institutional approvers/custodians. |
| Operational acceptance | Two-instance/outage/restore evidence and measured approved RPO/RTO; deployed HTTPS/cookie/currentness behavior and responsible release approver. No production data before applicable approvals. |

**Credential/MFA and IAM prerequisite sequence** — proposed builder plan, not endpoint
authorization. Use native inline execution on master when the individual bounded
slice is approved; retain the single handoff, protected work and synthetic-only rule.
New domain work must use unprotected sibling folders such as app/Domain/Registration/,
app/Domain/Authentication/ or app/Domain/Identifiers/; preserve the entire protected
app/Domain/Identity/ directory, including UserKind.php. Do not infer new application roles.

| Ordered slice / files proposed | Interface and behavior to establish | Required failure/acceptance proof |
|---|---|---|
| P0 — FND-05 prerequisite: app/Domain/Governance/FeatureGateDecision.php, FeatureGatePolicy.php; app/Infrastructure/Governance/MongoFeatureGateRepository.php; tests/Feature/FeatureApprovalGateTest.php | Evaluate server-resolved institutional_approvals evidence for a specific feature/version/effective instant; deny missing, pending, expired/superseded/conflicting or unverified approval evidence. Synthetic approval fixtures are explicitly synthetic and cannot switch a real-data environment into approved mode. Exact approval resolver/trust and gate mapping must be reviewed before runtime use. | Pending/replayed/client-asserted approval never admits registration or real data; version mismatch and stale restored approval deny; no new business endpoint. FND-05 is not complete merely because its collection exists. |
| P1 — permanent-ID prerequisite: app/Domain/Identifiers/{SystemIdIssuer,SystemIdAllocation}.php; app/Infrastructure/Identity/MongoSystemIdAllocationRepository.php; tests/Feature/SystemIdAllocationTest.php | Proposed reserve(operation UUID): allocation and lookup(operation UUID): known/unknown using a retained issuance registry; same operation returns the same opaque permanent ID, issued/retired IDs never become free. Decide format, collision handling, independent recovery/high-water anchor and additive registry schema first. Allocation occurs only after valid OTP/notice acceptance, before successful registration is acknowledged. | Concurrent reservations, lost ACK/local abort, duplicate retry, retired duplicate IDs, same-person return and stale registry restore cannot recycle an ID. A users.system_id unique index alone is insufficient; no deletion-based cleanup or invented institutional ID format. |
| P2 — registration/credential prerequisites: app/Domain/Registration/{RegistrationIntent,RegistrationAdmissionPolicy}.php; app/Domain/Authentication/{ChallengePolicy,ChallengeVerifier}.php; tests/Feature/RegistrationPrerequisiteTest.php | Proposed immutable pending intent binds normalized recipient, purpose, challenge, requested exact notice/terms versions and provisional academic selections; optional password is adaptively hashed before persistence. No users document or usable session before OTP verification. Separate reviewed registration-intent storage (or an explicit supported extension) is needed; approved catalogue currently names no such intent fields. Provisional academic status is not unverified email. | Wrong-purpose/recipient/intent OTP, expired/consumed code, concurrent consume and lockout/attempt races deny; stale/unapproved notice version denies; raw email token/password never appears in audit/outbox/error data. No public route or mail provider in this prerequisite slice. |
| P3 — IAM-01 only after FND-03/FND-05/P1/P2: RegistrationService, MongoRegistrationRepository and separately reviewed controllers/routes/api.php | Start registration returns anti-enumeration Challenge; verification transaction consumes the bound challenge, creates the single user/verified personal email, exact historical notice acknowledgments, optional credential, provisional academic selections and allocation mapping, with atomic audit/outbox correlation. Do not equate acknowledgment with processing consent. Delivery/expiry policy and authority integration must be accepted first. | Duplicate/concurrent verification creates one account/System ID; partial commit/lost ACK repairs rather than starts a new identity; notice changes are re-presented according to approved policy; no certificate/academic verification from registration. AC-001 and G-05 remain real-data gates. |
| P4 — IAM-02: CredentialUserProvider, LoginIdentifierResolver, PasswordSessionService and PasswordSessionTest | Native provider resolves current verified personal email, System ID or current verified university alias and checks credentials.secret_hash; keep secrets out of users and do not use the scaffold App/Models/User password cast as implementation. Rehash with CAS against the same current credential so a reset cannot be overwritten. Native login rotates transport and creates a correctly bound control; privileged session stays MFA-pending. | Wrong/unverified/retired alias, duplicate ambiguous lookup, invalidated credential, concurrent reset/rehash, enumeration/rate-limit and fixation attacks deny. Exact permission/current authority check on next request; remember-me/bearer shortcuts remain disabled. |
| P5 — IAM-03 then IAM-04: OtpSessionService, PasswordRecoveryService, PrivilegedMfaService and CoordinatorInvitationService with dedicated tests | Distinct purpose-bound OTP login/reset/MFA and single-use expiring invitations; reset and high-risk changes invalidate affected sessions/tokens/credentials with acknowledged independent state and safe repair. Additional MFA follows password for privileged access; TOTP secrets encrypted, replay-step state atomic, email MFA only if policy-approved. Invitees set their own password and receive only approved program scope. | Resend supersession, stale delivery, double consume, reset failure after external ACK, MFA replay/enrollment bypass and repeated invitation consumption deny; one active system_admin invariant remains. Lost-email IAM-07 and two-custodian IAM-08 are later separate stories, never shortcuts in password reset. |

P2 schema review is essential: accepted account_state values are provisional, active,
inactive, withdrawn, graduated and disabled; there is no pending-email state. The
current AccessDecisionService permits provisional students, so creating an unverified
user as provisional would break the intended gate. Existing auth_challenges stores
purpose/digest/expiry/attempts and optional user_id; it does not define a complete
pre-user intent/recipient/notice/password binding. These business validators often
allow additionalProperties=true; permissiveness is not authorization for hidden new
fields, unreviewed schemas, extra roles or pretending the required service exists.
Any necessary additive intent/issuance/MFA replay schema must have an exact diff,
negative tests and repair/rollback review before application. Only ephemeral auth
challenges already have an approved TTL; do not add TTL to notices, IDs or evidence.

Challenge/credential design requirements for that later implementation: use Laravel's
adaptive password hashing in credentials.secret_hash; keyed, purpose/context-bound
verification for low-entropy OTP digests so leaked challenge data does not enable
trivial offline guessing; constant-time verification, DB-time expiry, atomic bounded
attempts and consume; recipient ownership and resend/delivery idempotency. Preserve
original consume/activation correlation for lost-ACK repair without storing plaintext
codes. Delivery must use an approved short-lived encrypted handoff to an authorized
worker rather than a raw OTP in background_jobs.payload_redacted, audit or logs.
FND-04 worker/outbox/retry behavior is itself pending, and sync queue is the current
synthetic baseline. Digest/key custody and mail/TOTP packages are not selected.

OpenAPI mapping to preserve: POST /api/v1/auth/registrations -> 200 Challenge;
POST /auth/email-verifications -> 200 SelfUser; POST /auth/sessions/password and
/auth/sessions/otp-verifications -> 200 SessionResult; POST /auth/sessions/otp-challenges
-> 202 Challenge; POST /auth/sessions/mfa-verifications -> 200 SessionResult;
DELETE /auth/sessions/current -> 200 ActionResult; GET /me -> 200 SelfUser (remaining
paths here share /api/v1). Preserve problem responses and anti-enumeration. The current
candidate requires a minimum 10-character supplied registration/reset password;
registration password is optional, and EmailVerifyInput permits code length 4–12,
which is an input envelope, not the selected OTP entropy/length policy. RegisterInput
has programId/classification but no department/year fields despite FR-ID-001; enrollment
selection timing/DTO completion requires a documented backwards-compatible proposal.
SelfUser also currently requires systemId although the accepted users model leaves
it absent for coordinator/system_admin accounts; a staff-capable /me or session
response needs an explicitly reviewed schema deviation, never a fabricated staff
System ID. Any role-aware optional/nullable change must be assessed for client
compatibility rather than declared automatically backwards-compatible.
Missing policy and any required 419-CSRF/5xx uncertainty behavior must be mapped before
exposing routes; do not silently rewrite the external OpenAPI candidate or return a
test harness's 204 login/logout response as the production contract.

**Execution gates for each approved coding slice:** write behavior-focused negative
tests first; run the named new test and observe its intended failure, implement the
smallest service against approved ports/schema, rerun scoped tests/Pint and the full
PHP suite when source changes, then schema/routes/protected/reference checks and
one independent review. Use C:\tools\php84\php.exe; install no dependencies by force.
Run Vite when frontend/build inputs change or activation verification requires it.
No automatic commits, branches or institutional approvals. Every schema/permission
change needs exact diff, synthetic negative proof and a selective rollback that never
undoes verified revocations or recycles IDs.

**Next action for a continuation:** follow the single sequence P0 -> P1 -> P2 ->
IAM-01 -> IAM-02 -> IAM-03 -> IAM-04. Refine and implement P0/FND-05 approval-gate
foundation first under its reviewed contract, with synthetic evidence and no business
route activation; do not choose parallel feature paths. Resolve the marked trust/
identity/schema decisions before the slice that consumes them. No further transport
laboratory expansion is scheduled. A generic continuation does not invent a production
authority, an institutional approval, a mail service or official notice text. This
decision package allows review of the actual remaining work rather than claiming
that the existing synthetic foundation already delivers registration/MFA.

Evidence for this document-only step: storage/app/private/activation-readiness-2026-11-18/
contains the 149-source baseline/handoff snapshot, local SRS paragraph and original
backlog-row extracts, selected nonsecret runtime/route output and schema summaries.
Source documents were opened read-only. No full test/Vite rerun is claimed because
only this handoff changes; the latest saved code result remains 281 / 3,028. Selective
rollback restores only the handoff snapshot after checking its diff, preserving all
other dirty work, protected directories, source requirements and revocation evidence.

Verification for this step: all 149 pre-step files remain, only this handoff changed,
no new source file was created, all 13 existing protected/config/dependency/reference
checks match, and all 148 non-handoff files match the source hashes supporting the
last 281-test result. A fresh local runtime/route inspection confirmed the stated
configuration and no public authentication/registration routes. git diff --check
passes; nothing was staged or committed. A fresh read-only builder check returned
"Nothing important left" beyond the explicitly deferred owner/operational/schema/
DTO gates; it performed no edits, tests or database operations. The schema/DTO gaps
above remain review items, not approved contract changes or completed features.

#### Proposed authority/shared-session integration design — earlier design retained for context

Keep three responsibilities separate:

1. Laravel session transport holds opaque session payloads for all application
   instances, with expiry, atomic invalidation and tested concurrent request behavior.
2. The existing user_sessions/role_grants/users collections remain the current
   application security-control records read by FND-03. They are not a generic
   Laravel session payload store and must not be garbage-collected as one.
3. Independent authority establishes current revocation/account/grant trust outside
   the MongoDB restore domain. A second collection/database on the same restored
   MongoDB server is not independence. Preserve the unavailable default adapter
   until its trust, ownership, backup and recovery arrangements are approved.

The installed official MongoDB 5.12 session handler is a candidate for shared
transport, subject to a separately reviewed additive collection and tests. Its
payload uses opaque session-ID _id, binary payload, last_activity and expires_at;
its destroy()/gc() delete session payload records. Never configure SESSION_TABLE
to user_sessions, audit_events, or security_decision_journal. Do not invoke its
TTL-index helper on the accepted evidentiary/control collections. No such driver,
collection, TTL index or provider was enabled in the application environments.
The isolated laboratory above uses only its own synthetic transport collections.

A university-approved Redis backend is another transport candidate if that
infrastructure is selected; it adds an operational dependency and does not itself
solve independent security recovery. Prefer evaluating the already installed
MongoDB transport in an isolated synthetic lab first; select production transport
only after infrastructure ownership and recovery requirements are decided. Neither
candidate changes the accepted MongoDB business-data source of truth.

Authority-adapter acceptance contract for the next reviewed implementation:

- Authenticate the authority independently; verify current subject, session digest,
  account epoch and authorization fingerprint for every protected decision.
- A freshness deadline alone is insufficient: revoked evidence must stop working
  promptly even before an old assertion expires. Unknown/offline trust denies access.
- Revocation acknowledgments must be durable and idempotent. Extend the current
  void revoke port with an acknowledged version/reference receipt only when the
  concrete authority is designed; record its link in security_decision_journal.
- Persist local revocation/audit/journal correlation transactionally after authority
  acknowledgment. Reconcile acknowledged revocations after local commit failure.
- After a restore, invalidate affected privileged sessions and require applicable
  MFA; student sessions survive only with trustworthy CURRENT revocation/authorization.
- Test two app instances sharing session transport, session fixation/logout/replay,
  unavailable backend, concurrent requests, forged/stale authority evidence, and a
  MongoDB restore that cannot remove the independent revocation. Use isolated
  synthetic fixtures; do not alter existing synthetic/production schema to simulate
  a restore or delete unknown records.

Approval deliverable before activation: chosen transport and separate authority
trust/recovery model, exact additive schema/provider diff, negative tests, repair/
rollback plan, and named operational owner. No vendor, cloud region, institutional
approval, real credentials, or retention period has been invented. Existing code
continues to deny authorization through its unavailable production authority.

References: installed vendor/mongodb/laravel-mongodb/src/Session/MongoDbSessionHandler.php
and MongoDBServiceProvider.php; [Laravel session documentation](https://laravel.com/framework/docs/13.x/session).

Evidence/rollback: storage/app/private/fnd03-2026-11-17/ contains pre-FND tracked
file snapshots, protected-hashes.json, red/green/full test logs, dependency logs,
formatting, Composer/platform results, routes.json and schema.json. To roll back
after review, restore only these snapshotted files and remove only new FND-03
files listed above after checking their diff; install the restored lock under
PHP 8.4 and explicitly run post-autoload-dump. Preserve master history, protected
work, database schema, and authoritative revocations. Reauthenticate synthetic
sessions after a serialization rollback and rerun verification.

file-manifest.json records the exact new/modified paths, including the review fix.
The first batch-launcher Sanctum dry-run stripped a caret on Windows; the manifest
was set directly to ^4.4 before the targeted update. The actual lock and validation
confirm the intended constraint and one-package addition; no requirement was forced.

#### Reviewed scope and original implementation proposal (provenance)

The following proposal was accepted for the synthetic foundation above; it is
retained for traceability rather than a second execution path:

| Work | Anticipated areas | Acceptance / traceability |
|---|---|---|
| Sanctum first-party SPA cookie sessions, CSRF, secure HttpOnly cookie, rotation/invalidation | Reviewed Sanctum dependency; auth/session/Sanctum config; bootstrap/app.php; authentication adapter | Missing session denied; forged origin/CSRF denied with middleware active; fixation/replay denied; NFR-SEC-004 |
| Default-deny role/action/program/resource checks | Policies, scoped queries, authorization services; review reuse of protected UserKind without changing it unless approved | No implicit studentId access; cross-program denied; existing sessions enforce changed scope; FR-ACT-018, NFR-SEC-001; existing /api/v1/me contract |
| Single active System Administrator and privileged MFA gate | Existing unique index; transaction-aware grant service; MFA-state middleware | Competing grants yield one active admin; MFA-pending protected requests denied; FR-ACT-007 |
| Independent authoritative revocation/recovery anchor | Reuse AuthorizationRecoveryGate; security-state interface and synthetic adapter | Revoked sessions/stale restored privileges denied; unavailable or uncertain authority fails closed; FR-ACT-020/021, NFR-SEC-014, BR-050, AUD-13 |
| Consequential access-decision auditing | Audit service and synthetic fixtures using existing physical model | Actor/action/scope/outcome/security-state traceability without raw tokens or secrets |

Plan dedicated feature/unit tests for sessions/CSRF, scope checks, MFA, concurrent
admin grants, revocation/replay, stale-backup recovery, and audit behavior. Prefer
test-only routes/fixtures for foundation proof; this does not authorize full
registration, grant-management, or operational recovery workflows.

Before coding, finalize the file/interface plan and map tests to SRS acceptance
cases and OpenAPI behavior. Production authority hosting/trust, shared session
storage, MFA delivery, and operational recovery remain reviewed design decisions.
Do not invent a vendor or institutional approval. An anchor restored from the same
MongoDB backup cannot independently satisfy AUD-13.

Proposed next implementation order, for owner review only:

1. Confirm the FND-02 schema baseline under the MongoDB ADRs and the existing users,
   role_grants, user_sessions, audit_events, and security_decision_journal contracts.
   Separately verify identity/key prerequisites; enum/schema presence alone does
   not prove identity allocation/nonrecycling behavior.
2. Add a MongoDB-compatible authentication adapter, then a reviewed Laravel 13
   compatible Sanctum dependency. Use synthetic seeded principals and test-only
   probes; do not implement IAM-01 registration or full credential recovery.
3. Implement a default-deny access decision service using current account state,
   role/program scope, resource ownership, MFA state, and authoritative revocation
   evidence on each protected decision. A client-supplied studentId is never proof.
4. Connect shared-session invalidation and an authoritative security-state boundary
   that is independently recoverable. A synthetic adapter proves fail-closed rules;
   production authority storage/trust approval remains a separate prerequisite.
5. Add consequential decision audit events, then prove the negative-path matrix
   before enabling any approved route. Exercise real CSRF middleware rather than
   relying on Laravel's normal test-mode CSRF bypass.

Anticipated files (proposal, not files created by this upgrade):

| Area | Proposed files / changes |
|---|---|
| Framework wiring | composer.json/lock for Sanctum; config/auth.php, config/session.php, config/sanctum.php, config/cache.php, bootstrap/app.php |
| MongoDB identity adapter | app/Models/AuthenticatedUser.php; app/Providers/AppServiceProvider.php; map the existing physical model rather than copy SQL user assumptions |
| Access/revocation boundary | app/Domain/Security/AccessDecision.php, AuthorizationContext.php, AccessDecisionService.php, SecurityStateAuthority.php; reuse AuthorizationRecoveryGate unchanged where possible |
| Request enforcement | app/Http/Middleware/EnforceCurrentAuthorization.php and RequirePrivilegedMfa.php; route wiring only for the separately approved surface |
| Persistence/audit adapters | app/Infrastructure/Security/MongoAuthorizationRepository.php and AccessDecisionAuditRecorder.php; tests supply an in-memory authority adapter |
| Feature tests | tests/Feature/Fnd03SessionSecurityTest.php, Fnd03AuthorizationTest.php, Fnd03ActiveAdminTest.php, Fnd03RecoveryTest.php, Fnd03AuditTest.php |
| Unit tests | tests/Unit/Fnd03AccessDecisionTest.php; keep the protected M0 test and Identity directory untouched unless explicitly authorized |

The authority interface must return current trust/revocation evidence or an explicit
unavailable result; the access service must deny missing, stale, revoked, or
unverifiable authority evidence. Do not choose provider-specific storage in this
proposal or silently broaden the credential/registration scope.

| Proposed acceptance test | Required result | Existing trace |
|---|---|---|
| Anonymous request to protected /api/v1/me | 401 under existing API contract | FR-ACT-016; NFR-SEC-001 |
| Program A principal requests program B resource, or forges studentId | Denied without revealing protected data | FR-ACT-018; NFR-SEC-001 |
| Existing session after scope reduction/deactivation | Next protected request enforces current scope | FR-ACT-018 |
| Valid password/session with administrative MFA pending | Privileged action denied | FR-ACT-007; AC-003 |
| Concurrent active system_admin grant attempts | Exactly one active grant; conflict is specifically asserted | Single-admin invariant; existing partial unique index |
| Missing/invalid CSRF or disallowed request origin on a mutation probe | Rejected with active Laravel 13 middleware; no mutation | NFR-SEC-004; document Laravel's HTTP result against OpenAPI before exposing a route |
| Revoked/fixed/replayed session cookie | Access denied; login rotates session; logout invalidates | NFR-SEC-004; FR-ACT-012; AC-007 revocation subcase only |
| Restore a grant older than independent deactivation evidence | No recovered privilege; protected request denied | FR-ACT-020/021; AC-091; AUD-13; BR-050 |
| Unverifiable restored student session, or restored privileged session | Student reauth required; privileged reauth plus applicable MFA required | AC-092; NFR-SEC-014 |
| Missing/unavailable/stale authority evidence | Fail closed and audit consequential decision | FR-ACT-021; NFR-SEC-014 |

Existing /auth/sessions/current, administrative grant revocation, and recovery-run
OpenAPI entries guide integration boundaries; this foundation plan does not
authorize implementing their entire workflows. Full lost-email recovery and the
two-custodian emergency procedure (AC-075) remain separate stories and gates.

## Corrections carried forward from the older handouts

- Installed MongoDB is 8.3.7, not 7.0. Preserve ADR-02's initial 8.0 target as history.
- Bare PHP selects XAMPP unless the session PATH is corrected.
- Retire the older nine-test count and unverified M1 version/completion claims.
- Authoritative FND-03 is session/CSRF, authorization policy, and role model:
  FR-ACT-018, NFR-SEC-001/004, /me. Its backlog label is M0; the owner schedules
  this next slice in the M1 workstream. Do not rewrite the backlog to rename it.
- Authoritative IAM-01 is email OTP registration with versioned notice
  acknowledgment, not System ID allocation. Do not use the older handout's
  conflicting IAM mapping or start registration in this foundation slice.
- The SRS permits three authenticated application roles. Granular permissions and
  scopes do not authorize inventing additional roles.
- A scaffold password cast does not prove credentials/TOTP/recovery implementation.
- Retire the instruction to create iam-01-system-id or automatically commit work.

## References and source provenance

- [Laravel 13 upgrade guide](https://laravel.com/framework/docs/13.x/upgrade)
- [Laravel framework requirements](https://raw.githubusercontent.com/laravel/framework/13.x/composer.json)
- [MongoDB integration 5.12.0 manifest](https://raw.githubusercontent.com/mongodb/laravel-mongodb/5.12.0/composer.json)
- [Composer platform dependencies](https://getcomposer.org/doc/articles/composer-platform-dependencies.md)
- Repository policy: AGENTS.md.
- Final SRS, original backlog, and OpenAPI:
  ..\Documentations\R2 Requirement Finalization\
- Accepted ADRs: ADR-01_FINAL_Laravel_Runtime.md and
  ADR-02_FINAL_MongoDB_Physical_Model.md in
  ..\Studentity_M0_Laravel_MongoDB_Approved_v1.0\

The dated ChatGPT handoff is a pointer to this document. The IDE-listed
AI Assist/handout_codex.md was not found under the supplied workspace; no external
copy was edited or treated as a second maintained source.
