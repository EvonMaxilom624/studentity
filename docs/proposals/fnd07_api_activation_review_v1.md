# FND-07 API scope, DTO and composition review

Reviewed 23 November 2026 against master d4b3086, finalized SRS v1.1,
accepted ADR-01/02 and the immutable proposed OpenAPI v0.1 in
`../Documentations/R2 Requirement Finalization/`.

Status: engineering scope/DTO corrections authorized by owner task2/3 on23November2026.
The historical review below led to resources/contracts/identity-v1.openapi.json and
the unbound implementation in docs/proposals/identity_api_corrections_v1.md.
Institutional/legal/operational acceptance and runtime binding remain unaccepted.
No routes, handler registration, authentication authority or business operations
are activated by this document. Progress/order lives only in the canonical handoff.

## Bounded first application slice

Candidate scope for explicit approval is exactly these method/path pairs under
`/api/v1`, drawn from the source contract, not a wildcard over future endpoints:

| Method | Path | Contract result / requirement |
|---|---|---|
| POST | `/auth/registrations` | 200 Challenge; verified personal email process, exact acknowledged notices; FR-ACT-002/003, FR-ID-001 |
| POST | `/auth/email-verifications` | 200 SelfUser; single-use OTP, authority-issued permanent student ID; FR-ACT-002/004 |
| POST | `/auth/sessions/password` | 200 SessionResult; verified alias rules, no privileged access from password alone; FR-ACT-005/006/007 |
| POST | `/auth/sessions/otp-challenges` | 202 Challenge; generic response, bounded single-use OTP; FR-ACT-005/013 |
| POST | `/auth/sessions/otp-verifications` | 200 SessionResult; student OTP flow, no staff MFA bypass; FR-ACT-005/007/013 |
| DELETE | `/auth/sessions/current` | 200 response per source contract; durable revocation before success; FR-ACT-012/018 |
| GET | `/me` | 200 SelfUser; fresh current account/session/role state, not an authority cache |

These are review candidates, not implemented controllers. MFA verification,
password reset/recovery, coordinator invitations, profile writes, uploads,
certificates, public verification and exports remain outside this first slice.
Administrative session acquisition must remain denied or limited to MFA-pending
state until the separate password-plus-MFA path is accepted.

Sanctum CSRF initialization is a separately reviewed framework route outside
`/api/v1`; do not accidentally normalize its successful empty response or broaden
the scope to every web route. The existing six runtime routes are unchanged.

## Contract corrections and decisions

| Item | Observed source / lab | Proposed decision before binding |
|---|---|---|
| Registration academic fields | RegisterInput permits programId/classification, lacks department/year; additionalProperties=false | Add required departmentId (catalogue UUID) and yearLevel in a reviewed derivative contract, retain programId/classification. Accepted academic year_level is BSON int, while RegistrationIntent currently stores a bounded string: propose integer HTTP yearLevel with explicit validated conversion into a versioned intent and back to integer persistence. Existing signed intent bytes must not be reinterpreted. Select valid values from accepted academic metadata rather than invented ranges. Enforce program/department consistency. FR-ID-001/002: all initial academic attributes provisional. |
| Classification | Source is unrestricted string; SRS names six categories | Propose the six exact existing RegistrationIntent labels: Incoming Freshman, Continuing/Regular, Transferee, Returning/Readmission, Shiftee, Second-Course. Retain their current wire representation; no invented official codes. |
| Notices and optional password | Source uses termsVersion/noticeVersion/acknowledged and optional password | Resolve HTTP-to-immutable-notice-ID mapping against current accepted versions; acknowledgment is not blanket processing consent. Do not persist or log raw passwords in registration intent payloads. Define when password establishment occurs; generic approval cannot select legal text or credential custody. |
| SelfUser systemId | Required nonnullable string in source; existing projection accepts null and rejects nonstudent System ID | Keep the property present but propose OpenAPI 3.1 string-or-null. Student success requires accepted issuer proof; nonstudents have null. Do not select ID format or treat any opaque lab string as issuance proof. No silent addition of userKind to the closed response schema. |
| Login/logout success | Source already uses 200; earlier lab harnesses used 204 | Align future controllers to the existing 200 response schema. This is a lab alignment issue, not a proposed source status change. |
| Cookie versus bearer | Source permits sessionCookie OR bearerToken for protected paths | Accepted ADR-01 selects first-party cookies. Propose cookie-only security for this slice, retain CSRF protection for state changes, remove bearer alternatives in a reviewed derivative. Never enable token fallback because the historical OpenAPI lists it. |
| CSRF 419 | Source response lists omit 419 | Add application/problem+json 419 for protected state-changing requests; failure must precede writes. No exemption for SPA JSON. |
| Problem 400/409 | Source advertises both; current factory maps unsupported statuses including these to 500 | Add reviewed fixed safe codes/titles for malformed request and conflict, with regression coverage, before binding. Preserve real conflict semantics without exposing constraint/record details. Unknown statuses may still safely map500 under an explicit policy. |
| Problem correlation/detail | Source permits optional correlationId/detail; factory emits fixed five fields | Keep no raw detail/validation arrays; server-created UUID per request, numeric429 Retry-After only and no-store. Requiring correlationId in a derivative is a contract tightening requiring explicit approval. |

