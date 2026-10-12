<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PartyFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Fictional '.fake()->company(), 'kind' => fake()->randomElement(['vendor', 'customer'])];
    }
}
