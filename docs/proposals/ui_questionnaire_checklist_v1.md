# Synthetic questionnaire and checklist — bounded design/implementation

23 November 2026. Owner explicitly requested the next two preview pages with
pending/unavailable/read-only states, preserving existing backend/security gates.
Canonical development order remains in the handoff; this is a task specification.

Use the reviewed navy/teal React workspace. Add exact local screen states, not URLs
or server operations. Share the new student workspace shell and state selector.
Questionnaire uses two explicitly synthetic required example fields plus an optional
example note, Previous/Next, accessible validation and final review. Completion is
answered applicable required fields / total applicable required fields (FR-PRO-023),
excluding optional fields, portrait, identity and documents. Examples are not an
approved institution field catalogue (FR-PRO-001/002, FR-FRM-001/011).

Pending allows ephemeral example edits, never Saved Draft or Officially Submitted.
Unavailable hides editable questions and reports unknown service state; read-only
shows a fixed synthetic historical example with editing/submission disabled.
Checklist separates missing evidence, pending academic review and unavailable file
security state; no green clearance claim or upload picker. Legal acknowledgment and
official submit remain disabled. Switching away unmounts answers; no storage/API.

Implementation: extend typed preview navigation, add StudentWorkspace,
QuestionnaireScreen and RequirementsScreen plus responsive CSS; wire dashboard
profile/checklist actions to them. Existing login/registration unchanged.

- [x] Browser RED for missing pages.
- [x] Implement local-only screens/states and navigation.
- [x] TypeScript/build; desktop/mobile browser checks covering all states,
  validation/progress/review, disabled official actions, no upload/API/persistence.
- [x] Strict PHP/MongoDB/build verification, independent review and preservation.

No new route, dependency, approved catalogue, live auth, submission, file authority,
contract acceptance, migration, commit/push or deployment is authorized by this task.
