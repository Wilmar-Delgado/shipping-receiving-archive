<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['document_type_id', 'party_id', 'status', 'document_date', 'handled_on', 'carrier', 'notes', 'uploaded_by', 'reviewed_by', 'reviewed_at'];

    protected static function booted(): void
    {
        static::creating(fn (Document $document) => $document->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['document_date' => 'date', 'handled_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function references(): HasMany
    {
        return $this->hasMany(DocumentReference::class)->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('sort_order');
    }
}
