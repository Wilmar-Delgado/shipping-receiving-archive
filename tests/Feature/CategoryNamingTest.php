<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Support\ArchiveCategories;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryNamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_names_labels_redirects_and_upload_preselection_are_consistent(): void
    {
        Storage::fake('documents');
        $this->seed(DemoSeeder::class);
        $user = User::where('role', 'staff')->firstOrFail();
        $rma = DocumentType::where('slug', ArchiveCategories::RMA_SLUG)->firstOrFail();
        $this->actingAs($user)->get('/archive/'.ArchiveCategories::RMA_SLUG)->assertInertia(fn (Assert $page) => $page
            ->where('category.name', 'Return Merchandise Authorizations (RMAs)')->where('category.label', 'RMAs')
            ->where('category.slug', ArchiveCategories::RMA_SLUG)->where('documents.total', 8)
            ->where('category.metadata.partyKinds', ['vendor', 'customer'])
            ->where('archiveCategories.'.ArchiveCategories::RMA_SLUG.'.href', '/archive/'.ArchiveCategories::RMA_SLUG)
            ->where('archiveCategories.material-test-reports.label', 'MTRs'));
        $this->get('/archive/material-test-reports')->assertInertia(fn (Assert $page) => $page
            ->where('category.name', 'Material Test Reports (MTRs)')->where('category.label', 'MTRs'));
        $this->get('/archive/rmas?q=DEMO-PO-1001&sort=oldest&page=2')->assertStatus(301)
            ->assertRedirect('/archive/'.ArchiveCategories::RMA_SLUG.'?page=2&q=DEMO-PO-1001&sort=oldest');
        $this->get('/archive/return-merchandise-authorization?q=DEMO-PO-1001&sort=oldest&page=2')->assertStatus(301)
            ->assertRedirect('/archive/'.ArchiveCategories::RMA_SLUG.'?page=2&q=DEMO-PO-1001&sort=oldest');
        foreach (['rmas', 'return-merchandise-authorization', ArchiveCategories::RMA_SLUG] as $slug) {
            $this->get('/inbox?category='.$slug.'&upload=1')->assertInertia(fn (Assert $page) => $page->where('initialCategoryId', $rma->id)->where('openUpload', true));
        }
        $this->get('/search?document_type_id='.$rma->id.'&q=DEMO-PO-1001')->assertInertia(fn (Assert $page) => $page->where('documents.total', 3));
        $document = $rma->documents()->whereHas('files')->where('status', 'filed')->firstOrFail();
        $this->get('/documents/'.$document->uuid)->assertInertia(fn (Assert $page) => $page
            ->where('document.category_slug', ArchiveCategories::RMA_SLUG)->where('document.file_available', true));
        $this->get('/documents/'.$document->uuid.'/preview')->assertOk();
        $this->get('/documents/'.$document->uuid.'/download')->assertDownload();
    }

    public function test_migration_and_reseeding_preserve_legacy_type_id_documents_and_files(): void
    {
        Storage::fake('documents');
        $this->seed(DemoSeeder::class);
        $rma = DocumentType::where('slug', ArchiveCategories::RMA_SLUG)->firstOrFail();
        $rma->update(['slug' => 'return-merchandise-authorization', 'name' => 'Return Merchandise Authorizations (RMAs)']);
        $documents = $rma->documents()->get()->mapWithKeys(fn ($document) => [$document->id => $document->getAttributes()])->all();
        $files = collect(Storage::disk('documents')->allFiles())->mapWithKeys(fn ($path) => [$path => hash('sha256', Storage::disk('documents')->get($path))])->all();
        $migration = require database_path('migrations/2026_10_12_000004_pluralize_rma_category_slug.php');
        $migration->up();
        $migration->up();
        $this->seed(DocumentTypeSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertSame(ArchiveCategories::RMA_SLUG, $rma->fresh()->slug);
        $this->assertSame('Return Merchandise Authorizations (RMAs)', $rma->fresh()->name);
        $this->assertDatabaseCount('document_types', 6);
        $this->assertDatabaseCount('documents', 54);
        foreach ($documents as $id => $attributes) {
            $this->assertSame($attributes, Document::findOrFail($id)->getAttributes());
        }
        foreach ($files as $path => $hash) {
            $this->assertSame($hash, hash('sha256', Storage::disk('documents')->get($path)));
        }
        $this->assertSame(9, Document::where('document_type_id', $rma->id)->count());
        $this->assertSame('rmas', ArchiveCategories::demoIdentity(ArchiveCategories::RMA_SLUG));
    }

    public function test_duplicate_rma_types_stop_migration_and_seeding_without_merging_records(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $legacy = DocumentType::factory()->create(['slug' => 'rmas', 'name' => 'Legacy RMAs']);
        $snapshot = DocumentType::all()->map->getAttributes()->all();
        foreach (['migration', 'seeder'] as $operation) {
            try {
                if ($operation === 'migration') {
                    (require database_path('migrations/2026_10_12_000004_pluralize_rma_category_slug.php'))->up();
                } else {
                    $this->seed(DocumentTypeSeeder::class);
                }
                $this->fail('Duplicate categories must stop without an automatic merge.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Multiple RMA slugs exist', $exception->getMessage());
            }
            $this->assertSame($snapshot, DocumentType::all()->map->getAttributes()->all());
        }
        $this->assertSame('rmas', $legacy->fresh()->slug);
    }
}
