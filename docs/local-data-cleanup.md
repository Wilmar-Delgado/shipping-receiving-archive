# Compact local development data — October 12, 2026

The local database was reset only after confirming all existing records were recognized fictional demo data. Before reset it contained 756 documents (750 pagination fixtures and six older samples), five demo users, ten fictional parties, six categories, 2,512 references and zero file records. Private document storage contained zero files and no orphans.

## Database safety and backup

- Active MySQL database verified by connection configuration and `SELECT DATABASE()`: `shipping_receiving_archive`.
- Raw `.env` and all alternate SQL connection configurations resolved to that database; `DB_URL` was empty. No cached configuration was present.
- Existing AppServiceProvider database-name guard was active. No other project database was connected to or modified.
- A dedicated single-database MySQL dump was created before reset. Its completion marker and SHA-256 checksum were verified. The temporary credential file was removed, and the backup remains private and Git-ignored.
- Backup: `storage/app/private/backups/shipping_receiving_archive-before-compact-seed-20261012-173212.sql`; matching JSON checksum/count manifest is beside it.
- Final pre-reset checks reconfirmed disposable records, empty private storage, unchanged row counts and matching backup checksum.
- Laravel `migrate:fresh` was explicitly restricted to the verified `mysql` connection, followed by the default seed. Five migrations completed; none remain pending. No private files were deleted.

## Default development workflow

`DatabaseSeeder` invokes compact `DemoSeeder` only in local/testing environments; other environments seed document categories only. The optional `PaginationDemoSeeder` remains separate and is not invoked by default.

The compact seed creates clearly marked fictional records, two active demo accounts, varied dates/parties/carriers and normalized PO/job/invoice/tracking references. Three documents per category share a PO to model partial deliveries. It generates valid fictional PDFs and PNGs on the existing private disk, rather than attaching metadata for nonexistent originals.

Seeder UUIDs are deterministic. Repeat seeding creates no duplicate documents, preserves metadata edits and soft deletion, and never overwrites an existing fixture path. A path without its owning document stops seeding for inspection; private file deletion is not automated.

## Verified final state

| Category | Filed | Inbox | Total |
| --- | ---: | ---: | ---: |
| Vendor Packing Slips | 8 | 1 | 9 |
| Material Test Reports | 8 | 1 | 9 |
| RMAs | 8 | 1 | 9 |
| Customer Packing Slips | 8 | 1 | 9 |
| Waybills | 8 | 1 | 9 |
| Job Routers | 8 | 1 | 9 |
| **Total** | **48** | **6** | **54** |

Supporting records: two users, four parties, six document types, 180 references and 18 original file records. Private storage contains 12 generated PDFs and six PNGs; all sizes and checksums match database metadata, and there are zero orphaned files.

Both Admin and Staff demo credentials validate. Live seeded data passes category counts, repeated-PO and filename searches, date filters, ten-record pagination, normalized references/relationships and authorized preview/download responses. The post-reset Laravel suite passes 99 tests with 1,290 assertions, covering actual login and route/policy behavior in isolated SQLite. Pint, TypeScript, ESLint, Prettier and the production build pass.

Changes are limited to seeders, generated demo fixtures, demo identification, focused tests and documentation. No UI redesign or Phase 4/OCR work was started.
