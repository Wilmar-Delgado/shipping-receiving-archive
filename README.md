# Shipping & Receiving Archive

Standalone Laravel 12 / Vue 3 / Inertia 2 / Tailwind CSS / Vite application for a manufacturing document archive. Phase 1 only: authentication, authorization, schema, private storage configuration, navigation placeholders, and administrator user management.

## Local setup (PowerShell)

PHP 8.4, Composer, Node 22, npm, and MySQL are supported. Run commands from this project directory.

1. Install dependencies: `composer install` and `npm ci --cache .npm-cache`.
2. Copy `.env.example` to `.env` if needed. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for a dedicated MySQL database. Never commit `.env`.
3. Create the database `shipping_receiving_archive` using your MySQL administration tool (utf8mb4). Give a dedicated application account access to that database. An application guard requires this exact database name to prevent accidental access to unrelated projects.
4. Run `php artisan key:generate` for a new installation only. The key was generated during initial setup.
5. Run `php artisan migrate --seed` to create the schema and six document categories.
6. Run `php artisan archive:create-admin`. Name, email, and password are prompted; passwords are not passed on the command line.
7. Run `npm run build`, then `composer run dev`. Open http://localhost:8000.

Public registration is disabled. Admins create subsequent accounts at `/admin/users`. New accounts are active immediately. Password reset uses Laravel's standard mail flow; configure SMTP before relying on it. Local mail defaults to the Laravel log.

## Optional fictional demo

On a local development database, run:

```powershell
php artisan db:seed --class=DemoSeeder
```

Accounts: `admin@example.test` (Admin), `staff@example.test` (Staff). Both initially use `ArchiveDemo!2026`. These are deliberately fictional, publicly documented development credentials. Do not seed them in production. The demo seeder only runs in local/testing environments, is repeatable, and does not reset existing account passwords. It creates two fictional parties, six sample records, and twelve PO/job references; no physical files.

Default `DatabaseSeeder` creates only categories. Factories are provided for all archive models and users; document-file factories generate metadata only.

## Foundation behavior

- Admin and Staff are simple roles on users; Laravel auto-discovers UserPolicy and DocumentPolicy.
- Staff can access archive areas but cannot list, create, or change users. Deactivated users cannot log in and are logged out on their next request.
- Admins cannot deactivate themselves; the last active admin is protected. Self-service user deletion is disabled to preserve ownership history.
- Receiving: Vendor Packing Slips, Material Test Reports (MTRs), RMAs. Shipping: Customer Packing Slips, Waybills. Job Routers have a separate area.
- Dashboard, Inbox, category pages, and Global Search are placeholders. No uploads, downloads, preview, filing workflow, filters, search, OCR, retention, or audit logging are implemented.
- Private `documents` disk stores future files under `storage/app/private/documents`, with private visibility, no serving route, and no URL configuration. Do not symlink this directory into `public`.
- `document_date` is the printed date; `handled_on` is the actual receiving/pickup date; `created_at` records creation time. Nullable business dates are never inferred from yesterday.
- One document can reference several POs/jobs/invoices/tracking numbers. Reference values and party names normalize by trimming, collapsing whitespace, and uppercasing, while preserving punctuation. A unique database constraint blocks duplicate document/kind/normalized references; the same PO can recur across shipments.
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

Phase 2 requires explicit approval before implementing uploads, private file handling, Inbox review, or secure previews/downloads.

## Initial verification notes

The dedicated MySQL database migrated and seeded successfully. The original starter npm lockfile was updated within compatible ranges, reducing advisories from 42 (including two critical) to 10 (six high, four moderate). Remaining advisories concern the Tailwind/ESLint/PostCSS tooling dependency chain. No forced major upgrades were applied; review them before deployment.

During initial setup, a user-updated `.env` pointed at `construction_workforce_marketplace`. The archive migration failed there on an existing `is_active` column, and may have added a `role` column in its preceding statement. Demo seeding failed before inserting records. No cleanup was attempted in that excluded database without explicit authorization. This app now rejects any MySQL database name other than `shipping_receiving_archive`.

Connection verification after correcting `.env`: configured and active MySQL database are both `shipping_receiving_archive`; all six application tables are present and four migrations are completed. Configuration cache was cleared. The safety guard checks resolved DB_URL values and explicit MySQL/MariaDB connections. Construction Workforce cleanup was declined by the user; that database remains untouched following the failed initial attempt.

Final backend verification: 38 tests passed with 125 assertions. Vue type declarations inherited from the starter kit were corrected; production build passes. Laravel Sail was removed because this project does not use Docker.
