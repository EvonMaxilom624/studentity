# Synthetic React UI preview — bounded implementation plan

23 November 2026. Owner reviewed the navy/teal static mockups and asked to proceed.
Progress remains in the canonical handoff. No commit/push authorization is inferred.

Goal: show the three reviewed designs through the existing Laravel root page using
React state and the existing Vite entry. No new routes, APIs or dependencies.

Design: preserve the reviewed desktop/mobile layouts, six registration
classifications and provisional academic states. Keep conspicuous synthetic-preview
labeling, inert legal acknowledgment, and no credential, upload or submission action.
Use a visible sample-dashboard tab rather than pretending sign-in succeeded.

Approach: migrate static screen markup/styles into React components with typed
callbacks and local view state. The static companion remains historical design
evidence; the application components become the maintained implementation. An iframe
or raw HTML injection would duplicate navigation/state and is not used. This is an
application preview, not authorization to bind the FND-07 API candidates.

- [x] Verify existing root lacks preview controls (browser regression RED).
- [x] Add resources/js/preview/ screens, shared chrome/icons, typed state and CSS;
  replace the existing studentity-m0.tsx component with the preview entry.
- [x] Run TypeScript checking, Vite build and real-browser desktop/mobile checks:
  switching, password/code display, dialogs, labels, no overflow, zero API requests.
- [x] Run strict Laravel/MongoDB/tests/build verification and independent review.
- [x] Record results in handoff; preserve prior dirty files and protected bytes.

Review focus: accidental credential submission/storage; genuine versus synthetic
status; accessible dialogs and keyboard navigation; mobile overflow; backend routes
and existing CI/API work preserved. No institutional branding/legal approval implied.
