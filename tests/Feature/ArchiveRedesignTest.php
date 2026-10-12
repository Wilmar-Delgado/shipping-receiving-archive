<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_statistics_and_recent_records_use_scoped_database_values(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $user = User::factory()->create();
        $types = DocumentType::all()->keyBy('slug');
        foreach (['vendor-packing-slips', 'material-test-reports', 'waybills', 'job-routers'] as $slug) {
            Document::factory()->create(['status' => 'filed', 'document_type_id' => $types[$slug]->id, 'uploaded_by' => $user->id]);
        }
        Document::factory()->create(['status' => 'inbox']);
        Document::factory()->create(['status' => 'discarded']);
        Document::factory()->create(['status' => 'filed'])->delete();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('archive/Dashboard')->where('stats.filed', 4)->where('stats.inbox', 1)
            ->where('stats.receiving', 2)->where('stats.shipping', 1)->where('stats.routers', 1)
            ->has('recentDocuments', 4)->where('inboxCount', 1)->missing('recentDocuments.0.file.path'));
    }

    public function test_dashboard_returns_five_recent_filed_records_with_stable_order(): void
    {
        $user = User::factory()->create();
        $documents = Document::factory()->count(7)->create(['status' => 'filed', 'created_at' => '2026-09-15 12:00:00']);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('recentDocuments', 5)->where('recentDocuments.0.uuid', $documents->last()->uuid));
    }

    public function test_global_category_filter_is_validated_and_retained_in_pagination(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $type = DocumentType::where('slug', 'waybills')->firstOrFail();
        Document::factory()->count(21)->create(['status' => 'filed', 'document_type_id' => $type->id]);
        Document::factory()->create(['status' => 'filed']);
        Document::factory()->create(['status' => 'inbox', 'document_type_id' => $type->id]);
        $this->actingAs(User::factory()->create())->get('/search?document_type_id='.$type->id)->assertInertia(fn (Assert $page) => $page
            ->where('documents.total', 21)->where('filters.document_type_id', (string) $type->id)
            ->where('documents.links', fn ($links) => collect($links)->contains(fn ($link) => $link['url'] && str_contains($link['url'], 'document_type_id='.$type->id) && str_contains($link['url'], 'page=2'))));
        $this->get('/search?document_type_id=999999')->assertSessionHasErrors('document_type_id');
    }

    public function test_upload_category_preselection_uses_real_seeded_categories(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $type = DocumentType::where('slug', 'job-routers')->firstOrFail();
        $this->actingAs(User::factory()->create())->get('/inbox?category=job-routers&upload=1')->assertInertia(fn (Assert $page) => $page
            ->where('initialCategoryId', $type->id)->where('openUpload', true));
        $this->get('/inbox?category=unknown')->assertSessionHasErrors('category');
    }

    public function test_recent_dashboard_documents_follow_filing_time_before_upload_time(): void
    {
        $recentlyFiled = Document::factory()->create(['status' => 'filed', 'created_at' => '2026-01-01 12:00:00', 'reviewed_at' => '2026-10-12 12:00:00']);
        Document::factory()->create(['status' => 'filed', 'created_at' => '2026-10-01 12:00:00', 'reviewed_at' => '2026-10-01 12:00:00']);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('recentDocuments.0.uuid', $recentlyFiled->uuid));
    }
}
