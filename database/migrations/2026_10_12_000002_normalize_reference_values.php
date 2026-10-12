<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $normalize = fn (string $value): string => preg_replace('/[^\p{L}\p{N}]/u', '', Str::upper($value));
        DB::transaction(function () use ($normalize) {
            $seen = [];
            // Preflight the entire set before writing anything. No reference/display value is deleted.
            DB::table('document_references')->orderBy('id')->lockForUpdate()->chunkById(500, function ($references) use (&$seen, $normalize) {
                foreach ($references as $reference) {
                    $value = $normalize($reference->value);
                    $key = $reference->document_id.'|'.$reference->kind.'|'.$value;
                    if ($value === '' || isset($seen[$key])) {
                        throw new RuntimeException('Reference normalization has an empty or conflicting value in document ID '.$reference->document_id.'. Resolve its references before retrying. No references have been changed.');
                    }
                    $seen[$key] = true;
                }
            });
            DB::table('document_references')->orderBy('id')->lockForUpdate()->chunkById(500, function ($references) use ($normalize) {
                foreach ($references as $reference) {
                    DB::table('document_references')->where('id', $reference->id)->update(['normalized_value' => $normalize($reference->value)]);
                }
            });
        });
    }

    public function down(): void
    {
        // Deliberately preserve normalized data and all references on rollback.
    }
};
