# Shipping & Receiving Archive

Standalone Laravel 12 / Vue 3 / Inertia 2 / Tailwind CSS / Vite application for a manufacturing document archive. Phases 1–3: authentication, user administration, private uploads, document metadata/review, filing, category archives, filters, and global metadata search.

## Local setup (PowerShell)

PHP 8.4, Composer, Node 22, npm, and MySQL are supported. Run commands from this project directory.

1. Install dependencies: `composer install` and `npm ci --cache .npm-cache`.
2. Copy `.env.example` to `.env` if needed. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for a dedicated MySQL database. Never commit `.env`.
3. Create the database `shipping_receiving_archive` using your MySQL administration tool (utf8mb4). Give a dedicated application account access to that database. An application guard requires this exact database name to prevent accidental access to unrelated projects.
4. Run `php artisan key:generate` for a new installation only. The key was generated during initial setup.
5. First run `php artisan archive:verify-database` to confirm the dedicated connection (on a brand-new database, tables will be missing). Then run `php artisan migrate --seed` to create the schema. Local development also seeds the compact fictional dataset described below; production seeds only document categories.
6. For a non-demo installation, run `php artisan archive:create-admin`. Name, email, and password are prompted; passwords are not passed on the command line. Local development already includes the fictional Admin and Staff accounts.
7. Run `npm run build`, then `composer run dev`. Open http://localhost:8000.

Public registration is disabled. Admins create subsequent accounts at `/admin/users`. New accounts are active immediately. Password reset uses Laravel's standard mail flow; configure SMTP before relying on it. Local mail defaults to the Laravel log.

## Default local fictional demo

Normal `php artisan migrate --seed` or `php artisan db:seed` uses the compact demo in local/testing environments. To invoke it explicitly:

```powershell
php artisan db:seed --class=DemoSeeder
```

Accounts: `admin@example.test` (Admin), `staff@example.test` (Staff). Both initially use the previously documented development password `ArchiveDemo!2026`. These accounts are fictional and development-only. The demo seeder only runs in local/testing environments, is repeatable, and does not reset existing account passwords. It creates four fictional parties, six categories, 48 filed records (eight per category), six Inbox records (one per category), and 180 PO/job/invoice/tracking references. Repeated POs simulate partial deliveries. Eighteen valid fictional PDF/PNG fixtures are generated on the private documents disk for preview/download testing; the other records explicitly have no original.

Production `DatabaseSeeder` creates only categories. Factories are provided for all archive models and users; document-file factories generate metadata only. `PaginationDemoSeeder` remains a separate optional local/testing seeder that adds 750 records; normal seeding never invokes it. Compact seeding preserves edited/soft-deleted demo records and existing fixture bytes, and refuses to overwrite orphaned fixture paths. Inspect and back up a database before any reset; private storage is never automatically deleted.

The October 12 local reset, backup and verified counts are recorded in [docs/local-data-cleanup.md](docs/local-data-cleanup.md).

## Foundation behavior

- Admin and Staff are simple roles on users; Laravel auto-discovers UserPolicy and DocumentPolicy.
- Staff can access archive areas but cannot list, create, or change users. Deactivated users cannot log in and are logged out on their next request.
- Admins cannot deactivate themselves; the last active admin is protected. Self-service user deletion is disabled to preserve ownership history.
- Receiving: Vendor Packing Slips, Material Test Reports (MTRs), RMAs. Shipping: Customer Packing Slips, Waybills. Job Routers have a separate area.
- Dashboard provides navigation. Inbox supports uploads, metadata review, drafts, and filing. Each category lists its filed records; Global Search searches filed references, parties, and filenames. OCR, retention, and audit logging are deferred.
- Private `documents` disk stores original files under `storage/app/private/documents`, with private visibility, no serving route, and no URL configuration. Do not symlink this directory into `public`.
- `document_date` is the printed date; `handled_on` is the actual receiving/pickup date; `created_at` records creation time. Nullable business dates are never inferred from yesterday.
- One document can reference several POs/jobs/invoices/tracking numbers. Reference keys uppercase and strip spaces/punctuation while preserving letters, digits, and leading zeros; display values stay intact. Party names uppercase and collapse whitespace while preserving punctuation. A unique database constraint blocks duplicate document/kind/normalized references; the same PO can recur across shipments.
- Documents use UUIDs and soft deletion. Category, party, and user foreign keys restrict deletion of referenced records. Reference/file metadata cascades only on a future permanent document deletion.
- `requires_handled_on` currently applies to packing slips, RMAs, and waybills. MTRs and routers do not require it. This flag is architectural metadata; filing validation is deferred.

