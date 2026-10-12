<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['document_type_id' => DocumentType::factory(), 'party_id' => Party::factory(), 'status' => 'inbox', 'document_date' => null, 'handled_on' => null, 'uploaded_by' => User::factory(), 'notes' => 'Fictional factory sample.'];
    }
}
