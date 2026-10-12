<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\PaginationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_category_pages_and_global_search_paginate_ten_with_filters(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $this->actingAs(User::factory()->create());
        foreach (DocumentType::all() as $type) {
            $documents = Document::factory()->count(11)->create(['status' => 'filed', 'document_type_id' => $type->id, 'document_date' => '2026-09-01']);
            foreach ($documents as $document) {
                $document->references()->create(['kind' => 'po', 'value' => 'DEMO-PO-PARTIAL']);
            }
            $this->get('/archive/'.$type->slug.'?q=demopopartial&from=2026-09-01&sort=oldest')->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 10)->where('documents.total', 11)->where('documents.from', 1)->where('documents.to', 10)
                ->where('documents.next_page_url', fn ($url) => str_contains($url, 'q=demopopartial') && str_contains($url, 'sort=oldest') && str_contains($url, 'from=2026-09-01')));
            $this->get('/search?document_type_id='.$type->id.'&q=demopopartial&page=2')->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 1)->where('documents.from', 11)->where('documents.total', 11));
        }
    }

    public function test_inbox_and_users_paginate_ten_on_the_server(): void
    {
        Document::factory()->count(11)->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/inbox?category='.DocumentType::first()->slug)->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 10)->where('documents.total', 11)->where('documents.next_page_url', fn ($url) => str_contains($url, 'category=')));
        $this->get('/admin/users')->assertInertia(fn (Assert $page) => $page->has('users.data', 10)->where('users.per_page', 10));
    }

    public function test_demo_seed_is_additive_repeatable_and_preserves_edits_and_soft_deletion(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $existing = Document::factory()->create(['notes' => 'Existing record must stay unchanged']);
        $snapshot = $existing->fresh()->getAttributes();
        $this->seed(PaginationDemoSeeder::class);
        $this->assertDatabaseCount('documents', 751);
        $this->assertDatabaseCount('document_files', 0);
        foreach (DocumentType::where('slug', '!=', $existing->documentType->slug)->get() as $type) {
            $this->assertSame(125, Document::where('document_type_id', $type->id)->count());
            $this->assertSame(110, Document::where('document_type_id', $type->id)->where('status', 'filed')->count());
        }
        $demo = Document::where('notes', 'like', PaginationDemoSeeder::MARKER.'%')->firstOrFail();
        $demo->update(['notes' => 'User edited this demo record']);
        $demo->delete();
        $this->seed(PaginationDemoSeeder::class);
        $this->assertDatabaseCount('documents', 751);
        $this->assertSame($snapshot, $existing->fresh()->getAttributes());
        $this->assertSame('User edited this demo record', $demo->fresh()->notes);
        $this->assertSoftDeleted($demo);
        $this->assertSame(3, User::where('email', 'like', 'pagination-demo-%@example.test')->where('is_active', false)->count());
        $this->assertTrue(Document::where('status', 'inbox')->count() > 10);
        $this->actingAs($existing->uploader)->get('/documents/'.$demo->uuid.'/preview')->assertNotFound();
    }

    public function test_preview_and_download_require_authorization_and_handle_missing_bytes(): void
    {
        Storage::fake('documents');
        $document = Document::factory()->create(['status' => 'filed']);
        $file = DocumentFile::factory()->create(['document_id' => $document->id, 'path' => 'demo/fictional.png', 'original_name' => 'DEMO fictional.png', 'mime' => 'image/png']);
        Storage::disk('documents')->put($file->path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4WQAAAAASUVORK5CYII='));
        $this->get('/documents/'.$document->uuid.'/preview')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/documents/'.$document->uuid.'/preview')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/documents/'.$document->uuid.'/download')->assertDownload('DEMO fictional.png');
        Storage::disk('documents')->delete($file->path);
        $this->get('/documents/'.$document->uuid.'/preview')->assertNotFound()->assertDontSee($file->path);
        $this->get('/documents/'.$document->uuid.'/download')->assertNotFound();
    }

    public function test_user_modal_submission_preserves_validation_and_secure_password_creation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['name' => 'Demo New Staff', 'email' => 'demo-created@example.test', 'role' => 'staff', 'password' => 'FictionalTest!2026', 'password_confirmation' => 'FictionalTest!2026'];
        $this->from('/admin/users')->post('/admin/users', $payload)->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->post('/admin/users', $payload)->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', $payload['email'])->count());
        $this->actingAs($user)->post('/admin/users', $payload)->assertForbidden();
    }
}
