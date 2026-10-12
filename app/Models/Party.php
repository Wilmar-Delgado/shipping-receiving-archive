<?php

namespace App\Models;

use App\Support\Normalization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'kind'];

    protected static function booted(): void
    {
        static::saving(fn (Party $party) => $party->normalized_name = Normalization::value($party->name));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
