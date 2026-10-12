<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ArchiveCategories
{
    public const RMA_SLUG = 'return-merchandise-authorizations';

    public const LEGACY_RMA_SLUGS = ['rmas', 'return-merchandise-authorization'];

    public static function synchronizeNames(): void
    {
        DB::transaction(function () {
            $types = DB::table('document_types')->whereIn('slug', [...self::LEGACY_RMA_SLUGS, self::RMA_SLUG])->lockForUpdate()->get();
            if ($types->count() > 1) {
                throw new RuntimeException('Multiple RMA slugs exist. Resolve the duplicate category explicitly; no category data was changed.');
            }
            if ($type = $types->first()) {
                DB::table('document_types')->where('id', $type->id)->update([
                    'slug' => self::RMA_SLUG,
                    'name' => config('archive-categories.'.self::RMA_SLUG.'.name'),
                ]);
            }
            DB::table('document_types')->where('slug', 'material-test-reports')->update([
                'name' => config('archive-categories.material-test-reports.name'),
            ]);
        });
    }

    public static function label(string $slug, string $fallback): string
    {
        return config('archive-categories.'.$slug.'.label', $fallback);
    }

    public static function demoIdentity(string $slug): string
    {
        // Version-one fixture UUIDs used the old slug. Preserve their identity after renaming.
        return in_array($slug, [...self::LEGACY_RMA_SLUGS, self::RMA_SLUG], true) ? 'rmas' : $slug;
    }
}
