<?php

namespace App\Support;

use App\Models\Document;

final class DocumentData
{
    public static function summary(Document $document): array
    {
        return [
            'uuid' => $document->uuid, 'category' => $document->documentType->name,
            'is_demo' => str_starts_with($document->notes ?? '', '[DEMO pagination v1]') || str_starts_with($document->notes ?? '', '[DEMO compact v1]'),
            'category_slug' => $document->documentType->slug,
            'party' => $document->party?->only(['id', 'name', 'kind']),
            'document_date' => $document->document_date?->format('Y-m-d'),
            'handled_on' => $document->handled_on?->format('Y-m-d'),
            'carrier' => $document->carrier, 'uploaded_by' => $document->uploader->name,
            'created_at' => $document->created_at->toIso8601String(), 'status' => $document->status,
            'references' => $document->references->map->only(['kind', 'value'])->all(),
            'file' => $document->files->first()?->only(['original_name', 'mime', 'size']),
        ];
    }
}
