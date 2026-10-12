<?php

namespace App\Services;

use App\Models\Document;
use App\Support\Normalization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DocumentQuery
{
    public function filed(): Builder
    {
        return Document::query()->where('status', 'filed')->with(['documentType', 'party', 'uploader', 'references', 'files' => fn ($query) => $query->where('role', 'original')]);
    }

    public function filtered(array $filters, ?int $typeId = null): Builder
    {
        $query = $this->filed();
        if ($typeId !== null) {
            $query->where('document_type_id', $typeId);
        }
        if ($typeId === null && filled($filters['document_type_id'] ?? null)) {
            $query->where('document_type_id', $filters['document_type_id']);
        }
        if (filled($filters['q'])) {
            $reference = Normalization::reference($filters['q']);
            if ($typeId !== null) {
                $reference !== '' ? $query->whereHas('references', fn ($query) => $query->where('normalized_value', 'like', '%'.$reference.'%')) : $query->whereRaw('1 = 0');
            } else {
                $party = '%'.$this->escape(Normalization::value($filters['q'])).'%';
                $filename = '%'.$this->escape(mb_strtolower($filters['q'])).'%';
                $query->where(function ($query) use ($reference, $party, $filename) {
                    if ($reference !== '') {
                        $query->whereHas('references', fn ($query) => $query->where('normalized_value', 'like', '%'.$reference.'%'));
                    }
                    $query->orWhereHas('party', fn ($query) => $query->whereRaw("normalized_name LIKE ? ESCAPE '!'", [$party]))
                        ->orWhereHas('files', fn ($query) => $query->where('role', 'original')->whereRaw("LOWER(original_name) LIKE ? ESCAPE '!'", [$filename]));
                });
            }
        }
        if (filled($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }
        $field = $filters['date_field'];
        if (filled($filters['from'])) {
            $query->where($field, '>=', $filters['from'].($field === 'created_at' ? ' 00:00:00' : ''));
        }
        if (filled($filters['to'])) {
            // A next-day exclusive bound includes fractional timestamps and keeps indexes usable.
            $end = CarbonImmutable::parse($filters['to'])->addDay()->format('Y-m-d').($field === 'created_at' ? ' 00:00:00' : '');
            $query->where($field, '<', $end);
        }
        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }

    public function related(Document $document): Builder
    {
        $references = $document->references;
        $query = $this->filed()->where('id', '!=', $document->id);
        if ($references->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('references', function ($query) use ($references) {
            $query->where(function ($query) use ($references) {
                foreach ($references as $reference) {
                    $query->orWhere(fn ($query) => $query->where('kind', $reference->kind)->where('normalized_value', $reference->normalized_value));
                }
            });
        })->latest('created_at')->latest('id');
    }

    private function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
