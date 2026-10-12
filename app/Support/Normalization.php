<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Normalization
{
    // Preserve punctuation so distinct business identifiers are not merged.
    public static function value(string $value): string
    {
        return Str::upper(Str::squish($value));
    }

    public static function reference(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', Str::upper($value));
    }
}
