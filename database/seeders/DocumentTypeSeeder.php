<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Support\ArchiveCategories;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        ArchiveCategories::synchronizeNames();
        foreach (array_keys(config('archive-categories')) as $index => $slug) {
            $type = config('archive-categories.'.$slug);
            DocumentType::updateOrCreate(['slug' => $slug], [
                'name' => $type['name'], 'section' => $type['section'],
                'requires_handled_on' => $type['requires_handled_on'], 'sort_order' => $index + 1,
            ]);
        }
    }
}
