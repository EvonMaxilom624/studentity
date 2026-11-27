# Connected Synthetic Profiling & Questionnaire Workflows v1

**Date:** 24 November 2026  
**Status:** Implemented and Verified in Local React Preview.  
**Scope:** Client-side state synchronization across Dashboard, Questionnaire, and Requirements screens; in-memory FND-06 file security pipeline simulation.  
**Canonical Handoff Reference:** `docs/handoffs/handout_codex.md`

---

## 1. Context and Objective

Under Task 7 of the development plan, the synthetic student workspace UI (`resources/js/preview/`) connects the interactive questionnaire and requirements checklist to the student dashboard:
- Providing real-time state synchronization between the multi-step profiling questionnaire and dashboard progress indicators.
- Providing an interactive client-side simulation of the FND-06 file security foundation (staging, antimalware scanning, preview isolation, and quarantine).
- Preserving strict security boundaries: in-memory state only, zero server uploads, zero credential/token storage, and conspicuous synthetic disclaimers.

---

## 2. Connected Workflow Architecture

### 2.1 State Synchronization (`PreviewApp.tsx`)
- `questionnaireData` (`QuestionnaireData`):
  - Tracks `preference`, `goal`, `note`, `step`, and `completed`.
  - Shared between `QuestionnaireScreen` and `DashboardScreen`.
  - When the student enters answers or advances sections in the questionnaire, the state updates reactively.
- `simulatedDocuments` (`Record<string, SimulatedDocument>`):
  - Tracks simulated document items (e.g. `academic_id`), recording `status` (`not_provided`, `clean`, `unsafe`), generated synthetic IDs (`objectId`, `scanId`, `previewId`), and descriptive feedback.

### 2.2 Dashboard Reactive Indicators (`DashboardScreen.tsx`)
- **Questionnaire Progress Card:**
  - Progress percentage dynamically reflects answered sections (0% -> 25% -> 50% -> 100%).
  - Step counter updates dynamically (`0 sections complete` up to `3 sections complete`).
- **Next Steps Tasks:**
  - "Complete your profiling questionnaire": Dynamically updates pill from `Not started` -> `In progress` -> `Completed`.
  - "Check your identity requirements": Dynamically updates pill from `Not submitted` -> `Evidence staged (clean)` or `Scan alert (quarantined)`.
- **Recent Activity:** Displays dynamic entry when questionnaire is in progress or completed.

### 2.3 Synthetic Document Pipeline Simulation (`RequirementsScreen.tsx`)
- In "pending" mode, the "Document actions" panel exposes interactive simulations demonstrating the FND-06 architecture:
  1. **Clean Student ID (JPEG):**
     - Simulates valid JPEG signature detection, clean scanner status, and distinct preview object generation.
     - Checklist updates to `Clean & Preview Ready (synthetic)`.
     - Displays synthetic receipt: `objectId`, `scanId`, `previewId`.
  2. **Unsafe File (Malware / Active Script):**
     - Simulates active script or EICAR marker detection.
     - Checklist updates to `Quarantined · Unsafe (synthetic)`.
     - Displays security alert: file isolated in quarantine; preview generation aborted.
  3. **Reset Simulation:** Clears the simulated document and returns to baseline.

---

## 3. Security Invariants & Disclaimers

1. **In-Memory Only:** All questionnaire answers and simulated document receipts exist strictly in React state memory. Leaving or refreshing the page clears all data.
2. **Zero Network / Server I/O:** No network requests, API calls, or server uploads are performed.
3. **Disabled Official Actions:** Official submission buttons, legal acknowledgments, and academic clearance actions remain disabled.
4. **Conspicuous Labeling:** Every screen displays prominent banners indicating that the view is an illustrative synthetic preview.

---

## 4. Verification Evidence

- **TypeScript Compilation:** `npx tsc --noEmit` passed with 0 errors.
- **Frontend Production Build:** Vite 7.3.8 built 35 client modules in **756 ms**.
- **Strict Verification Candidate:** Verified via `scripts/verify-local.php --ci-candidate`.
