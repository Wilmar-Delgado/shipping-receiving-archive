<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_preview_head_and_range_responses_remain_private_and_authorized(): void
    {
        Storage::fake('documents');
        $this->seed(DemoSeeder::class);
        $document = Document::whereHas('files', fn ($query) => $query->where('mime', 'application/pdf'))->firstOrFail();
        $file = $document->files->first();
        $size = $file->size;
        $url = '/documents/'.$document->uuid.'/preview';
        $this->head($url)->assertRedirect('/login');
        $this->actingAs(User::where('role', 'staff')->firstOrFail());
        $this->head($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Length', (string) $size);
        $response = $this->withHeader('Range', 'bytes=0-31')->get($url)->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-31/'.$size)->assertHeader('Content-Length', '32')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'self'");
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_preview_preflight_handles_missing_originals_and_discarded_documents(): void
    {
        Storage::fake('documents');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('role', 'staff')->firstOrFail());
        $document = Document::where('status', 'inbox')->whereHas('files')->firstOrFail();
        $file = $document->files->first();
        $this->head('/documents/'.$document->uuid.'/preview')->assertOk();
        Storage::disk('documents')->delete($file->path);
        $this->head('/documents/'.$document->uuid.'/preview')->assertNotFound();
        $document->update(['status' => 'discarded']);
        $this->head('/documents/'.$document->uuid.'/preview')->assertForbidden();
        $empty = Document::whereDoesntHave('files')->firstOrFail();
        $this->head('/documents/'.$empty->uuid.'/preview')->assertNotFound();
    }
}
