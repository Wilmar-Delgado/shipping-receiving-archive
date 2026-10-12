<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DocumentInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->seed(DocumentTypeSeeder::class);
    }

    private function pdf(string $name = 'fictional.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    private function invalidPdf(): UploadedFile
    {
        return new UploadedFile(__DIR__.'/../Fixtures/not-a-pdf.pdf', 'disguised.pdf', 'application/pdf', null, true);
    }

    private function upload(array $files, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->post('/inbox', ['document_type_id' => DocumentType::first()->id, 'files' => $files]);
    }

    public function test_authenticated_staff_and_admin_can_upload_private_originals(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $file = $this->pdf();
            $checksum = hash_file('sha256', $file->getRealPath());
            $this->upload([$file], $user)->assertRedirect('/inbox')->assertSessionHasNoErrors();
            $document = Document::latest('id')->firstOrFail();
            $original = $document->files()->firstOrFail();
            $this->assertSame('inbox', $document->status);
            $this->assertSame($user->id, $document->uploaded_by);
            $this->assertNull($document->handled_on);
            $this->assertSame('documents', $original->disk);
            $this->assertSame('fictional.pdf', $original->original_name);
            $this->assertSame('application/pdf', $original->mime);
            $this->assertSame($checksum, $original->sha256);
            $this->assertSame($checksum, hash('sha256', Storage::disk('documents')->get($original->path)));
            // Windows does not report POSIX modes; the disk is outside the web root.
            $this->assertSame('private', config('filesystems.disks.documents.visibility'));
            if (DIRECTORY_SEPARATOR !== '\\') {
                $this->assertSame('private', Storage::disk('documents')->getVisibility($original->path));
            }
            $this->assertNotSame($original->original_name, basename($original->path));
        }
    }

    public function test_guests_and_inactive_users_cannot_upload(): void
    {
        $payload = ['document_type_id' => DocumentType::first()->id, 'files' => [$this->pdf()]];
        $this->post('/inbox', $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_active' => false]))->post('/inbox', $payload)->assertRedirect('/login');
        $this->assertDatabaseCount('documents', 0);
        $this->assertEmpty(Storage::disk('documents')->allFiles());
    }

    public function test_multiple_pdfs_and_images_create_separate_documents(): void
    {
        $this->upload([$this->pdf(), UploadedFile::fake()->image('fictional.jpg'), UploadedFile::fake()->image('fictional.png')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('documents', 3);
        $this->assertDatabaseCount('document_files', 3);
        $this->assertCount(3, Storage::disk('documents')->allFiles());
    }

    public function test_invalid_file_types_and_disguised_content_are_rejected(): void
    {
        foreach ([UploadedFile::fake()->createWithContent('wrong.txt', 'example text'), $this->invalidPdf(), $this->pdf('original.php')] as $file) {
            $this->upload([$file])->assertSessionHasErrors('files.0');
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertEmpty(Storage::disk('documents')->allFiles());
    }

    public function test_size_category_batch_and_empty_validation(): void
    {
        config(['archive.upload_max_kb' => 1, 'archive.upload_max_files' => 1]);
        $this->upload([UploadedFile::fake()->create('large.pdf', 2, 'application/pdf')])->assertSessionHasErrors('files.0');
        $this->upload([$this->pdf(), $this->pdf()])->assertSessionHasErrors('files');
        $this->upload([])->assertSessionHasErrors('files');
        $this->upload([UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf')])->assertSessionHasErrors('files.0');
        $this->post('/inbox', ['document_type_id' => 999999, 'files' => [$this->pdf()]])->assertSessionHasErrors('document_type_id');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_php_upload_errors_are_rejected(): void
    {
        $file = new UploadedFile(__FILE__, 'failed.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);
        $this->upload([$file])->assertSessionHasErrors('files.0');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_batch_with_one_invalid_file_stores_nothing(): void
    {
        $this->upload([$this->pdf(), $this->invalidPdf()])->assertSessionHasErrors('files.1');
        $this->assertDatabaseCount('documents', 0);
        $this->assertEmpty(Storage::disk('documents')->allFiles());
    }

    public function test_inbox_excludes_filed_discarded_and_deleted_documents_and_is_paginated(): void
    {
        $user = User::factory()->create();
        $category = DocumentType::first()->id;
        Document::factory()->count(21)->create(['uploaded_by' => $user->id, 'document_type_id' => $category]);
        Document::factory()->create(['status' => 'filed', 'document_type_id' => $category]);
        Document::factory()->create(['status' => 'discarded', 'document_type_id' => $category]);
        Document::factory()->create(['document_type_id' => $category])->delete();
        $this->actingAs($user)->get('/inbox')->assertInertia(fn (Assert $page) => $page->component('archive/Inbox')->has('documents.data', 10)->where('documents.total', 21)->has('documentTypes', 6));
        $this->get('/inbox?page=2')->assertInertia(fn (Assert $page) => $page->has('documents.data', 10));
        $this->get('/inbox?page=3')->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
    }

    public function test_empty_inbox_is_rendered(): void
    {
        $this->actingAs(User::factory()->create())->get('/inbox')->assertInertia(fn (Assert $page) => $page->has('documents.data', 0)->where('documents.total', 0));
    }

    public function test_authorized_preview_download_details_do_not_expose_storage_paths(): void
    {
        $this->upload([$this->pdf()]);
        $document = Document::firstOrFail();
        $original = $document->files()->firstOrFail();
        $this->actingAs(User::factory()->create())->get('/documents/'.$document->uuid)->assertInertia(fn (Assert $page) => $page->component('archive/DocumentDetails')->where('document.file_available', true)->where('document.file.original_name', 'fictional.pdf')->missing('document.file.path')->missing('document.file.disk'));
        $preview = $this->get('/documents/'.$document->uuid.'/preview')->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('inline', $preview->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $preview->headers->get('cache-control'));
        $this->assertStringContainsString('private', $preview->headers->get('cache-control'));
        $download = $this->get('/documents/'.$document->uuid.'/download')->assertDownload('fictional.pdf');
        $this->assertStringContainsString('private', $download->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', $download->headers->get('cache-control'));
        $this->get('/inbox')->assertDontSee($original->path)->assertInertia(fn (Assert $page) => $page->missing('documents.data.0.file.path')->missing('documents.data.0.file.disk'));
        $this->get('/storage/'.$original->path)->assertNotFound();
    }

    public function test_guests_cannot_access_any_document_route(): void
    {
        $document = Document::factory()->create();
        foreach (['', '/preview', '/download'] as $suffix) {
            $this->get('/documents/'.$document->uuid.$suffix)->assertRedirect('/login');
        }
        $this->patch('/documents/'.$document->uuid, [])->assertRedirect('/login');
        $this->post('/documents/'.$document->uuid.'/discard')->assertRedirect('/login');
    }

    public function test_image_preview_and_missing_file_are_handled(): void
    {
        $this->upload([UploadedFile::fake()->image('fictional.png')]);
        $document = Document::firstOrFail();
        $this->get('/documents/'.$document->uuid.'/preview')->assertOk()->assertHeader('content-type', 'image/png');
        $path = $document->files()->first()->path;
        Storage::disk('documents')->delete($path);
        $this->get('/documents/'.$document->uuid)->assertInertia(fn (Assert $page) => $page->where('document.file_available', false));
        $this->get('/documents/'.$document->uuid.'/preview')->assertNotFound()->assertSee('Document unavailable')->assertDontSee(storage_path())->assertDontSee($path);
        $this->get('/documents/'.$document->uuid.'/download')->assertNotFound();
    }

    public function test_category_and_notes_can_be_corrected_without_filing(): void
    {
        $this->upload([$this->pdf()]);
        $document = Document::firstOrFail();
        $category = DocumentType::where('slug', 'waybills')->firstOrFail();
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => $category->id, 'notes' => 'Fictional receiving note', 'status' => 'filed', 'uploaded_by' => 999])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'document_type_id' => $category->id, 'notes' => 'Fictional receiving note', 'status' => 'inbox', 'uploaded_by' => $document->uploaded_by]);
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => 999, 'notes' => str_repeat('x', 10001)])->assertSessionHasErrors(['document_type_id', 'notes']);
    }

    public function test_discard_removes_inbox_and_access_but_retains_file(): void
    {
        $this->upload([$this->pdf()]);
        $document = Document::firstOrFail();
        $file = $document->files()->firstOrFail();
        $this->post('/documents/'.$document->uuid.'/discard')->assertRedirect('/inbox');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'discarded']);
        Storage::disk('documents')->assertExists($file->path);
        $this->get('/inbox')->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
        foreach (['', '/preview', '/download'] as $suffix) {
            $this->get('/documents/'.$document->uuid.$suffix)->assertForbidden();
        }
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => $document->document_type_id])->assertForbidden();
        $this->post('/documents/'.$document->uuid.'/discard')->assertForbidden();
    }

    public function test_filed_documents_allow_metadata_corrections_but_cannot_be_discarded(): void
    {
        $document = Document::factory()->create(['status' => 'filed', 'party_id' => null, 'document_type_id' => DocumentType::where('slug', 'job-routers')->firstOrFail()->id]);
        $document->references()->create(['kind' => 'job', 'value' => 'Fictional-Job-1']);
        $this->actingAs(User::factory()->create())->patch('/documents/'.$document->uuid, ['notes' => 'Corrected notes'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'filed', 'notes' => 'Corrected notes']);
        $this->post('/documents/'.$document->uuid.'/discard')->assertForbidden();
    }

    public function test_duplicates_are_warned_and_not_rejected_even_within_one_batch(): void
    {
        $this->upload([$this->pdf('first.pdf'), $this->pdf('second.pdf')])->assertSessionHas('upload_warnings', fn ($warnings) => count($warnings) === 1 && str_contains($warnings[0], 'second.pdf'));
        $this->upload([$this->pdf('third.pdf')])->assertSessionHas('upload_warnings', fn ($warnings) => count($warnings) === 1);
        $this->assertDatabaseCount('documents', 3);
        $this->assertCount(3, Storage::disk('documents')->allFiles());
    }

    public function test_storage_failure_rolls_back_the_whole_batch_and_cleans_files(): void
    {
        $disk = Storage::disk('documents');
        $proxy = Mockery::mock($disk);
        $writes = 0;
        $proxy->shouldReceive('putFileAs')->andReturnUsing(function (...$arguments) use ($disk, &$writes) {
            $path = $disk->putFileAs(...$arguments);
            if (++$writes === 2) {
                throw new RuntimeException('Simulated partial write failure.');
            }

            return $path;
        });
        $proxy->shouldReceive('delete')->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::shouldReceive('disk')->with('documents')->andReturn($proxy);
        $this->upload([$this->pdf(), $this->pdf()])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_files', 0);
        $this->assertEmpty($disk->allFiles());
    }

    public function test_database_failure_cleans_a_file_already_written(): void
    {
        DocumentFile::creating(fn () => throw new RuntimeException('Simulated metadata failure.'));
        $this->upload([$this->pdf()])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_files', 0);
        $this->assertEmpty(Storage::disk('documents')->allFiles());
    }

    public function test_testing_uses_an_isolated_database_and_keeps_mysql_guard(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->expectException(RuntimeException::class);
        // This object has no PDO; no connection/query is made to another database.
        Event::dispatch(new ConnectionEstablished(new MySqlConnection(null, 'unrelated_database', '', ['driver' => 'mysql'])));
    }

    public function test_oversized_requests_receive_a_clear_error_without_debug_details(): void
    {
        $this->call('POST', '/inbox', [], [], [], ['CONTENT_LENGTH' => PHP_INT_MAX])->assertStatus(413)->assertSee('Select fewer files')->assertDontSee('Stack trace');
    }

    public function test_inaccessible_storage_and_unsupported_disk_do_not_expose_paths(): void
    {
        $this->upload([$this->pdf()]);
        $document = Document::firstOrFail();
        $file = $document->files()->firstOrFail();
        $file->update(['disk' => 'public']);
        $this->get('/documents/'.$document->uuid.'/preview')->assertNotFound()->assertDontSee($file->path);
        $file->update(['disk' => 'documents']);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->andThrow(new RuntimeException('Simulated inaccessible storage.'));
        Storage::shouldReceive('disk')->with('documents')->andReturn($disk);
        $this->get('/documents/'.$document->uuid)->assertInertia(fn (Assert $page) => $page->where('document.file_available', false));
        $this->get('/documents/'.$document->uuid.'/download')->assertNotFound()->assertSee('Document unavailable')->assertDontSee($file->path);
    }

    public function test_unicode_original_names_are_preserved_and_served_safely(): void
    {
        $name = 'fictional métal 例.pdf';
        $this->upload([$this->pdf($name)])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();
        $this->assertSame($name, $document->files()->firstOrFail()->original_name);
        $download = $this->get('/documents/'.$document->uuid.'/download')->assertDownload();
        $this->assertStringContainsString($name, rawurldecode($download->headers->get('content-disposition')));
        $this->get('/documents/'.$document->uuid.'/preview')->assertOk();
    }
}
