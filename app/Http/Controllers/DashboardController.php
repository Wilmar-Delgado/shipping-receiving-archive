<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentQuery;
use App\Support\DocumentData;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DocumentQuery $query): Response
    {
        Gate::authorize('viewAny', Document::class);
        $counts = Document::join('document_types', 'document_types.id', '=', 'documents.document_type_id')
            ->whereIn('documents.status', ['inbox', 'filed'])->selectRaw('documents.status, document_types.section, COUNT(*) AS total')->groupBy('documents.status', 'document_types.section')->get();
        $filed = $counts->where('status', 'filed');

        return Inertia::render('archive/Dashboard', [
            'stats' => ['filed' => $filed->sum('total'), 'inbox' => $counts->where('status', 'inbox')->sum('total'), 'receiving' => $filed->where('section', 'receiving')->sum('total'), 'shipping' => $filed->where('section', 'shipping')->sum('total'), 'routers' => $filed->where('section', 'routers')->sum('total')],
            'recentDocuments' => $query->filed()->latest('reviewed_at')->latest('created_at')->latest('id')->limit(5)->get()->map(DocumentData::summary(...)),
        ]);
    }
}
