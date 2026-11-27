# FND-01 CI and release-evidence review packet v1

Updated 23 November 2026. Review input, not a second roadmap: development order and
execution evidence remain in docs/handoffs/handout_codex.md. No release, signing,
artifact upload, deployment or institutional acceptance is granted here.

## Current measured candidate

GitHub repository EvonMaxilom624/studentity, master. Owner authorized the manual
synthetic GitHub-hosted candidate and the Vite build-order fix. Run45095083400
passed at578e1fd:731tests/4636assertions,40.362s; Vite prebuild779ms and mandatory
post-test build768ms; fresh rs0/catalogue/ledger and unchanged-input checks passed.
Follow-up d4b3086 changes only the handoff. Historical earlier packet statements
that no remote/workflow exists are superseded by this measured evidence.

Measured runner ubuntu-24.04 image20261116.376.1, PHP8.4.28/Composer2.11.2,
ext-mongodb2.5.7, Mongo8.0.38rs0, Node24.22.3/npm11.19.1. These are observed values,
not fully pinned release inputs. Runtime app dependencies remain Laravel13.42.0,
Tinker3.1.0, Sanctum4.4.0, Mongo integration5.12/library2.5.1, PHPUnit12.5.44.

## Checkout runtime maintenance

Previous v4.2.2 pin declares Node20; GitHub forced Node24 and emitted a warning.
Reviewed replacement: actions/checkout v7.0.1 at
3d3c42e5aac5ba805825da76410c181273ba90b1. Official tag resolves to that commit;
GitHub signature metadata verified=true; action.yml declares runs.using=node24.
README requires runner>=2.327.1 for Node24; measured runner2.338.2 satisfies it.
V7 refuses unsafe fork checkout under privileged PR/workflow triggers by default;
this candidate remains manual-only, with persist-credentials:false and
contents:read. No unsafe override is enabled and full source pinning is retained.

Local YAML checks and focused application regression coverage are recorded in the
handoff. Publishing/rerunning this pin change requires the owner's normal explicit
commit/push authorization; no hosted pass for this new pin is inferred from the
previous v4 run. Signature metadata does not attest Studentity release contents.

Sources: [release](https://github.com/actions/checkout/releases/tag/v7.0.1),
[action manifest](https://github.com/actions/checkout/blob/3d3c42e5aac5ba805825da76410c181273ba90b1/action.yml),
[README](https://github.com/actions/checkout/blob/3d3c42e5aac5ba805825da76410c181273ba90b1/README.md),
[commit](https://github.com/actions/checkout/commit/3d3c42e5aac5ba805825da76410c181273ba90b1).

## Candidate execution boundary

Manual workflow_dispatch only; contents:read; no persisted checkout credential,
project secrets, release credentials, automatic PR/push triggers or shared caches.
Disposable masked APP_KEY, loopback fresh job-local rs0, both fixed catalogues
proved empty before migration; no reset/drop/repair of existing state. Full20minute
job timeout and bounded replica-set readiness. Never run this bootstrap on accepted
local or production databases. The local strict entry point itself installs,
migrates, publishes and signs nothing.

composer install uses lockfile and --no-scripts, checks platform requirements, then
explicit package discovery. No locked Composer plugins; configured allow-plugins
is not blanket release trust. npm ci --ignore-scripts uses locked npm registry
URLs/integrity, followed only by explicit esbuild rebuild. Esbuild may fetch its
exact matching platform binary if optional installation is missing. Application
providers keep real security/issuer/storage/scanner authorities unavailable.
No dependency updates, platform ignores or local installations are part of this
review. Outbound network/package-source trust remains a separate control.

## Exact artifact, SBOM and signing gap review

| Evidence | What exists | Decision/proof still needed |
|---|---|---|
| Source identity | Full Git commit and selected source hashes, including CI YAML | Reviewed source change/approval association and independent origin verification; hashes alone are unsigned |
| Runtime/build identity | Observed runner/tool versions and action commit pins | Exact runner/base image, PHP/modules, Composer, Node/npm, Mongo package/key and build-tool identity; a major version selector is mutable |
| Complete application artifact | No deployable assembly; public/build and vendor excluded from source manifest | Select archive with exact vendor+build contents or OCI image after hosting/runtime decision; explicit path allowlist, executable bits, symlink policy, deterministic assembly and exact SHA256 digest |
| Runtime versus build inputs | Composer/npm lockfiles and private transitive lock inventory | Distinguish shipped PHP dependencies from frontend bundled dependencies and build-only npm/dev tools; include native extension/base OS packages for the selected artifact |
| SBOM | Lock inventory for review only | Select pinned SBOM producer and SPDX/CycloneDX format; bind actual shipped components, licenses, versions/hashes and dependency relationships to the exact artifact digest; inspect native/bundled components |
| Vulnerability/secret scanning | Existing code tests are not scans | Choose reviewed scanners, databases/as-of versions, severity policy and exception/expiry process; scan source/dependencies and final artifact without uploading secrets; no clean scan result claimed |
| Provenance/signature | No signing identity/custody selected | Separate approved trusted job/identity, exact artifact+SBOM+source+builder binding, key/OIDC trust policy, independent verification and rotation/revoke/recovery controls |
| Permission separation | Test job has contents:read and cannot mint release authority | Keep future signing/publishing outside untrusted test jobs; never widen current job rights as a shortcut |
| Evidence retention | GitHub log policy and private local metadata/excerpts | Define approved duration/access/region/budget; retain complete manifests/failure logs and artifact/SBOM/signature linkage. No existing uploaded artifact or backup claim |
| Recovery/signoff | Local/hosted lab proofs | Named operational reviewers, tested deployed restore/revoke/session/storage behavior and institutional M0 acceptance |

The selected-source manifest intentionally excludes .env/secret storage, docs,
vendor, node_modules and built assets. Do not rename it a release artifact or SBOM.
The lock inventory records dependency references, not independently verified
package bytes or vulnerability status; Composer dist checksums may be empty.

## Decisions remaining with owner/institution

Select intended deployment/package format and exact approved runtime environment;
then artifact/SBOM/scanner tool scope and trust/exception policy. Name signing and
verification authorities with custody/revocation, choose evidence retention and
access, and obtain independent institutional signoff. No provider, institution
legal basis, region, retention duration, key or production budget is invented.
This review prepares those decisions; it neither provisions them nor stalls
synthetic UI design behind production artifact decisions.

## Concrete verifier and rejection policy

`php scripts/verify-local.php --ci-candidate` (local explicit PHP8.4 binary).
Skipped/incomplete/risky/warning/notice/deprecation/empty-suite outcomes fail.
Mandatory post-test Vite build and selected-source before/after equality remain;
deployable=false. The workflow prebuild supplies assets required by the HTTP
smoke test on clean checkout. --preflight-only cannot combine with strict mode.

No result in this packet grants production readiness, API binding or private
file delivery. FND-07 scope/DTO/logging/host composition and FND-06 operational
storage/scanner/auth/recovery acceptance remain separate reviews.