## Verification

```powershell
php artisan archive:verify-database
php artisan test
php vendor/laravel/pint/builds/pint --test
npx vue-tsc --noEmit
npm run build
```

Tests use isolated in-memory SQLite, never the configured MySQL database. They exercise authentication, inactive accounts, role authorization, admin validation/status changes, registration/deletion restrictions, category routes, normalized reference uniqueness, relationships, dates, soft deletion, demo seed repeatability, and private storage configuration. Production MySQL migration verification requires valid project credentials.

## Scope and architecture

One Laravel monolith. No Docker, Redis, external search service, permissions package, or OCR dependency is needed. The official Laravel Vue starter kit supplies the authentication foundation: https://laravel.com/starter-kits.

Phase 4 requires explicit approval before implementing OCR, text extraction, or searching scanned contents.

## Initial verification notes

The dedicated MySQL database migrated and seeded successfully. The original starter npm lockfile was updated within compatible ranges, reducing advisories from 42 (including two critical) to 10 (six high, four moderate). Remaining advisories concern the Tailwind/ESLint/PostCSS tooling dependency chain. No forced major upgrades were applied; review them before deployment.

The initial Phase 1 database-target incident was resolved by the user. Phase 2 did not connect to or modify the Construction Workforce database. The active Archive database was independently verified as `shipping_receiving_archive` before implementation. The guard checks resolved DB_URL values and explicit MySQL/MariaDB connections. No Phase 2 schema changes or migrations were required.

## Phase 2: Upload and Inbox

- Open `/inbox`, choose one of the six categories, and drag/drop files or use Choose files. Take photo uses browser camera capture where supported; unsupported browsers use a picker. Only PDF, JPG/JPEG, and PNG are accepted; HEIC must be converted before uploading.
- Each file creates a separate `inbox` document and one `original` file record. Internal UUID filenames, detected MIME, original filename, size, and SHA-256 are recorded. Original bytes are unchanged. Storage paths and disk names are omitted from frontend props.
- Uploads are batch-atomic: validation rejects the entire batch before storage; storage or database errors roll back all new records and remove files written by the batch. Cleanup errors are logged if the filesystem itself prevents cleanup; administrators should investigate those logs.
- Duplicate SHA-256 checksums produce warnings after a successful upload, including copies within the same batch. Copies are still accepted, even if the existing record is discarded. Simultaneous independent uploads may complete before one sees the other; duplicate detection is advisory.
- Inbox is paginated at 20 documents per page, shows category/uploader/date/type/size/status, and excludes filed, discarded, and soft-deleted records.
- Active Admin and Staff share access to uploaded documents. All detail, preview, download, correction, and discard routes use DocumentPolicy. Guests and inactive accounts cannot access them.
- PDFs use an embedded browser viewer; images display inline. Open preview and Download original provide fallbacks on browsers without embedded PDF support. Downloads and range-capable previews go through authenticated Laravel routes with private/no-store headers; there are no public document URLs.
- Inbox records support drafts and filing; filed records allow metadata corrections. Discard requires frontend confirmation, changes status to `discarded`, blocks preview/download/edit access, and keeps the physical file and record. No permanent deletion is implemented.
- Missing files show an unavailable message; direct file routes return 404. Unsupported content/extensions, empty files, oversized files, PHP upload errors, invalid categories, and too many files return validation errors.

### Upload limits

`ARCHIVE_UPLOAD_MAX_KB=20480` (20 MB per file) and `ARCHIVE_UPLOAD_MAX_FILES=20` are configurable in `.env`. Run `php artisan config:clear` after editing configuration. PHP/web-server limits also apply: set `upload_max_filesize` at least as high as the per-file limit, `max_file_uploads` at least as high as the batch count, and `post_max_size` high enough for the chosen batch plus multipart overhead. Current CLI PHP allows 64 MB per file and 64 MB per request, so large batches must be split. The browser checks total selected bytes against PHP's request limit with overhead reserved. Laravel renders a plain 413 error for oversized requests. A proxy's lower limit must be adjusted separately. No global PHP settings were changed.

