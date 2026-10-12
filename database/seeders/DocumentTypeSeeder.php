<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['vendor-packing-slips', 'Vendor Packing Slips', 'receiving', true],
            ['material-test-reports', 'Material Test Reports (MTRs)', 'receiving', false],
            ['rmas', 'RMAs', 'receiving', true],
            ['customer-packing-slips', 'Customer Packing Slips', 'shipping', true],
            ['waybills', 'Waybills', 'shipping', true],
            ['job-routers', 'Job Routers', 'routers', false],
        ];
        foreach ($types as $index => [$slug, $name, $section, $handled]) {
            DocumentType::updateOrCreate(['slug' => $slug], ['name' => $name, 'section' => $section, 'requires_handled_on' => $handled, 'sort_order' => $index + 1]);
        }
    }
}
