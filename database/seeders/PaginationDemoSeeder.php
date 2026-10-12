<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use App\Support\ArchiveCategories;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaginationDemoSeeder extends Seeder
{
    public const MARKER = '[DEMO pagination v1]';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Pagination demo data is restricted to local/testing environments.');
        }
        $connection = DB::connection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            if ($connection->getDatabaseName() !== 'shipping_receiving_archive' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'shipping_receiving_archive') {
                throw new RuntimeException('Demo data requires the shipping_receiving_archive database.');
            }
        } elseif (! app()->environment('testing') || $connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Only the Archive MySQL database or isolated testing SQLite is supported.');
        }
        $slugs = array_keys(config('archive-categories'));
        $types = DocumentType::whereIn('slug', $slugs)->get()->keyBy('slug');
        if ($types->count() !== 6) {
            throw new RuntimeException('The six existing Archive categories must be present before seeding demo data.');
        }
        $faker = Factory::create('en_CA');
        $faker->seed(20261012);
        DB::transaction(function () use ($types, $slugs, $faker) {
            $users = collect(['Avery', 'Morgan', 'Riley'])->map(fn ($name) => User::firstOrCreate(
                ['email' => 'pagination-demo-'.strtolower($name).'@example.test'],
                ['name' => 'Demo '.$name, 'role' => 'staff', 'is_active' => false, 'password' => Str::random(64)]
            ));
            $vendors = collect(['Prairie Alloy', 'Northstar Metals', 'Aspen Industrial', 'Silver Creek Supply'])->map(fn ($name) => Party::firstOrCreate(['name' => 'Fictional Demo '.$name, 'kind' => 'vendor']));
            $customers = collect(['Summit Fabrication', 'Cedar Works', 'Glacier Assembly', 'Meadow Engineering'])->map(fn ($name) => Party::firstOrCreate(['name' => 'Fictional Demo '.$name, 'kind' => 'customer']));
            foreach ($slugs as $categoryIndex => $slug) {
                $type = $types[$slug];
                for ($index = 1; $index <= 125; $index++) {
                    // Stable versioned UUIDs survive metadata edits and soft deletion: never overwrite or resurrect a record.
                    $hash = sha1('shipping-receiving-archive:pagination-demo:v1:'.ArchiveCategories::demoIdentity($slug).':'.$index);
                    $uuid = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
                    $days = $faker->numberBetween(0, 180);
                    $hour = $faker->numberBetween(8, 17);
                    if (Document::withTrashed()->where('uuid', $uuid)->exists()) {
                        continue;
                    }
                    $uploaded = CarbonImmutable::parse('2026-10-12')->subDays($days)->setHour($hour);
                    $documentDate = $uploaded->subDays($index % 4);
                    $status = $index <= 110 ? 'filed' : ($index <= 122 ? 'inbox' : 'discarded');
                    $user = $users[($index + $categoryIndex) % 3];
                    $document = new Document([
                        'document_type_id' => $type->id,
                        'party_id' => $type->section === 'routers' || $slug === 'waybills' ? null : ($type->section === 'shipping' ? $customers : $vendors)[($index - 1) % 4]->id,
                        'status' => $status, 'document_date' => $documentDate->toDateString(),
                        'handled_on' => $type->requires_handled_on ? $uploaded->toDateString() : null,
                        'carrier' => $slug === 'waybills' ? 'Fictional Demo '.['Prairie Freight', 'Mountain Logistics', 'Cedar Transport'][$index % 3] : null,
                        'notes' => self::MARKER.' '.$type->name.' #'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'. Fictional training record; no original file attached.',
                        'uploaded_by' => $user->id, 'reviewed_by' => $status === 'filed' ? $users[($index + 1) % 3]->id : null,
                        'reviewed_at' => $status === 'filed' ? $uploaded->addHours(2) : null,
                    ]);
                    $document->uuid = $uuid;
                    $document->created_at = $uploaded;
                    $document->updated_at = $uploaded;
                    $document->save();
                    foreach (['po' => 'DEMO-PO-'.(10000 + intdiv($index - 1, 3)), 'job' => 'DEMO-JOB-'.(20000 + ($index % 25)), 'invoice' => 'DEMO-INV-'.$categoryIndex.'-'.(30000 + $index), 'tracking' => 'DEMO-TRK-'.$categoryIndex.'-'.(40000 + $index)] as $kind => $value) {
                        if ($kind !== 'tracking' || $type->section === 'shipping') {
                            $document->references()->create(['kind' => $kind, 'value' => $value]);
                        }
                    }
                }
            }
        });
    }
}
