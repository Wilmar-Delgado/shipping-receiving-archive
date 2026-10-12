<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $types = DB::table('document_types')->whereIn('slug', ['rmas', 'return-merchandise-authorization', 'return-merchandise-authorizations'])->lockForUpdate()->get();
            if ($types->count() > 1) {
                throw new RuntimeException('Multiple RMA slugs exist. No categories were modified.');
            }
            if ($type = $types->first()) {
                DB::table('document_types')->where('id', $type->id)->update(['slug' => 'return-merchandise-authorizations']);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $types = DB::table('document_types')->whereIn('slug', ['rmas', 'return-merchandise-authorization', 'return-merchandise-authorizations'])->lockForUpdate()->get();
            if ($types->count() > 1) {
                throw new RuntimeException('Multiple RMA slugs exist. Rollback stopped.');
            }
            DB::table('document_types')->where('slug', 'return-merchandise-authorizations')->update(['slug' => 'return-merchandise-authorization']);
        });
    }
};
