# Identity engineering contract and corrections

23 November 2026. Owner authorized tasks2/3/4 and explicitly held task1.
No commit, push or hosted CI. Work directly on master; preserve earlier dirty work.

Engineering decisions for the seven-operation identity slice are accepted for
implementation under this task. The derivative lives in
resources/contracts/identity-v1.openapi.json; original SRS/ADR/OpenAPI remain immutable.
This does not grant institutional legal/operational approval or activate routes.

Decisions: first-party cookie-only protected operations; CSRF419 on state changes;
login/logout200 as in source; nullable systemId property (not omitted); closed safe
five-field Problem with server correlation and explicit400/409 semantics. Registration
adds departmentId and integer yearLevel, exact six existing classification labels,
true acknowledgment and server-resolved program/department/year selections.

Use existing lowercase v4 UUID compatibility for registration selections. Version
references are bounded printable ASCII identifiers, not legal text. Optional password
retains source minimum10 characters; maximum72 UTF-8 bytes prevents the current
bcrypt path from truncating input. These are engineering transport bounds, not a
claim of approved institution strength policy. Existing hash driver remains injected.

An unbound HTTP adapter rejects unknown fields/types, mismatched academic selections,
unaccepted year values, invalid notices and malformed passwords. Academic lookup
snapshots must be supplied by trusted server code, never request fields. It emits
an integer academic projection and explicitly adapts the validated year to the
existing immutable string-year intent, hashing optional password before conversion.
No existing intent/payload purpose or stored bytes are changed/reinterpreted.

- [x] RED tests: contract shape, DTO negatives/conversion/redaction and safe400/409.
- [x] Implement derivative, unbound adapter, exact scope predicate and response fixes.
- [x] Correct diagnostic institutional gate traceability; preserve historical lab IDs.
- [x] Focused and strict verification, review and canonical handoff.

Task4 operational completion additionally requires actual approved issuer/format,
notices, independent recovery/key custody and shared session infrastructure. The
owner has been asked for those missing details. No lab-to-production promotion,
fabricated approval or readiness switch is permitted to substitute for them.

Implementation/verification above completed. Task4 operational acceptance is NOT complete; see canonical handoff and private task report. No publication performed.
