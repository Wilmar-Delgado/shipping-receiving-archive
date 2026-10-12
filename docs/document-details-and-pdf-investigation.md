# Document details and PDF investigation — October 12, 2026

## Implemented changes

- RMA canonical slug is now `return-merchandise-authorizations`. Both `/archive/rmas` and `/archive/return-merchandise-authorization` permanently redirect with query parameters retained. Both legacy Inbox category parameters remain compatible. RMAs labels and full descriptive heading are unchanged.
- One data-only migration updated existing RMA type ID 3 in place. Before/after hashes confirm that only its slug changed: 54 documents, all references/file metadata/users/parties and 18 private originals are otherwise unchanged. No reset, destructive seeding, file replacement or naming migration was performed.
- Details header retains breadcrumbs, the back link, original filename and review/filed status. Read-only Upload information and the existing single editable MetadataForm sit beside each other on desktop and stack on mobile. The form uses compact columns for fields/references, keeping its validation, new-party entry, draft/filing/correction behavior and original file identity.
- A shared DocumentPreview renders below the information/form sections and is also reused by listing preview dialogs. It checks the existing authorized endpoint with HEAD, validates its MIME, and then embeds the original route directly. No full PDF blob is buffered in Vue, so browser range requests remain available. Abort cleanup prevents stale preflight results; primitive identity/availability watchers avoid reloading a viewer for unrelated metadata changes.
- PDF page navigation/zoom uses the browser's native viewer when supported. Images have bounded zoom and Fit controls. Loading, missing/unavailable/error states, explicit retry, secure download and a separate-view fallback are provided. No authorization or security headers were loosened.
- Incorrect upload/discard confirmation remains below preview. Cancel receives initial focus; existing retention behavior is unchanged. Related documents retain their existing table/pagination.

## Investigation findings and limits

The reported Chrome "Page Unresponsive" warning **was not reproduced and its root cause is not established**. The user could not identify whether it affects seeded versus uploaded PDFs. No connected Chrome session was available; only the Codex embedded browser was available for UI testing. That browser does not render native PDFs, so it cannot reproduce Chrome's PDF renderer behavior or prove native multi-page toolbar behavior.

Read-only checks of all 12 current seeded PDFs passed strict `pypdf` parsing, text extraction and Poppler `pdfinfo`. Each has one page. A representative page rasterized successfully and was visually checked. The Poppler runtime warned about missing Symbol/ArialUnicode fallback fonts, while rendering succeeded; these warnings are not evidence of malformed source PDFs. No existing PDF bytes were modified.

The source review found no reactive iframe-update loop: the existing detail iframe URL was stable. The new reusable renderer makes this explicit by watching UUID/availability primitives and retains native embedding rather than replacing it with a new PDF engine.

Endpoint tests confirm authenticated PDF HEAD responses with correct MIME/content length, private/no-store caching, inline disposition, nosniff/CSP headers and **206 partial content** with the requested Content-Range. Anonymous access redirects to login; missing files return 404; discarded HEAD previews remain denied by policy (403). This is existing authorization behavior, not a relaxation.

An isolated SQLite/files/session fixture was used for browser testing. A request journal recorded one HEAD and one image/PDF GET on initial preview load, with no extra preview request after metadata editing and Save Draft. One iframe was present for the PDF. Image zoom and draft/filing actions worked; forms remained responsive. Console reported no warnings/errors. No browser-level CPU/memory profile of Chrome was available, and unrelated uploaded PDFs were not available to reproduce the warning.

To establish the remaining browser-specific cause, the useful evidence is an affected file/version of Chrome and a native Chrome trace, including whether opening the same original separately also hangs. No timeout increase, error suppression or speculative PDF rewrite was made. The preview loading/error improvements do not constitute a confirmed fix for the unresponsive warning.

## Verification

- 104 Laravel tests passed, 1,451 assertions. Existing review/filing/correction/discard/upload tests remain intact; new tests cover preview HEAD/range/privacy and both old slug routes/preselection.
- Pint, TypeScript, ESLint, Prettier and production build passed.
- Desktop and mobile layouts checked in Light and Dark. One metadata form; controls stay within mobile viewport. Image zoom, Save Draft, File Document, discard confirmation focus and missing-original presentation checked with isolated data.
- The active MySQL database remains `shipping_receiving_archive`; the safety guard remains active. No other project database was accessed. The isolated fixture and temporary server/scripts are removed after verification.

## Naming proposal only

See [intelligent-document-naming-plan.md](intelligent-document-naming-plan.md). It proposes reviewed display titles separate from immutable originals, explicit invoice/order precedence, structured router ranges, confirmed dates and single-company preference configuration. OCR and all naming-related schema/UI/settings changes await approval; Phase 4 has not started.
