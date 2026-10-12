<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Party;
use App\Services\DocumentMetadataService;
use App\Services\DocumentQuery;
use App\Support\DocumentData;
use App\Support\DocumentMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class DocumentController extends Controller
{
    public function show(Document $document, DocumentQuery $query): Response
    {
        Gate::authorize('view', $document);
        $document->load(['documentType', 'uploader', 'party', 'references', 'reviewer']);
        $keys = $document->references->map(fn ($reference) => $reference->kind.'|'.$reference->normalized_value)->all();
        $related = $query->related($document)->paginate(10, ['*'], 'related_page')->withQueryString()->through(function (Document $related) use ($keys) {
            return [...DocumentData::summary($related), 'connections' => $related->references->filter(fn ($reference) => in_array($reference->kind.'|'.$reference->normalized_value, $keys, true))->map->only(['kind', 'value'])->values()->all()];
        });
        $file = $this->original($document);

        return Inertia::render('archive/DocumentDetails', [
            'document' => [
                'uuid' => $document->uuid, 'document_type_id' => $document->document_type_id,
                'category' => $document->documentType->name, 'category_slug' => $document->documentType->slug, 'notes' => $document->notes,
                'party_id' => $document->party_id, 'party' => $document->party?->only(['id', 'name', 'kind']),
                'document_date' => $document->document_date?->format('Y-m-d'), 'handled_on' => $document->handled_on?->format('Y-m-d'),
                'carrier' => $document->carrier, 'references' => $document->references->map->only(['kind', 'value'])->all(),
                'reviewed_by' => $document->reviewer?->name, 'reviewed_at' => $document->reviewed_at?->toIso8601String(),
                'uploaded_by' => $document->uploader->name, 'created_at' => $document->created_at->toIso8601String(),
                'status' => $document->status, 'file' => $file?->only(['original_name', 'mime', 'size']),
                'file_available' => $file && $this->available($file),
                'can_update' => Gate::allows('update', $document),
                'can_discard' => Gate::allows('discard', $document), 'can_file' => Gate::allows('file', $document),
            ],
            'documentTypes' => DocumentMetadata::types(),
            'parties' => Party::orderBy('name')->get(['id', 'name', 'kind']),
            'related' => $related,
        ]);
    }

    public function update(Request $request, Document $document, DocumentMetadataService $metadata): RedirectResponse
    {
        Gate::authorize('update', $document);
        $saved = $metadata->save($document, $request->all(), $request->user(), false);

        return back()->with('success', $saved->status === 'filed' ? 'Filed document metadata corrected.' : 'Draft saved. The document remains in the Inbox.');
    }

    public function file(Request $request, Document $document, DocumentMetadataService $metadata): RedirectResponse
    {
        Gate::authorize('file', $document);
        $saved = $metadata->save($document, $request->all(), $request->user(), true);

        return to_route('documents.show', $saved->uuid)->with('success', 'Document filed. It is now available in its category and Global Search.');
    }

    public function discard(Document $document): RedirectResponse
    {
        Gate::authorize('discard', $document);
        DB::transaction(function () use ($document) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('discard', $locked);
            $locked->update(['status' => 'discarded']);
        });

        return to_route('inbox')->with('success', 'Document discarded. Its original file has been retained.');
    }

    public function preview(Document $document): HttpResponse
    {
        Gate::authorize('view', $document);

        return $this->fileResponse($document, false);
    }

    public function download(Document $document): HttpResponse
    {
        Gate::authorize('download', $document);

        return $this->fileResponse($document, true);
    }

    private function original(Document $document): ?DocumentFile
    {
        return $document->files()->where('role', 'original')->first();
    }

    private function available(DocumentFile $file): bool
    {
        if ($file->disk !== 'documents') {
            return false;
        }
        try {
            return Storage::disk('documents')->exists($file->path);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function fileResponse(Document $document, bool $download): HttpResponse
    {
        $file = $this->original($document);
        abort_unless($file && $this->available($file), 404, 'This document file is unavailable. Contact an administrator.');
        abort_unless(in_array($file->mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415, 'This file type cannot be previewed.');
        try {
            // Local private disk; BinaryFileResponse supports range requests for PDF viewers.
            $path = Storage::disk('documents')->path($file->path);
            $headers = ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'", 'Referrer-Policy' => 'no-referrer'];
            $response = $download ? response()->download($path, $file->original_name, $headers) : response()->file($path, $headers);
            // BinaryFileResponse defaults to public, so explicitly override it after construction.
            $response->setPrivate();
            if (! $download) {
                $response->setContentDisposition('inline', $file->original_name);
            }

            return $response;
        } catch (Throwable $exception) {
            report($exception);
            abort(404, 'This document file is unavailable. Contact an administrator.');
        }
    }
}
