<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentsRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\DocumentUploadService;
use App\Support\ArchiveCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Document::class);

        if (in_array($request->query('category'), ArchiveCategories::LEGACY_RMA_SLUGS, true)) {
            $request->merge(['category' => ArchiveCategories::RMA_SLUG]);
        }

        $options = DocumentType::orderBy('sort_order')->get(['id', 'name', 'slug']);
        $request->validate(['category' => ['nullable', 'string', 'exists:document_types,slug'], 'upload' => ['nullable', 'in:1']]);

        return Inertia::render('archive/Inbox', [
            'initialCategoryId' => $options->firstWhere('slug', $request->query('category'))?->id,
            'openUpload' => $request->query('upload') === '1',
            'documents' => Document::where('status', 'inbox')->with(['documentType:id,name', 'uploader:id,name', 'files' => fn ($query) => $query->where('role', 'original')])
                ->latest('id')->paginate(10)->withQueryString()->through(fn (Document $document) => [
                    'uuid' => $document->uuid, 'category' => $document->documentType->name,
                    'is_demo' => str_starts_with($document->notes ?? '', '[DEMO pagination v1]') || str_starts_with($document->notes ?? '', '[DEMO compact v1]'),
                    'uploaded_by' => $document->uploader->name, 'created_at' => $document->created_at->toIso8601String(),
                    'status' => $document->status,
                    'file' => $document->files->first()?->only(['original_name', 'mime', 'size']),
                ]),
            'documentTypes' => $options,
            'uploadLimits' => ['maxKb' => config('archive.upload_max_kb'), 'maxFiles' => config('archive.upload_max_files'), 'maxRequestBytes' => $this->requestLimitBytes()],
        ]);
    }

    public function store(UploadDocumentsRequest $request, DocumentUploadService $uploads): RedirectResponse
    {
        try {
            $warnings = $uploads->upload($request->file('files'), (int) $request->validated('document_type_id'), $request->user());
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['files' => 'The upload could not be completed. No documents were added. Please try again or contact an administrator.']);
        }

        return to_route('inbox')->with('success', count($request->file('files')).' document(s) added to the Inbox.')->with('upload_warnings', $warnings);
    }

    private function requestLimitBytes(): ?int
    {
        $limit = trim(ini_get('post_max_size'));
        $bytes = (float) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $bytes > 0 ? (int) $bytes : null;
    }
}
