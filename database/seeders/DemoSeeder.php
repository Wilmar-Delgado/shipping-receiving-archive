<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use App\Support\ArchiveCategories;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoFixtures;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public const MARKER = '[DEMO compact v1]';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Demo data is restricted to local/testing environments.');
        }
        $connection = DB::connection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            if ($connection->getDatabaseName() !== 'shipping_receiving_archive' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'shipping_receiving_archive') {
                throw new RuntimeException('Demo data requires the shipping_receiving_archive database.');
            }
        } elseif (! app()->environment('testing') || $connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Only Archive MySQL or isolated testing SQLite is supported.');
        }
        $this->call(DocumentTypeSeeder::class);
        $admin = User::firstOrCreate(['email' => 'admin@example.test'], ['name' => 'Demo Administrator', 'role' => 'admin', 'is_active' => true, 'password' => Hash::make('ArchiveDemo!2026')]);
        $staff = User::firstOrCreate(['email' => 'staff@example.test'], ['name' => 'Demo Staff', 'role' => 'staff', 'is_active' => true, 'password' => Hash::make('ArchiveDemo!2026')]);
        $vendors = collect(['Prairie Steel', 'Cedar Alloy'])->map(fn ($name) => Party::firstOrCreate(['name' => 'Fictional Demo '.$name, 'kind' => 'vendor']));
        $customers = collect(['Summit Fabrication', 'Aspen Assembly'])->map(fn ($name) => Party::firstOrCreate(['name' => 'Fictional Demo '.$name, 'kind' => 'customer']));
        $disk = Storage::disk('documents');
        $image = DemoFixtures::png();
        foreach (DocumentType::orderBy('sort_order')->get() as $type) {
            for ($index = 1; $index <= 9; $index++) {
                $hash = sha1('shipping-receiving-archive:compact-demo:v1:'.ArchiveCategories::demoIdentity($type->slug).':'.$index);
                $uuid = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
                // Never overwrite user edits or resurrect soft-deleted demo records.
                if (Document::withTrashed()->where('uuid', $uuid)->exists()) {
                    continue;
                }
                $uploaded = CarbonImmutable::parse('2026-10-12 10:00:00')->subDays(($index - 1) * 7 + $type->sort_order);
                $status = $index === 9 ? 'inbox' : 'filed';
                $extension = $index === 2 ? 'png' : 'pdf';
                $attachFile = in_array($index, [1, 2, 9], true);
                $path = 'demo/compact-v1/'.$uuid.'.'.$extension;
                $bytes = $extension === 'png' ? $image : DemoFixtures::pdf($type->name.' - DEMO #'.$index);
                // Protect untracked private files too; seeding never overwrites an existing path.
                if ($attachFile && $disk->exists($path)) {
                    throw new RuntimeException('A compact demo fixture path already exists without its document. Preserve it and inspect before seeding.');
                }
                DB::transaction(function () use ($type, $index, $uuid, $uploaded, $status, $admin, $staff, $vendors, $customers, $attachFile, $extension, $path, $bytes, $disk) {
                    $document = new Document([
                        'document_type_id' => $type->id,
                        'party_id' => $type->section === 'routers' || $type->slug === 'waybills' ? null : ($type->section === 'shipping' ? $customers : $vendors)[($index - 1) % 2]->id,
                        'status' => $status, 'document_date' => $uploaded->subDays($index % 3)->toDateString(),
                        'handled_on' => $type->requires_handled_on ? $uploaded->toDateString() : null,
                        'carrier' => $type->slug === 'waybills' ? 'Fictional Demo '.($index % 2 ? 'Prairie Freight' : 'Cedar Transport') : null,
                        'notes' => self::MARKER.' '.$type->name.' #'.$index.'. Fictional local development record.'.($attachFile ? ' Generated fictional fixture attached.' : ' No original file attached.'),
                        'uploaded_by' => $index % 2 ? $admin->id : $staff->id,
                        'reviewed_by' => $status === 'filed' ? $admin->id : null,
                        'reviewed_at' => $status === 'filed' ? $uploaded->addHours(2) : null,
                    ]);
                    $document->uuid = $uuid;
                    $document->created_at = $uploaded;
                    $document->updated_at = $uploaded;
                    $document->save();
                    foreach (['po' => 'DEMO-PO-'.(1001 + intdiv($index - 1, 3)), 'job' => 'DEMO-JOB-'.(2001 + ($index % 4)), 'invoice' => 'DEMO-INV-'.$type->sort_order.'-'.(3000 + $index), 'tracking' => 'DEMO-TRK-'.$type->sort_order.'-'.(4000 + $index)] as $kind => $value) {
                        if ($kind !== 'tracking' || $type->section === 'shipping') {
                            $document->references()->create(['kind' => $kind, 'value' => $value]);
                        }
                    }
                    if ($attachFile) {
                        // Write bytes last. A failed storage write rolls back the database row.
                        $document->files()->create(['role' => 'original', 'disk' => 'documents', 'path' => $path, 'original_name' => 'DEMO-'.$type->slug.'-'.$index.'.'.$extension, 'mime' => $extension === 'png' ? 'image/png' : 'application/pdf', 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'page_count' => $extension === 'pdf' ? 1 : null, 'sort_order' => 0]);
                        $disk->put($path, $bytes);
                    }
                });
            }
        }
    }
}
