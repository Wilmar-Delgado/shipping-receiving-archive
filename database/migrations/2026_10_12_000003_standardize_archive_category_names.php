<?php

use App\Support\ArchiveCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        ArchiveCategories::synchronizeNames();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $types = DB::table('document_types')->whereIn('slug', ['rmas', 'return-merchandise-authorization'])->lockForUpdate()->get();
            if ($types->count() > 1) {
                throw new RuntimeException('Both RMA slugs exist. Rollback stopped without modifying either category.');
            }
            DB::table('document_types')->where('slug', 'return-merchandise-authorization')->update(['slug' => 'rmas']);
        });
    }
};
