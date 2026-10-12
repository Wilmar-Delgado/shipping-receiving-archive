# Archive UI refinement — October 12, 2026

This work remains before Phase 4. No OCR, schema changes, new dependencies, or new application Settings feature were introduced.

## Changes

- `resources/css/app.css`: stronger shared light text/teal tokens, theme-aware warning colors, readable placeholders and wrapping table headers. Dark navy/teal and the existing Instrument Sans font remain. Light, Dark and System preferences are supported.
- `ArchiveModal.vue`: shared Headless UI dialog, focus trap, Escape/outside close, accessible title/description and mobile sizing. Portal content carries Archive theme tokens.
- `DocumentPreviewModal.vue`, `DocumentActions.vue`, `DocumentTable.vue`: visible right-aligned Actions header, labeled eye/download actions, one preview dialog per table, PDF/image preview using the existing authorized route, loading/error/no-original states, secure download and object-URL cleanup. Private paths never enter UI props.
- `ArchivePagination.vue`, Archive/Inbox/Admin User controllers: server-side 10-record pages, totals/ranges, numbered buttons, active/disabled states, retained query parameters. Applying document filters starts at page 1. Dashboard recent documents remain an intentionally unpaginated five-record summary; related documents already paginate at ten.
- `admin/Users.vue`: full-width Team Members table, top-right Create User button, responsive dialog, server validation errors, duplicate-submission prevention, Inertia refresh after creation and keyboard focus restoration. Existing creation policy, password hashing, account status controls and last-admin safeguards remain.
- `InputError.vue`, pending status/stat components, Inbox and document details: readable error/warning colors, live validation announcements and theme-aware modal content.
- `PaginationDemoSeeder.php`, DocumentData/Inbox summaries and archive types: explicit, additive fictional test data and clearly labeled demo listings.
- `ArchiveRefinementTest.php` and existing pagination assertions: focused coverage and expectations updated for ten-record pages.

## Demo data and database preservation

The active local MySQL database was verified with both the configured connection name and `SELECT DATABASE()` as `shipping_receiving_archive` before database work. The database-name guard remains in place. Construction Workforce was never accessed.

The dedicated seeder was explicitly run on the local Archive database. It added 750 fictional documents: 125 per category (110 filed, 12 Inbox, 3 discarded). Dates, parties, carriers, uploaders and PO/job/invoice/tracking references vary. Vendor POs repeat across partial deliveries. Three fictional Staff uploaders are inactive with randomly generated passwords; no usable demo credentials are published. Eight clearly fictional parties were added. No document file records or original files were created by the seeder.

Before/after hash comparisons verified that all previously existing users, categories, parties, documents, references, file records and private document file bytes remained unchanged. A second seeder run created no duplicates. Stable versioned UUIDs also prevent edited or soft-deleted demo records from being overwritten or resurrected. The default DatabaseSeeder does not invoke this bulk seeder; production execution is refused.

To repeat explicitly from the project directory in a local environment:

```powershell
php artisan db:seed --class=PaginationDemoSeeder
```

Demo records intentionally have no original files. Preview explains this; Download is disabled. They can be distinguished by DEMO labels, fictional parties/uploaders and DEMO references. They are database fixtures, not scanned paperwork.

## Verification

- Laravel suite: 95 tests, 1,109 assertions passed, using guarded in-memory SQLite.
- PHP Pint, TypeScript, ESLint, Prettier and production build checked.
- Isolated browser fixture (separate SQLite, private files and sessions): all six category pages show ten rows/actions/ranges; Global Search category changes reset page one and pagination retains selection; valid fictional PNG preview loads; PDF authorized bytes load into the iframe; missing-original messages and Escape/focus return work.
- Duplicate-email validation keeps Create User open; successful fictional creation closes it and updates the table without a full reload. No real user was created through browser testing.
- Light and Dark rendered listing text checked against a 4.5:1 threshold (including translucent backgrounds); initial marginal light teal labels were corrected. This is focused contrast verification, not a claim of complete WCAG certification.
- System preference and responsive 1366px/390px layouts checked. Dialogs fit mobile widths; table overflow stays in its own scroll region.

The embedded test browser does not render native PDFs, although authorized PDF bytes load correctly. PDF display uses the browser's existing native viewer; the visible secure Download fallback remains available. No PDF rendering library was added.

Temporary browser data and server are removed after verification. Live Archive private storage was not used by these browser fixtures.