The original OpenAPI, SRS and architecture files remain byte-for-byte unchanged.
These changes require an explicit reviewed derivative/decision record; this packet
does not silently amend the authoritative source.

## Composition review against installed Laravel 13.42.0

1. **Scope and identity:** use one server-owned exact method/path predicate shared
   by integration and boundary. A request header, JSON field, route supplied by a
   client or configuration flag must not decide whether redaction applies. Match
   before route resolution where required, without covering unrelated web requests.
2. **One response registry:** supply the SAME FoundationProblemResponses instance
   to FoundationProblemIntegration and FoundationProblemBoundary for the applicable
   request/host lifecycle. Request WeakMap keys isolate correlations; do not keep
   sensitive request state in a process-global cache or assume Octane is approved.
3. **Outer boundary:** place outside target response-producing middleware,
   including authentication/CSRF/throttling/authorization. Handler finalization
   covers thrown exceptions, including errors before route resolution; route-only
   middleware cannot cover early global failures. Verify all relevant paths on the
   actual selected stack rather than relying on a generic ordering diagram.
4. **Single finalizer:** Laravel Handler::respondUsing stores one callback.
   FoundationProblemIntegration::register installs it; a later registration can
   replace it. Existing bootstrap SecurityStateUnavailable rendering and web/M0
   behavior require deliberate scope composition, not wholesale replacement.
5. **Reporting:** Laravel calls an exception's own report() BEFORE registered
   callbacks, and earlier callbacks can already have logged. Returning false from
   the safe callback stops only later/default reporting. Prohibit/review sensitive
   self-reporting/earlier hooks, custom reporters and explicit application logs.
6. **Log channel:** config/logging.php exposes stack/single/daily/stderr/network
   options controlled by environment; no production channel is accepted. A safe
   callback payload alone does not accept formatters, processors, listeners,
   fallback logging, access logs, retention or network destinations. Existing
   identity/authorization middleware logs exception class names separately; review
   their destination/field policy too. No environment secret values were inspected.
7. **Host lifecycle:** current tests cover reused exceptions, mutation and request
   correlations. Worker termination, streaming, after-response callbacks, direct
   sends, proxy errors, long-lived worker recycling and external monitoring remain
   separate proofs. No universal log/body redaction claim is supported.

## Verification and approval boundaries

Existing coverage: FoundationProblemContract/Integration/Boundary,
FoundationProblemResponse(s), SelfUserProfile tests; their scoped rerun evidence
is recorded in the canonical handoff:42tests/335assertions GREEN5.612s on23November. Direct probe also confirmed400/409 map to500. No successful run accepts the missing DTO,
400/409, bearer/cookie or production log-channel decisions above.

Before binding: approve exact slice and derivative contract; implement/test approved
status/DTO deltas; prove CSRF/auth/rate-limit failures cannot write account/session
state; review finalizer/log hooks/channel/host stack; preserve fail-closed current
security/issuer/recovery authorities. Approving this review is not approving
institutional legal text, external providers, storage or production access.

## Visible UI design can precede runtime activation

The current root React page is an M0 foundation page. The next UI design slice can
show login, registration and a task-oriented student dashboard with synthetic data
in mobile and desktop layouts (FR-ACT-001/016, FR-ID-001/002). Include provisional,
pending/unavailable, validation, expired-session and read-only states. Login design
must accommodate email/password, email OTP and verified ID aliases; registration
must show department/program/year/classification and separate notice acknowledgment.

This is a proposed design brief, not authorization to create a new business route
or connect authentication. Select visual direction and review screens first; a
mockup/isolated preview can be reviewed while operational gates remain open.
Real uploads, verified academic status and clearance must never be simulated as
accepted university outcomes. No screen was implemented as part of this review.
