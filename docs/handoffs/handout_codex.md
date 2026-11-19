# Studentity ? current Codex handoff

Updated 19 November 2026 (Asia/Shanghai). Work on **master**, HEAD 02bc9a8.
This is the only maintained handoff and execution checklist. Keep completed detail in
linked evidence. No competing roadmaps, branches, worktrees or automatic commits.
Owner says: do not stage/commit until explicitly told again. Preserve all dirty work.

History: [archived handoff](handout_codex_archive_2026-11-18_pre_governance_identity.md).
Owner authorized its archive; preserve its original bytes. Read it only for a specific
historical question. Original P0?P5/activation readiness detail is retained there.

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
Last full suite: **607 tests / 4,019 assertions**, no failures/skips, 83.784s.
Evidence: storage/app/private/fnd06-original-scan-2026-11-19/full-tests.log.
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
| M0-06 files/workers | Worker reliability and private quarantine/scanner labs verified; durable scan-head/delivery and real provider acceptance pending. |
| M0-07 clearance | Slot uniqueness/freshness guards tested; public clearance stays inactive. |
| M0-08 CI/review | Local checks/reviews exist; secure CI/signed reproducible release/M0 signoff pending. |

**M0 is incomplete.** Lab proof is not operational/production acceptance. Original weekly
windows were candidates, not current promises; no invented completion date/percentage.
Operational FND-03/FND-05 acceptance still requires real authority/issuer/key/clock/
approved-notice ownership and evidence; independent provider/delivery/shared-throttle
acceptance remains pending. studentity:readiness lists these blockers, never accepts them.
Next development order (foundations can overlap; activation dependencies stay binding):
1. FND-06 worker integration of stored original scan-command/IDs/time, then append-only history/latest head and
   conditional state/audit/outbox reconciliation; stale-clean/newer-unsafe/restore/
   concurrency proofs. Then private authenticated delivery with current permission/
   scan/policy/recovery checks. Real storage/scanner/ACL/encryption acceptance stays open.
2. FND-07 OpenAPI/problem/CSRF/throttle harness and approved DTO corrections.
3. FND-01 secure CI/exact artifacts and M0 acceptance review, then gated M1 IAM rollout.

## Completed foundations (isolated, not operational acceptance)

P0 approval provenance G-05/G-09, P1 authority-first nonrecycling ID projection and
P2 encrypted registration intent/purpose/recipient/notices/OTP guards implemented.
IAM-01 atomically applies provisional account/email/optional credential/notices/audit
from original child IDs and actual issuer receipt; no automatic role/session/academic
verification/consent/certificate. RegistrationLabDatabase only targets
studentity_test_registration_activation_v1; proposal iam01_registration_v1.json.
Real issuer/ID format/notices/delivery/global throttles/recovery remain open. Details:
storage/app/private/registration-durable-2026-11-18/ and
registration-test-acceptance-2026-11-18/; older evidence retained in archived handoff.

FND-04 reference outbox: atomic enqueue/audit, DB-clock token/generation leases,
immutable results/final aggregate audit, reserved IDs and verified hash-chain/history.
Handler I/O outside TX. Producer context owns snapshot/primary/majority; caught errors
poison owning transaction; exact replay skips apply and verifies original producer/job.
Current lab studentity_test_fnd04_outbox_v2 (3collections/10indexes); preserve v1.
Evidence: fnd04-outbox-2026-11-18/ and fnd04-producer-2026-11-18/ under private storage.

Handler protocol binds original job/item IDs, fingerprints, actor/type, destination
and generation. Private verification rechecks fresh challenge/query/proof policy and
original lower bound; unknown never implies absent. Signed complete noncommit is
point-in-time only, not permission/retry. Adapter persists original wire/time before
I/O, rechecks current gates/policy, and always requires live lookup despite ACK cache.
Synthetic provider serializes per-item controls and atomically stores marker+receipt;
orphan/conflicting evidence fences. Labs studentity_test_fnd04_handler_v1 (1collection)
and studentity_test_fnd04_provider_v1 (3collections), total5indexes. Same-server state
is not independent recovery or exactly-once delivery. No positive runtime binding.
Proposals docs/proposals/fnd04_handler_protocol_v1.json and
database/mongodb/proposals/fnd04_handler_lab_v1.json. Evidence:
fnd04-handler-protocol-2026-11-19/ and fnd04-handler-reconciliation-2026-11-19/.

Readiness command studentity:readiness [--json] observes safe allowlisted config and
5 registered ports without resolving/probing providers.8operational evidence blockers
remain unaccepted; always advisory/blocked/activationAllowed=false, exit1 intentional.
No approval flag/production setting can grant acceptance. Delivery proposal now includes
recipient-facing confidentiality/authenticated private links and encrypted/controlled/
regular/monitored/off-site backups + documented restores (FR-NOT-004/005,NFR-OPS-002).
No sender/encryption implementation. Evidence operational-readiness-2026-11-19/.

## FND-06 private quarantine/scanner foundation completed (uncommitted)

