<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentReference;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use App\Services\DocumentMetadataService;
use App\Support\Normalization;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class DocumentFilingSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->seed(DocumentTypeSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function document(string $slug = 'vendor-packing-slips', array $attributes = []): Document
    {
        return Document::factory()->create([...['document_type_id' => DocumentType::where('slug', $slug)->firstOrFail()->id, 'party_id' => null, 'notes' => null], ...$attributes]);
    }

    private function metadata(Document $document, array $overrides = []): array
    {
        return [...[
            'document_type_id' => $document->document_type_id,
            'party_id' => Party::factory()->create(['kind' => 'vendor'])->id,
            'handled_on' => '2026-09-16', 'document_date' => '2026-09-14', 'notes' => 'Fictional metadata',
            'references' => [['kind' => 'po', 'value' => 'po - 45821']],
        ], ...$overrides];
    }

    public function test_drafts_can_be_incomplete_and_save_multiple_original_reference_formats(): void
    {
        $document = $this->document();
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => $document->document_type_id, 'notes' => 'Draft', 'references' => [['kind' => 'po', 'value' => 'po - 45821'], ['kind' => 'po', 'value' => 'PO/45822'], ['kind' => 'job', 'value' => 'Job 001']]])->assertSessionHasNoErrors();
        $document->refresh();
        $this->assertSame('inbox', $document->status);
        $this->assertNull($document->handled_on);
        $this->assertNull($document->reviewed_by);
        $this->assertSame(['PO45821', 'PO45822', 'JOB001'], $document->references->pluck('normalized_value')->all());
        $this->assertSame('po - 45821', $document->references->first()->value);
    }

    public function test_filing_records_reviewer_removes_inbox_and_preserves_original(): void
    {
        $this->post('/inbox', ['document_type_id' => DocumentType::where('slug', 'vendor-packing-slips')->firstOrFail()->id, 'files' => [UploadedFile::fake()->image('fictional.png')]])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();
        $file = $document->files()->firstOrFail();
        $checksum = hash('sha256', Storage::disk('documents')->get($file->path));
        $this->post('/documents/'.$document->uuid.'/file', $this->metadata($document))->assertRedirect('/documents/'.$document->uuid)->assertSessionHasNoErrors();
        $this->assertSame('filed', $document->fresh()->status);
        $this->assertSame(auth()->id(), $document->fresh()->reviewed_by);
        $this->assertNotNull($document->fresh()->reviewed_at);
        $this->assertSame($checksum, hash('sha256', Storage::disk('documents')->get($file->path)));
        $this->get('/inbox')->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
        $this->get('/archive/vendor-packing-slips')->assertInertia(fn (Assert $page) => $page->where('documents.total', 1));
    }

    public function test_packing_slip_filing_requires_vendor_po_and_configured_handled_date(): void
    {
        $document = $this->document();
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id])->assertSessionHasErrors(['party_id', 'handled_on', 'references']);
        $this->assertSame('inbox', $document->fresh()->status);
    }

    public function test_customer_packing_slips_require_customer_and_job_but_not_invoice_or_tracking(): void
    {
        $document = $this->document('customer-packing-slips');
        $customer = Party::factory()->create(['kind' => 'customer']);
        $this->post('/documents/'.$document->uuid.'/file', $this->metadata($document, ['party_id' => $customer->id, 'references' => [['kind' => 'job', 'value' => 'Job-72']]]))->assertSessionHasNoErrors();
        $this->assertSame('filed', $document->fresh()->status);
    }

    public function test_party_kind_is_validated_and_job_reference_is_required_for_customer_slips(): void
    {
        $document = $this->document('customer-packing-slips');
        $this->post('/documents/'.$document->uuid.'/file', $this->metadata($document))->assertSessionHasErrors(['party_id', 'references']);
        $this->assertSame('inbox', $document->fresh()->status);
    }

    public function test_mtr_can_be_filed_without_unknown_party_dates_or_references(): void
    {
        $document = $this->document('material-test-reports');
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id])->assertSessionHasNoErrors();
        $this->assertSame('filed', $document->fresh()->status);
        $this->assertNull($document->fresh()->handled_on);
    }

    public function test_waybill_requires_carrier_and_configured_pickup_date_but_not_tracking(): void
    {
        $document = $this->document('waybills');
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id])->assertSessionHasErrors(['carrier', 'handled_on']);
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id, 'carrier' => 'Fictional Courier', 'handled_on' => '2026-09-18'])->assertSessionHasNoErrors();
        $this->assertSame('filed', $document->fresh()->status);
    }

    public function test_routers_require_job_but_do_not_infer_or_require_handled_date(): void
    {
        $document = $this->document('job-routers');
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id])->assertSessionHasErrors('references');
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id, 'references' => [['kind' => 'job', 'value' => 'Job-32']]])->assertSessionHasNoErrors();
        $this->assertNull($document->fresh()->handled_on);
    }

    public function test_rma_allows_customer_and_obeys_the_configured_handled_flag(): void
    {
        $document = $this->document('return-merchandise-authorizations');
        $customer = Party::factory()->create(['kind' => 'customer']);
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id, 'party_id' => $customer->id])->assertSessionHasErrors('handled_on');
        $document->documentType->update(['requires_handled_on' => false]);
        $this->post('/documents/'.$document->uuid.'/file', ['document_type_id' => $document->document_type_id, 'party_id' => $customer->id])->assertSessionHasNoErrors();
    }

    public function test_duplicate_punctuation_variants_are_rejected_atomically(): void
    {
        $document = $this->document();
        $document->references()->create(['kind' => 'po', 'value' => 'Original-1']);
        $this->patch('/documents/'.$document->uuid, $this->metadata($document, ['notes' => 'Must not be saved', 'references' => [['kind' => 'po', 'value' => 'PO-45821'], ['kind' => 'po', 'value' => 'po 45821']]]))->assertSessionHasErrors('references.1.value');
        $this->assertNull($document->fresh()->notes);
        $this->assertSame('Original-1', $document->references()->first()->value);
    }

    public function test_partial_deliveries_can_share_po_and_different_kinds_can_share_value(): void
    {
        foreach ([$this->document(), $this->document()] as $document) {
            $this->post('/documents/'.$document->uuid.'/file', $this->metadata($document, ['references' => [['kind' => 'po', 'value' => 'PO-45821'], ['kind' => 'job', 'value' => 'PO45821']]]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('document_references', 4);
        $this->assertSame(2, Document::where('status', 'filed')->count());
    }

    public function test_metadata_validation_rejects_bad_categories_dates_refs_and_forbidden_fields(): void
    {
        $document = $this->document();
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => 999999, 'document_date' => '2026-02-30', 'handled_on' => 'yesterday', 'references' => [['kind' => 'heat', 'value' => '---']]])->assertSessionHasErrors(['document_type_id', 'document_date', 'handled_on', 'references.0.kind', 'references.0.value']);
        $this->patch('/documents/'.$document->uuid, ['document_type_id' => $document->document_type_id, 'status' => 'filed', 'reviewed_by' => 999999, 'uploaded_by' => 999999])->assertSessionHasNoErrors();
        $this->assertSame('inbox', $document->fresh()->status);
        $this->assertNull($document->fresh()->reviewed_by);
    }

    public function test_inline_party_creation_is_validated_and_reuses_normalized_names(): void
    {
        $document = $this->document();
        $data = $this->metadata($document, ['party_id' => null, 'new_party_name' => 'Fictional Atlas Steel', 'new_party_kind' => 'vendor']);
        $this->post('/documents/'.$document->uuid.'/file', $data)->assertSessionHasNoErrors();
        $party = $document->fresh()->party;
        $other = $this->document();
        $data['document_type_id'] = $other->document_type_id;
        $data['new_party_name'] = 'fictional atlas steel';
        $this->post('/documents/'.$other->uuid.'/file', $data)->assertSessionHasNoErrors();
        $this->assertSame($party->id, $other->fresh()->party_id);
        $invalid = $this->document();
        $this->patch('/documents/'.$invalid->uuid, ['new_party_name' => 'Wrong kind', 'new_party_kind' => 'customer'])->assertSessionHasErrors('new_party_kind');
        $this->assertDatabaseMissing('parties', ['name' => 'Wrong kind']);
    }

    public function test_filed_corrections_preserve_status_original_review_and_unchanged_reference_ids(): void
    {
        $document = $this->document();
        $this->post('/documents/'.$document->uuid.'/file', $this->metadata($document))->assertSessionHasNoErrors();
        $referenceId = $document->references()->first()->id;
        $reviewedAt = $document->fresh()->reviewed_at;
        $this->patch('/documents/'.$document->uuid, ['notes' => 'Corrected', 'references' => [['kind' => 'po', 'value' => 'PO45821']]])->assertSessionHasNoErrors();
        $this->assertSame('filed', $document->fresh()->status);
        $this->assertTrue($reviewedAt->equalTo($document->fresh()->reviewed_at));
        $this->assertSame($referenceId, $document->references()->first()->id);
        $this->post('/documents/'.$document->uuid.'/file', [])->assertForbidden();
        $this->patch('/documents/'.$document->uuid, ['references' => []])->assertSessionHasErrors('references');
    }

    public function test_draft_reference_removal_and_metadata_failure_roll_back_as_a_unit(): void
    {
        $document = $this->document();
        $document->references()->create(['kind' => 'po', 'value' => 'Keep-1']);
        DocumentReference::creating(fn () => throw new RuntimeException('Simulated reference failure.'));
        try {
            app(DocumentMetadataService::class)->save($document, ['notes' => 'Not committed', 'references' => [['kind' => 'po', 'value' => 'Replace-1']]], auth()->user(), false);
            $this->fail('Expected metadata failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated reference failure.', $exception->getMessage());
        }
        $this->assertNull($document->fresh()->notes);
        $this->assertSame('Keep-1', $document->references()->first()->value);
    }

    public function test_category_scope_filters_dates_sort_and_shareable_pagination(): void
    {
        $party = Party::factory()->create(['kind' => 'vendor']);
        $first = $this->document(attributes: ['status' => 'filed', 'party_id' => $party->id, 'document_date' => '2026-09-14', 'created_at' => '2026-09-20 12:00:00']);
        $first->references()->create(['kind' => 'po', 'value' => 'PO-45821']);
        $second = $this->document(attributes: ['status' => 'filed', 'party_id' => $party->id, 'document_date' => '2026-09-15', 'created_at' => '2026-09-21 12:00:00']);
        $second->references()->create(['kind' => 'po', 'value' => 'po 45821']);
        $this->document('customer-packing-slips', ['status' => 'filed']);
        $this->document();
        $this->document(attributes: ['status' => 'discarded']);
        $query = http_build_query(['q' => 'po45821', 'party_id' => $party->id, 'from' => '2026-09-14', 'to' => '2026-09-15', 'date_field' => 'document_date', 'sort' => 'oldest']);
        $this->get('/archive/vendor-packing-slips?'.$query)->assertInertia(fn (Assert $page) => $page->component('archive/Category')->where('documents.total', 2)->where('documents.data.0.uuid', $first->uuid)->where('filters.sort', 'oldest'));
        $this->get('/archive/vendor-packing-slips?from=2026-09-15&to=2026-09-15')->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.uuid', $second->uuid));
        Document::factory()->count(21)->create(['status' => 'filed', 'party_id' => $party->id, 'document_type_id' => $first->document_type_id]);
        $this->get('/archive/vendor-packing-slips?party_id='.$party->id.'&sort=oldest')->assertInertia(fn (Assert $page) => $page->has('documents.data', 10)->where('documents.total', 23)->where('documents.links', fn ($links) => collect($links)->contains(fn ($link) => $link['url'] && str_contains($link['url'], 'party_id='.$party->id) && str_contains($link['url'], 'sort=oldest') && str_contains($link['url'], 'page=2'))));
    }

    public function test_upload_and_handled_date_ranges_have_inclusive_end_dates(): void
    {
        $document = $this->document(attributes: ['status' => 'filed', 'handled_on' => '2026-09-16', 'created_at' => '2026-09-20 23:59:59']);
        $this->get('/search?from=2026-09-20&to=2026-09-20&date_field=created_at')->assertInertia(fn (Assert $page) => $page->where('documents.total', 1));
        $this->get('/search?from=2026-09-16&to=2026-09-16&date_field=handled_on')->assertInertia(fn (Assert $page) => $page->where('documents.total', 1));
        $this->get('/search?to=2026-09-15&date_field=handled_on')->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
    }

    public function test_global_search_matches_all_reference_kinds_case_and_punctuation(): void
    {
        foreach (['po', 'job', 'invoice', 'tracking'] as $kind) {
            $document = $this->document(attributes: ['status' => 'filed']);
            $document->references()->create(['kind' => $kind, 'value' => strtoupper($kind).'-45 821']);
            foreach ([strtoupper($kind).'-45821', $kind.'45821', $kind.' 45821'] as $query) {
                $this->get('/search?q='.urlencode($query))->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.uuid', $document->uuid));
            }
        }
        $this->assertSame('PO45821', Normalization::reference('po - 45/821'));
    }

    public function test_global_search_party_filename_literal_wildcards_and_status_isolation(): void
    {
        $party = Party::factory()->create(['name' => 'Fictional Atlas Steel']);
        $document = $this->document(attributes: ['status' => 'filed', 'party_id' => $party->id]);
        DocumentFile::factory()->create(['document_id' => $document->id, 'original_name' => 'Fictional_100%.PDF']);
        $this->document(attributes: ['party_id' => $party->id]);
        $this->document(attributes: ['status' => 'discarded', 'party_id' => $party->id]);
        $this->document(attributes: ['status' => 'filed', 'party_id' => $party->id])->delete();
        foreach (['atlas', 'fictional_100%', 'FICTIONAL_100%.pdf'] as $query) {
            $this->get('/search?q='.urlencode($query))->assertInertia(fn (Assert $page) => $page->component('archive/Search')->where('documents.total', 1)->where('documents.data.0.uuid', $document->uuid)->missing('documents.data.0.file.path'));
        }
        $this->get('/search?q=unknown')->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
        $this->get('/archive/vendor-packing-slips?q=---')->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
    }

    public function test_related_documents_match_kind_and_value_and_are_deduplicated(): void
    {
        $current = $this->document();
        $current->references()->createMany([['kind' => 'po', 'value' => 'PO-123'], ['kind' => 'job', 'value' => 'Job-456']]);
        $related = $this->document('material-test-reports', ['status' => 'filed']);
        $related->references()->createMany([['kind' => 'po', 'value' => 'po 123'], ['kind' => 'job', 'value' => 'Job456']]);
        $wrongKind = $this->document(attributes: ['status' => 'filed']);
        $wrongKind->references()->create(['kind' => 'invoice', 'value' => 'PO123']);
        $inbox = $this->document();
        $inbox->references()->create(['kind' => 'po', 'value' => 'PO123']);
        $discarded = $this->document(attributes: ['status' => 'discarded']);
        $discarded->references()->create(['kind' => 'po', 'value' => 'PO123']);
        $this->get('/documents/'.$current->uuid)->assertInertia(fn (Assert $page) => $page->where('related.total', 1)->where('related.data.0.uuid', $related->uuid)->has('related.data.0.connections', 2)->missing('related.data.0.file.path'));
    }

    public function test_guest_inactive_and_discarded_metadata_access_is_denied(): void
    {
        $document = $this->document();
        auth()->logout();
        $this->post('/documents/'.$document->uuid.'/file', [])->assertRedirect('/login');
        $this->patch('/documents/'.$document->uuid, [])->assertRedirect('/login');
        $this->get('/search')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_active' => false]))->post('/documents/'.$document->uuid.'/file', [])->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $document->update(['status' => 'discarded']);
        $this->post('/documents/'.$document->uuid.'/file', [])->assertForbidden();
        $this->patch('/documents/'.$document->uuid, [])->assertForbidden();
    }

    public function test_invalid_filter_fields_and_reversed_dates_are_rejected(): void
    {
        $this->get('/search?from=2026-09-30&to=2026-09-01&sort=unsafe&date_field=notes')->assertSessionHasErrors(['to', 'sort', 'date_field']);
        $this->get('/search?party_id=999999')->assertSessionHasErrors('party_id');
    }

    public function test_legacy_normalization_backfill_preserves_values_and_allows_partial_deliveries(): void
    {
        $documents = [$this->document(), $this->document()];
        foreach ($documents as $document) {
            DB::table('document_references')->insert(['document_id' => $document->id, 'kind' => 'po', 'value' => 'po - 45821', 'normalized_value' => 'PO - 45821', 'created_at' => now(), 'updated_at' => now()]);
        }
        $migration = require database_path('migrations/2026_10_12_000002_normalize_reference_values.php');
        $migration->up();
        $this->assertSame(2, DocumentReference::where('normalized_value', 'PO45821')->count());
        $this->assertSame('po - 45821', DocumentReference::first()->value);
    }

    public function test_conflicting_legacy_references_stop_the_backfill_without_changes(): void
    {
        $document = $this->document();
        foreach (['PO-1', 'PO1'] as $value) {
            DB::table('document_references')->insert(['document_id' => $document->id, 'kind' => 'po', 'value' => $value, 'normalized_value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
        $migration = require database_path('migrations/2026_10_12_000002_normalize_reference_values.php');
        try {
            $migration->up();
            $this->fail('Expected conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No references have been changed', $exception->getMessage());
        }
        $this->assertSame(['PO-1', 'PO1'], $document->references()->orderBy('id')->pluck('normalized_value')->all());
    }

    public function test_metadata_and_search_tests_use_only_the_isolated_database(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
}
