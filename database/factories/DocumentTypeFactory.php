<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTypeFactory extends Factory
{
    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(), 'name' => 'Fictional Document Category', 'section' => 'receiving', 'requires_handled_on' => false, 'sort_order' => 1];
    }
}
