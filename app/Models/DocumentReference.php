<?php

namespace App\Models;

use App\Support\Normalization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReference extends Model
{
    use HasFactory;

    protected $fillable = ['document_id', 'kind', 'value'];

    protected static function booted(): void
    {
        static::saving(fn (DocumentReference $reference) => $reference->normalized_value = Normalization::reference($reference->value));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
