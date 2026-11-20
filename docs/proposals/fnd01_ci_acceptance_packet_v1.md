# FND-01 CI activation review packet v1

Status: local strict verification candidate, no active CI/release approval.
Canonical progress remains docs/handoffs/handout_codex.md; this packet is an acceptance input, not another roadmap.

## Decisions still required

| Decision | Observed status | Required choice/evidence |
|---|---|---|
| Repository host/remote | No Git remote configured | Owner-selected destination/repository and approved source access. No push performed. |
| CI provider/runner | None configured; no active workflow | Approved provider, region/budget, runner OS/isolation and immutable runtime/image/action references. |
| Untrusted changes | No remote jobs | Read-only permissions, no persisted checkout credentials, no institutional secrets/release tokens, isolated caches/network/artifacts and reviewed dependency execution. |
| Signing/release identity | Not selected | Separate trusted signing job/identity, custody/rotation, independently verified exact artifact digest and provenance. Test jobs cannot mint release authority. |
| M0 signoff | Incomplete | Named institutional reviewers and accepted operational evidence; local green tests never substitute. |

## Concrete candidate entry point

`php scripts/verify-local.php --ci-candidate`

On this developer machine use `& 'C:\tools\php84\php.exe' scripts/verify-local.php --ci-candidate`.

This requires all tests plus Vite build. PHPUnit skipped, incomplete, risky, warning, notice and deprecation outcomes fail; empty suites fail. `--preflight-only` cannot be combined with strict mode. Default local/preflight commands retain their behavior. Successful summary records `profile=strict-ci-candidate`, `strictTests=true`, `frontendBuild=passed`, unchanged selected-source hashes and `deployable=false`. Source hashes are unsigned and incomplete as release provenance. The runner installs/migrates/cleans/uploads/signs nothing.

## Host setup acceptance sequence

1. Review the exact source revision, including all current uncommitted work, before any future commit/push. No automatic merge/publish/deploy. Actual workflow permissions and dependency execution remain unaccepted until inspected.
2. Provision a disposable isolated runner with reviewed immutable references for PHP8.4/ext-mongodb, Node/npm and MongoDB replica set. Current measured versions: PHP8.4.27, Mongo extension2.5.7, Node24.13/npm11.6, Mongo8.3.7rs0. Exact hosted OS/tool/image compatibility has not been measured; no production image or action pin is invented here. Never run untrusted jobs on this developer machine or shared production credentials/data.
3. In that approved environment only, provision exact Composer/npm lockfiles with platform checks. No force installs, platform ignores or dependency updates. Review Composer/npm lifecycle scripts/registry trust before executing dependency code. This local slice performs no installations.
4. Supply synthetic test configuration only: APP_ENV=testing, MONGODB_DATABASE=studentity_test_m0, MONGODB_URI=mongodb://127.0.0.1:27017/?replicaSet=rs0, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, QUEUE_CONNECTION=sync, SESSION_DRIVER=array; generate a new disposable synthetic APP_KEY. PHPUnit XML owns child-test overrides; parent Laravel/Dotenv is not bootstrapped by the verifier. Approved public build inputs only. No institutional credentials or real records.
5. Initialize rs0 on the disposable job-local instance with loopback advertised members. The existing preflight verifies URI/topology but is not network isolation; enforce network/data boundaries outside PHP as well. It does not create a replica set or schema.
6. Provision the accepted catalogue into a demonstrably fresh/empty isolated studentity_test_m0 via a separately reviewed bootstrap step. Existing migration can issue collMod on existing collections, so do not point it at accepted/local/production databases or invoke it as automatic repair. Existing lab tests may create their exact studentity_test_* fixtures. Do not reuse/persist job DB volumes; disposal policy is a host-approval decision, not a local deletion command.
7. Run the strict entry point. Retain failed-stage logs and input manifests privately. A nonzero status at preflight/tests/build/hash verification rejects the candidate. Green means local strict verification only; no operational acceptance flag is emitted.
8. Separately select/review complete artifact assembly, dependency/SBOM/secret scanning, immutable package retention and reproducibility requirements. Current verifier hashes selected source inputs, excluding secrets/storage/vendor/node_modules/docs; it does not package vendor/build outputs or sign anything.
9. Use an independently approved trusted release job to verify/sign the complete exact artifact and origin. Define signing identity/custody and permission boundaries before workflow activation. A source manifest or plain checksum cannot grant deployment.
10. Obtain institutional M0 and deployment acceptance. Then review real FND07 API scope/DTO/middleware/logging/lifecycle binding and FND06 storage/scanner/auth/private delivery acceptance. Those remain separate gated work.

## Immutable input references at preparation

- `composer.lock` SHA256 `bb2ff2ff59233b50561a3e7c3457b127b50d1fa80313d9a06d09d8237848d503`
- `package-lock.json` SHA256 `7185acc8b626c7be92b46cebfd5173e8d63abe446fbd2d8969a0fa87b5862f53`
- `database/mongodb/collection_catalogue.json` SHA256 `2fb572b70b6ed1ef2f4bcb94f07e3a79d7b76706a13cc02e02408c79afc32709`

These values identify the current local candidate, not institutional approval. Dependencies remain Laravel13.42.0/Tinker3.1.0/Sanctum4.4.0/PHPUnit12.5.44/Mongo integration5.12/library2.5.1. Version claims derive from existing lockfiles/local evidence, not remote CI measurements.

## Required activation review proof

Actual provider workflow diff; exact reviewed source/runner/action/image/dependency identities; job secret/permission boundaries; disposable schema/rs0 proof; strict negative/positive test/build evidence; controlled log/artifact retention; complete artifact/SBOM and independent signing verification; operational recovery/deployment acceptance and named M0 signoff. None is accepted by a configuration toggle.

No active workflow or Git remote was created, and no data was uploaded. Rollback is source-only and reviewed; preserve all prior dirty work, test evidence and Mongo histories. No reset/stash/clean/drop/delete/repair commands.
