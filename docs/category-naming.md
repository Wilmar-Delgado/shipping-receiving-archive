# Category naming — October 12, 2026

The RMA category page rendered `document_types.name`, which was still `RMAs` in the existing database, despite a fuller name in the edited seeder. Editing a seeder does not execute it against existing rows. Sidebar and Dashboard menus also had independent hardcoded labels.

Canonical category names and short navigation labels now live in `config/archive-categories.php`. DocumentTypeSeeder reads that configuration; the frontend receives its short labels/links through shared Inertia props. Category headings retain database-driven full names, while category and document-detail breadcrumbs use short labels. The sidebar and Dashboard links use the same shared labels. Existing descriptions/filter placeholders stay in their display configuration.

| Category | Sidebar / breadcrumb | Page heading | Slug / URL |
| --- | --- | --- | --- |
| MTR | MTRs | Material Test Reports (MTRs) | `material-test-reports` / `/archive/material-test-reports` |
| RMA | RMAs | Return Merchandise Authorizations (RMAs) | `return-merchandise-authorizations` / `/archive/return-merchandise-authorizations` |

The remaining category names and URLs are unchanged.

## Existing database preservation

The active database was verified as `shipping_receiving_archive`. A single new data migration ran, without any reset or schema alteration. Existing RMA type ID 3 was renamed in place; its nine documents retain the same type ID. The MTR name was synchronized with the canonical full title. Only the intended category name/slug fields were updated.

Before/after row hashes verified preservation of all 54 documents, references, file records, users and parties, and all category IDs/other fields. Checksums and paths of all 18 private documents were unchanged. No other project database was accessed.

The migration is safe to rerun. Both migration and seeder stop without changing categories if old and new RMA slugs coexist, rather than automatically merging records. Both demo seeders use the original version-one RMA identity when generating UUIDs, so rerunning them after the slug rename does not duplicate documents or overwrite existing fixtures. Factories have no hardcoded category slugs and needed no changes.

## Compatibility and verification

- Authenticated `/archive/rmas` requests permanently redirect to the new route with query parameters retained.
- Legacy `/inbox?category=rmas` links still preselect the same RMA type. New upload links use the canonical slug. Uploaded files continue to use numeric type IDs.
- RMA metadata configuration retains vendor/customer selection and handled-date behavior.
- Search/category filters, document details, related records and protected file endpoints retain existing IDs/relationships.
- 102 Laravel tests passed (1,413 assertions), including canonical names/shared labels, redirects, upload preselection, existing files, migration reruns, collision refusal and stable compact-seeder identities. Existing filing tests use the canonical RMA slug.
- Pint, TypeScript, ESLint, Prettier and production build checked.
- Browser verification confirmed both sidebar labels/full headings/short breadcrumbs, the canonical RMA upload preselection, and legacy redirect with retained query/sort values. Browser console reported no warnings or errors. The temporary verification server and scripts were removed.

No UI redesign, database reset, or Phase 4/OCR work was performed.

The subsequent plural-slug correction is documented in [document-details-and-pdf-investigation.md](document-details-and-pdf-investigation.md). The initial singular canonical URL is also retained as a redirect.
