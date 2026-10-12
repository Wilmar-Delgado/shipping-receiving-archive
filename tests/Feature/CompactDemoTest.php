<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompactDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_default_seed_is_compact_and_all_fixture_bytes_are_valid_and_private(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('documents', 54);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('parties', 4);
        $this->assertDatabaseCount('document_types', 6);
        $this->assertSame(6, Document::where('status', 'inbox')->count());
        foreach (DocumentType::all() as $type) {
            $this->assertSame(8, Document::where('document_type_id', $type->id)->where('status', 'filed')->count());
            $this->assertSame(1, Document::where('document_type_id', $type->id)->where('status', 'inbox')->count());
        }
        foreach (DocumentFile::all() as $file) {
            $bytes = Storage::disk('documents')->get($file->path);
            $this->assertSame($file->size, strlen($bytes));
            $this->assertSame($file->sha256, hash('sha256', $bytes));
            if ($file->mime === 'image/png') {
                $this->assertSame('image/png', getimagesizefromstring($bytes)['mime']);
            } else {
                $this->assertStringStartsWith('%PDF-1.4', $bytes);
                $this->assertStringContainsString('FICTIONAL DEMO DOCUMENT', $bytes);
                preg_match('/startxref\n(\d+)/', $bytes, $matches);
                $this->assertSame('xref', substr($bytes, (int) $matches[1], 4));
            }
        }
        $this->assertCount(18, Storage::disk('documents')->allFiles());
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('documents', 54);
        $this->assertCount(18, Storage::disk('documents')->allFiles());
    }

    public function test_compact_demo_login_search_filters_preview_and_download_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (['admin@example.test', 'staff@example.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'ArchiveDemo!2026'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
            $this->post('/logout');
        }
        $this->actingAs(User::where('role', 'staff')->firstOrFail());
        $type = DocumentType::where('slug', 'vendor-packing-slips')->firstOrFail();
        $this->get('/archive/'.$type->slug.'?q=demo-po-1001')->assertInertia(fn (Assert $page) => $page->where('documents.total', 3));
        $this->get('/search?document_type_id='.$type->id.'&sort=oldest')->assertInertia(fn (Assert $page) => $page->where('documents.total', 8)->has('documents.data', 8));
        $this->get('/search?page=2')->assertInertia(fn (Assert $page) => $page->where('documents.total', 48)->has('documents.data', 10)->where('documents.from', 11));
        $document = Document::where('document_type_id', $type->id)->where('status', 'filed')->whereHas('files', fn ($q) => $q->where('mime', 'application/pdf'))->firstOrFail();
        $this->get('/search?q=DEMO-vendor-packing-slips-1.pdf')->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.is_demo', true));
        $this->get('/archive/'.$type->slug.'?from='.$document->document_date->format('Y-m-d').'&to='.$document->document_date->format('Y-m-d'))->assertInertia(fn (Assert $page) => $page->where('documents.total', 1));
        $this->get('/documents/'.$document->uuid.'/preview')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/documents/'.$document->uuid.'/download')->assertDownload($document->files->first()->original_name);
        $image = DocumentFile::where('mime', 'image/png')->firstOrFail();
        $this->get('/documents/'.$image->document->uuid.'/preview')->assertOk()->assertHeader('Content-Type', 'image/png');
        $noFile = Document::whereDoesntHave('files')->firstOrFail();
        $this->get('/documents/'.$noFile->uuid.'/preview')->assertNotFound();
    }

    public function test_reseeding_preserves_edited_soft_deleted_demo_records_and_fixture_bytes(): void
    {
        $this->seed(DemoSeeder::class);
        $document = Document::whereHas('files')->firstOrFail();
        $file = $document->files->first();
        $hash = hash('sha256', Storage::disk('documents')->get($file->path));
        $document->update(['notes' => 'Edited demo metadata']);
        $document->delete();
        $this->seed(DemoSeeder::class);
        $this->assertSoftDeleted($document);
        $this->assertSame('Edited demo metadata', $document->fresh()->notes);
        $this->assertSame($hash, hash('sha256', Storage::disk('documents')->get($file->path)));
        $this->assertDatabaseCount('documents', 54);
    }

    public function test_seeding_refuses_to_overwrite_a_fixture_without_its_document(): void
    {
        $this->seed(DemoSeeder::class);
        $document = Document::whereHas('files')->firstOrFail();
        $path = $document->files->first()->path;
        $bytes = Storage::disk('documents')->get($path);
        $document->forceDelete();
        try {
            $this->seed(DemoSeeder::class);
            $this->fail('An orphaned fixture must stop seeding.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('fixture path already exists', $exception->getMessage());
        }
        $this->assertSame($bytes, Storage::disk('documents')->get($path));
        $this->assertDatabaseCount('documents', 53);
    }
}
