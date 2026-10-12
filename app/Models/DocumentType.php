<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'section', 'requires_handled_on', 'sort_order'];

    protected function casts(): array
    {
        return ['requires_handled_on' => 'boolean', 'sort_order' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
