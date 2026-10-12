<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFileFactory extends Factory
{
    // Metadata only; this factory does not create an uploaded file.
    public function definition(): array
    {
        return ['document_id' => Document::factory(), 'role' => 'original', 'disk' => 'documents', 'path' => 'fictional/'.Str::uuid().'.pdf', 'original_name' => 'fictional-sample.pdf', 'mime' => 'application/pdf', 'size' => 1024, 'sha256' => hash('sha256', Str::uuid()), 'page_count' => 1, 'sort_order' => 0];
    }
}
