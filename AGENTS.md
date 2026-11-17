# AGENTS.md (optional file to copy into the Laravel repository)

This is Studentity, a university student-data system. The authoritative requirements are Studentity SRS v1.1; the architecture deviation is ADR-01/ADR-02 v1.0 (Laravel + MongoDB). Do not edit the SRS or Blueprint v0.1.

- Use **synthetic data only**; never copy real student records, institution secrets or cloud credentials into code, tests, prompts or logs.
- Preserve immutable source/version histories and the certificate one-slot-per-student-year invariant.
- No student/administrator, uploads, certificates or public verification endpoints unless the applicable M0 security gate and tests pass.
- Never invent an institutional approval, privacy legal basis, retention period, cloud budget, data region or production vendor.
- For dangerous changes (migrations, permissions, certificate status, retention/disposal, imports), provide diff, negative tests and rollback or repair plan. Never run destructive database commands without explicit human review.
- Reject any design where a restored old backup silently revives revoked permissions or falsely reports current clearance.
- Keep OpenAPI changes backwards-compatible or document proposed deviations for review.
- Use Composer/PHPUnit/Pest/Laravel and React Vite tests with pinned lockfiles; inspect test output before reporting success.
- For Antigravity and Codex: verify extension support locally; otherwise use Codex CLI in integrated terminal. One writing agent per branch/file set, review Git diffs after every task.