QuarantineObjectReference binds exact object/version/owner UUID,digest,size,declared
media. PrivateQuarantineStore stage/readForScan and FileSecurityScanner available/
inspect ports default unavailable. No positive runtime gate/policy/clock/provider.
LocalQuarantineLabStore explicit testing-only install below storage/framework/testing/
fnd06-quarantine-v1/{UUID}; outside every configured local disk/public root/link target
(the default Laravel local disk has serve=true). Current root/ancestor/file checks;
exclusive immutable fingerprint-header+bytes; digest/length/exact replay verification;
partial/conflicting metadata/body retained/fenced, never overwritten/deleted/repaired.
64KiB cap is synthetic only; no production size policy adopted. OSACLs/encryption and
hostile same-user races/unconfigured serving aliases remain operational concerns.

QuarantineInspectionService verifies exact bytes before scanner, fresh service scan
UUID echo, object/policy fingerprints, scanner/malware-definition version, result
time/type/checks and current permission/policy/health after both I/O phases. Monotonic
wall observations and elapsed budgets reject rollback/late/frozen-clock results; no
hung-I/O cancellation claim. Unknown/error/outage fail closed with redacted exceptions.
Returns clean/unsafe metadata only, never ordinary bytes, permission, persisted cleared
state or academic approval. No cached clean fallback or durable scan head/outbox yet.
Actual malware/type/structure detection/authenticity is not proved by synthetic fakes.

Proposal docs/proposals/fnd06_quarantine_v1.json; full600/4,010,83.126s; focused33/124,
Pint16PHPfiles passed. Real immutable lab-byte restart/scan/outage + signed Laravel
storage normal/traversal URL404/no payload proved; no new business route. Fresh review
no Critical/Important; actual-filesystem-link coverage Minor below. Prior255sources
retained except provider fourimports/two unavailable bindings and this handoff;16new
files. Protected13+archive hashes match; accepted62+ledger/128,6HTTP routes unchanged;
config/dependencies unchanged. No Vite rerun (backend-only), no staging/commit. Evidence/
ledger/manifests/snapshots/completion-report.md: storage/app/private/
fnd06-quarantine-2026-11-19/. Prior work not recertified by slice review. On rollback
retain all bytes/partial records/audit/receipts/evidence; selective source restore after
review only. Detailed prior handoff retained in before/docs/handoffs/handout_codex.md.

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
- Deferred test minors: observe real DB writes for empty-genesis and service-level
  account/session no-write proofs; replace flaky OTP substring with structural MAC;
  assert registration entered markers absent before release; repair older FND04 negative
  cases that catch self::fail; assert producer fault-stage and exact enqueue-hook counts.
- New protocol minors: duplicate/oversize wire tests should isolate the intended failure;
  proposal wording about query hash/observation context needs precision (only nonce/
  issuedAt/command context hashed; lower/freshness/observation bounds checked separately).

- Deferred readiness wording Minor: include SRS NFR-OPS-003 initial RPO/RTO design
  targets <=24 hours alongside measured institutionally approved deployed objectives.
  Diagnostic stays blocked; no acceptance or retention/budget value was invented.

- FND-06 optimized previews must preserve originals; unsafe replacement/isolation/
  notification and retention/disposal require approved procedures and negative proofs.
- Deferred FND-06 coverage Minor: platform-supported real ancestor/object symlink and
  hard-link negatives must reject and preserve target bytes. Configured-link cases
  covered; no actual filesystem-link test or implementation bypass demonstrated.

## Daily commands

Use explicit PHP84 or $env:Path='C:\tools\php84;'+$env:Path in that terminal.
php artisan serve --host=127.0.0.1 --port=8000; npm.cmd run dev in a second terminal.
MongoDB must run; Start-Service may need elevation. Never replace an existing service
or erase data to start development. Tests: & 'C:\tools\php84\php.exe' vendor/bin/phpunit.
Readiness: & 'C:\tools\php84\php.exe' artisan studentity:readiness --json
Exit1 is intentional while operational acceptance is blocked.
Read-only schema check: explicit PHP84 followed by
storage/app/private/laravel13-upgrade-2026-11-17/schema-inspection.php.

## Original scan-command persistence completed (uncommitted)

FileScanCommand binds scan UUID, full immutable reference/policy and original issue
time. MongoOriginalScanCommandStore insert-once primary/majority exact replay/lookup
with default-deny gate. Explicit ScanCommandLabDatabase only installs local testing
studentity_test_fnd06_scan_commands_v1, one strict scan_commands collection/_id index;
pinned fnd06_scan_commands_v1.json, no repair/TTL/down/delete. Corruption or changed
body/policy/time fences. Missing is unknown; no permission/current clearance inferred.
Existing inspection service is unchanged and still uses ephemeral scan IDs; stored
command worker integration, scan history/head/state/audit/outbox are next, not completed.
No runtime binding/schema/config/route/dependency changes.271prior sources preserved
except handoff;5newfiles. Scoped7/9; full607/4,019,83.784s; Pint4files; fresh review
no findings.14immutable/archive hashes, accepted62+ledger/128 and6routes match.
Evidence/report/snapshots: storage/app/private/fnd06-original-scan-2026-11-19/.
No commit. Retain commands/data on rollback; source restoration only after review.
