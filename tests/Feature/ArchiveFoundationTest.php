<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArchiveFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_manage_users(): void
    {
        $staff = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->patch('/admin/users/'.$target->id, ['is_active' => false])->assertForbidden();
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_can_create_and_change_account_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->post('/admin/users', ['name' => 'Fictional Staff', 'email' => 'new@example.test', 'role' => 'staff', 'password' => 'StrongExample!123', 'password_confirmation' => 'StrongExample!123'])->assertRedirect();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongExample!123', $user->password));
        $this->patch('/admin/users/'.$user->id, ['is_active' => false])->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->patch('/admin/users/'.$user->id, ['is_active' => true])->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $this->patch('/admin/users/'.$admin->id, ['is_active' => false])->assertForbidden();
    }

    public function test_user_creation_validates_role_email_and_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/users', ['name' => 'Sample', 'email' => $admin->email, 'role' => 'owner', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['email', 'role', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_inactive_users_cannot_login_or_keep_a_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_categories_are_separate_and_only_available_to_authenticated_users(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $this->assertDatabaseCount('document_types', 6);
        $this->get('/archive/vendor-packing-slips')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (DocumentType::all() as $type) {
            $this->get('/archive/'.$type->slug)->assertOk();
        }
        $this->get('/archive/unknown')->assertNotFound();
        $this->get('/inbox')->assertOk();
        $this->get('/search')->assertOk();
    }

    public function test_relationships_dates_and_normalization(): void
    {
        $document = Document::factory()->create(['document_date' => '2026-09-14', 'handled_on' => null]);
        $reference = $document->references()->create(['kind' => 'po', 'value' => '  po-123  ']);
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);
        $this->assertSame('PO-123', $reference->normalized_value);
        $this->assertSame('2026-09-14', $document->document_date->format('Y-m-d'));
        $this->assertNull($document->handled_on);
        $this->assertNotEmpty($document->uuid);
        $this->assertTrue($reference->document->is($document));
        $this->assertTrue($file->document->is($document));
        $this->assertCount(1, $document->uploader->uploadedDocuments);
        $this->assertNotNull($document->documentType);
        $party = Party::factory()->create(['name' => '  Fictional   Steel ']);
        $this->assertSame('FICTIONAL STEEL', $party->normalized_name);
        $this->assertTrue(Gate::forUser($document->uploader)->allows('view', $document));
        $this->assertFalse(Gate::forUser(User::factory()->create(['is_active' => false]))->allows('view', $document));
        $document->delete();
        $this->assertSoftDeleted($document);
        $this->assertDatabaseHas('document_references', ['id' => $reference->id]);
    }

    public function test_duplicate_normalized_references_are_rejected(): void
    {
        $document = Document::factory()->create();
        $document->references()->create(['kind' => 'po', 'value' => 'PO-123']);
        $this->expectException(QueryException::class);
        $document->references()->create(['kind' => 'po', 'value' => ' po-123 ']);
    }

    public function test_references_can_repeat_across_documents_and_kinds(): void
    {
        $first = Document::factory()->create();
        $second = Document::factory()->create();
        foreach ([$first, $second] as $document) {
            $document->references()->create(['kind' => 'po', 'value' => 'DEMO-100']);
        }
        $first->references()->create(['kind' => 'job', 'value' => 'DEMO-100']);
        $this->assertDatabaseCount('document_references', 3);
    }

    public function test_private_storage_has_no_public_serving_or_url(): void
    {
        $disk = config('filesystems.disks.documents');
        $this->assertSame('private', $disk['visibility']);
        $this->assertFalse($disk['serve']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertSame(storage_path('app/private/documents'), $disk['root']);
        $this->get('/storage/documents/sample.pdf')->assertNotFound();
    }

    public function test_demo_seeder_is_repeatable_and_fictional(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('parties', 2);
        $this->assertDatabaseCount('documents', 6);
        $this->assertDatabaseCount('document_references', 12);
        $this->assertDatabaseCount('document_files', 0);
    }

    public function test_mysql_database_guard_rejects_an_unrelated_database(): void
    {
        $original = config('database');
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unrelated_database']);
        $this->expectException(\RuntimeException::class);
        try {
            (new AppServiceProvider($this->app))->boot();
        } finally {
            config(['database' => $original]);
        }
    }

    public function test_first_administrator_can_be_created_through_the_console(): void
    {
        $this->artisan('archive:create-admin')
            ->expectsQuestion('Administrator name', 'Fictional Admin')
            ->expectsQuestion('Administrator email', 'console@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'ExamplePassword!123')
            ->expectsQuestion('Confirm password', 'ExamplePassword!123')
            ->expectsOutput('Administrator created.')
            ->assertExitCode(0);
        $this->assertDatabaseHas('users', ['email' => 'console@example.test', 'role' => 'admin', 'is_active' => true]);
    }

    public function test_database_url_cannot_override_the_dedicated_database(): void
    {
        $original = config('database');
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'shipping_receiving_archive', 'database.connections.mysql.url' => 'mysql://localhost/unrelated_database']);
        $this->expectException(\RuntimeException::class);
        try {
            (new AppServiceProvider($this->app))->boot();
        } finally {
            config(['database' => $original]);
        }
    }
}
