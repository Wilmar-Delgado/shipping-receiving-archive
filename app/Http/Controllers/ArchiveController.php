<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Party;
use App\Services\DocumentQuery;
use App\Support\ArchiveCategories;
use App\Support\DocumentData;
use App\Support\DocumentMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    public function category(Request $request, DocumentType $documentType, DocumentQuery $query): Response
    {
        Gate::authorize('viewAny', Document::class);
        $filters = $this->filters($request);

        return Inertia::render('archive/Category', [
            'category' => ['name' => $documentType->name, 'label' => ArchiveCategories::label($documentType->slug, $documentType->name), 'slug' => $documentType->slug, 'section' => ucfirst($documentType->section), 'metadata' => DocumentMetadata::configuration($documentType)],
            'documents' => $query->filtered($filters, $documentType->id)->paginate(10)->withQueryString()->through(DocumentData::summary(...)),
            'filters' => $filters, 'parties' => $this->parties(),
        ]);
    }

    public function search(Request $request, DocumentQuery $query): Response
    {
        Gate::authorize('viewAny', Document::class);
        $filters = $this->filters($request);

        return Inertia::render('archive/Search', [
            'documents' => $query->filtered($filters)->paginate(10)->withQueryString()->through(DocumentData::summary(...)),
            'filters' => $filters, 'parties' => $this->parties(),
            'documentTypes' => DocumentType::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'date_field' => ['sometimes', 'required', Rule::in(['document_date', 'handled_on', 'created_at'])],
            'sort' => ['sometimes', 'required', Rule::in(['newest', 'oldest'])],
        ]);

        return array_map(fn ($value) => $value ?? '', array_replace(['q' => '', 'party_id' => '', 'document_type_id' => '', 'from' => '', 'to' => '', 'date_field' => 'document_date', 'sort' => 'newest'], $validated));
    }

    private function parties(): mixed
    {
        return Party::orderBy('name')->get(['id', 'name', 'kind']);
    }
}
