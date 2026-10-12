<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentReferenceFactory extends Factory
{
    public function definition(): array
    {
        return ['document_id' => Document::factory(), 'kind' => fake()->randomElement(['po', 'job', 'invoice', 'tracking']), 'value' => 'DEMO-'.fake()->unique()->numerify('########')];
    }
}