### Phase 2 verification

The suite uses fake storage under this project and isolated in-memory SQLite. It verifies uploads for both roles, guest/inactive denial, MIME and extension validation (including a real fictional text fixture disguised as PDF), size/count/category/empty/PHP upload errors, independent multiple files, unchanged checksums, pagination, private props and routes, PDF/image response headers, missing-file handling, category/notes correction, discard/access restrictions, duplicate warnings, and cleanup after partial storage/database failures. Database guard tests do not create PDO connections to excluded databases.

Final Phase 2 verification: 60 tests passed (357 assertions); PHP formatting, Vue/TypeScript checking, and the production frontend build passed. The active database was reverified as shipping_receiving_archive. No Phase 2 migrations were required, and Phase 3 remains unimplemented.

## Phase 3: Metadata, filing, and retrieval

Review a document from the Inbox. The metadata form adapts to its category, accepts multiple PO/job/invoice/tracking references, and can select an existing vendor/customer or create one inline. Save Draft retains `inbox` status and permits missing filing requirements; invalid dates, incompatible parties, empty reference rows, and duplicate normalized references are rejected even in drafts. Removing a reference row intentionally removes that metadata on save, never its original file.

File Document validates the complete metadata transactionally, changes status to `filed`, records the reviewing user/time, and removes the record from the active Inbox. Admin and Staff can correct filed metadata through Save corrections; filing requirements still apply, status remains filed, original filing identity/time are preserved, and file bytes/checksums are unchanged. Re-filing and discarding a filed document are prohibited.

Filing requirements:

| Category | Required to file | Optional information |
| --- | --- | --- |
| Vendor Packing Slips | Vendor, at least one PO | Document date, other references, notes |
| MTRs | Valid category | Vendor, PO, document/received dates, notes |
| RMAs | Valid category | Known vendor/customer, references, document date, notes |
| Customer Packing Slips | Customer, at least one job | Carrier, invoice/tracking, document date, notes |
| Waybills | Carrier | Tracking, related job/invoice, document date, notes |
| Job Routers | At least one job | Document date, other references, notes |

A handled date is additionally required only when `document_types.requires_handled_on` is true. Existing configuration requires it for packing slips, RMAs, and waybills; MTRs and Job Routers do not require it. Received/pickup dates are never inferred. No invoice or tracking number is mandatory. Dates printed on documents can remain unknown.

Category pages list only filed records in that category, using a reusable table with appropriate party/carrier/date columns. Filters use shareable GET query parameters: `q` (reference fragment), `party_id`, `from`, `to`, `date_field` (`document_date`, `handled_on`, or `created_at`), and `sort` (`newest`/`oldest` upload). Date bounds include the entire selected end day; upload-date filters use UTC. Pagination retains filters.

Global Search searches filed metadata across all categories. `PO-45821`, `po45821`, and `PO 45821` match the same reference key. Reference fragments also match; party names and original filenames use case-insensitive substring matching, with literal `%` and `_` escaped. An empty search lists newest filed records. Search excludes Inbox, discarded, and soft-deleted records and does not inspect file contents or notes. Queries use existing document status/category/date/party indexes and reference indexes; substring searches can scan candidate values, which is appropriate for this micro-app's current scope.

Details show paginated related filed documents sharing an exact normalized reference of the same kind. The current record is excluded, each related record appears once, and connecting PO/job/invoice/tracking values are labeled. All detail/file routes retain policy authorization and private storage protections. Archive components now honor the starter kit's existing light/dark appearance preference without changing navigation structure.

### Reference normalization migration

`2026_10_12_000002_normalize_reference_values` updates only existing normalized reference keys. Display values, reference IDs, timestamps, records, and physical files are retained; indexes and the unique document/kind/normalized constraint remain in place. A transaction preflights the entire set and stops without changes if punctuation folding creates a within-document conflict or an empty key. Resolve the indicated references before retrying; no automatic merging/deletion is performed. Rollback deliberately retains the normalized data.

