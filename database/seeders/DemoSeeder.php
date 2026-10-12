<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo data is restricted to local/testing environments.');
        }
        $this->call(DocumentTypeSeeder::class);
        $admin = User::firstOrCreate(['email' => 'admin@example.test'], ['name' => 'Demo Administrator', 'role' => 'admin', 'is_active' => true, 'password' => Hash::make('ArchiveDemo!2026')]);
        User::firstOrCreate(['email' => 'staff@example.test'], ['name' => 'Demo Staff', 'role' => 'staff', 'is_active' => true, 'password' => Hash::make('ArchiveDemo!2026')]);
        $vendor = Party::firstOrCreate(['name' => 'Fictional Prairie Steel', 'kind' => 'vendor']);
        $customer = Party::firstOrCreate(['name' => 'Fictional Summit Fabrication', 'kind' => 'customer']);
        foreach (DocumentType::orderBy('sort_order')->get() as $type) {
            $document = Document::firstOrCreate(['document_type_id' => $type->id, 'notes' => 'Fictional Phase 1 sample; no file attached.'], [
                'party_id' => $type->section === 'routers' ? null : ($type->section === 'shipping' ? $customer->id : $vendor->id),
                'status' => 'filed', 'document_date' => '2026-09-14',
                'handled_on' => $type->requires_handled_on ? '2026-09-16' : null,
                'uploaded_by' => $admin->id, 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
            ]);
            $document->references()->firstOrCreate(['kind' => 'po', 'value' => 'DEMO-PO-1001']);
            $document->references()->firstOrCreate(['kind' => 'job', 'value' => 'DEMO-JOB-2001']);
        }
    }
}