The migration ran successfully on the verified `shipping_receiving_archive` database. Read-only MySQL checks confirmed normalized search, party/filename queries, related-document matching, and existing reference indexes against fictional seed records. No excluded project/database was accessed.

### Test database isolation

PHPUnit sets both environment and server values to testing, SQLite, `:memory:`, and an empty DB_URL, covering inherited Windows environment variables. The base test case additionally verifies the resolved driver/database before RefreshDatabase can run. Persistent database connections and cached production configuration cause tests to stop before database writes.

Final Phase 3 verification: 85 tests passed (767 assertions). PHP formatting, Vue/TypeScript checks, and the production build passed. An additional run with inherited MySQL connection/database/URL variables still used isolated in-memory SQLite. The active production connection was reverified as shipping_receiving_archive with five completed migrations. Phase 4 has not started.

## UI redesign (October 12, 2026)

The archive now uses the Figma reference's navy/near-black surfaces, teal actions, compact navigation, and shared table/filter styling. `ArchiveLayout` delegates to ArchiveSidebar, ArchiveTopbar, ArchiveBrand, and a Headless UI UserDropdown. The sidebar contains navigation only; account settings/sign-out live in the user dropdown. There is no topbar search input and no application-wide administration Settings feature.

All six categories use the same Category page, category display configuration, DocumentTable, FilterBar, and ArchivePagination. Table modes cover filed archives, Global Search, Inbox review, and recent Dashboard records; file icons, status/category badges, and actions are separate reusable components. Category filters adapt party/date controls to the category; global search additionally accepts a validated `document_type_id` query parameter. Date ranges, normalized references, party/filename search, sorting, clear behavior, and query-preserving pagination remain functional.

Dashboard metrics are authorized read-only aggregates from real document records (excluding soft-deleted/discarded records). Receiving/Shipping statistics count filed records in those sections. Recent documents sort by filing time, then upload time and ID. No prototype numbers or records are inserted into the application database.

Upload actions use the existing single uploader. Category links preselect a real seeded category; selection/camera, multiple files, size validation, duplicates, and progress remain intact. Existing metadata/draft/filing/correction, private preview/download, related-document, user-management, and account workflows are retained. Dark is the default for new appearance preferences; saved Light/Dark/System choices remain functional.

Tables fit standard desktop widths and use controlled, keyboard-focusable horizontal scrolling on narrow screens. The mobile drawer and dropdowns provide focus handling, Escape, and outside-click dismissal. No CSS zoom or whole-app scale is used. Browser checks covered 1366×768, 1920×1080, 390×844, and 320×800 using a separate SQLite fixture database and private test files; those checks never connected to or changed the application database. The original database-name guard is unchanged.

Prototype-only export and application-wide Settings controls are omitted because no corresponding backend exists. Page-count decorations are not invented; file details show actual size/type. Native embedded PDF support still depends on the browser, with secure Open preview/Download fallback retained.

The redesign adds no frontend framework or dependencies and requires no migrations. Prettier's existing Vue support was sufficient. Verification: 90 Laravel tests (843 assertions), PHP formatting, Vue/TypeScript, ESLint, Prettier, and production build checks.

UI refinement, demo seeding safeguards, modified components and verification: see [docs/ui-refinement.md](docs/ui-refinement.md).

Category names and navigation labels are centralized in `config/archive-categories.php`. MTRs uses the heading "Material Test Reports (MTRs)" at `/archive/material-test-reports`. RMAs uses "Return Merchandise Authorizations (RMAs)" at `/archive/return-merchandise-authorizations`; `/archive/rmas` and `/archive/return-merchandise-authorization` redirect permanently with query parameters retained. The naming migration updates the existing RMA type in place, retaining its ID and all document relationships. Both demo seeders preserve their legacy fixture identities to prevent duplicates after this slug change. See [docs/category-naming.md](docs/category-naming.md).

Document details layout, PDF diagnostics and verification: [docs/document-details-and-pdf-investigation.md](docs/document-details-and-pdf-investigation.md). Intelligent naming remains [a proposal only](docs/intelligent-document-naming-plan.md); no Phase 4 implementation is included.
