<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Party;
use App\Models\User;
use App\Support\DocumentMetadata;
use App\Support\Normalization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DocumentMetadataService
{
    public function save(Document $document, array $input, User $reviewer, bool $file): Document
    {
        return DB::transaction(function () use ($document, $input, $reviewer, $file) {
            $document = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            Gate::authorize($file ? 'file' : 'update', $document);
            $filingRules = $file || $document->status === 'filed';
            $typeId = $input['document_type_id'] ?? $document->document_type_id;
            $type = is_scalar($typeId) ? DocumentType::find($typeId) : null;
            $configuration = $type ? DocumentMetadata::configuration($type) : null;
            $data = array_replace($document->only(['document_type_id', 'party_id', 'document_date', 'handled_on', 'carrier', 'notes']), [
                'document_date' => $document->document_date?->format('Y-m-d'),
                'handled_on' => $document->handled_on?->format('Y-m-d'),
                'references' => $document->references->map->only(['kind', 'value'])->all(),
                'new_party_name' => null, 'new_party_kind' => null,
            ], array_intersect_key($input, array_flip(['document_type_id', 'party_id', 'document_date', 'handled_on', 'carrier', 'notes', 'references', 'new_party_name', 'new_party_kind'])));
            $newParty = filled($data['new_party_name']);
            $rules = [
                'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
                'party_id' => [$filingRules && ($configuration['partyRequired'] ?? false) && ! $newParty ? 'required' : 'nullable', 'integer', 'exists:parties,id'],
                'new_party_name' => ['nullable', 'string', 'max:255'],
                'new_party_kind' => [$newParty ? 'required' : 'nullable', Rule::in($configuration['partyKinds'] ?? [])],
                'document_date' => ['nullable', 'date_format:Y-m-d'],
                'handled_on' => [$filingRules && $type?->requires_handled_on ? 'required' : 'nullable', 'date_format:Y-m-d'],
                'carrier' => [$filingRules && ($configuration['carrierRequired'] ?? false) ? 'required' : 'nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:10000'],
                'references' => ['present', 'array', 'max:100'],
                'references.*.kind' => ['required', Rule::in(['po', 'job', 'invoice', 'tracking'])],
                'references.*.value' => ['required', 'string', 'max:255'],
            ];
            $validator = Validator::make($data, $rules, [
                'party_id.required' => 'Select or enter the '.implode('/', $configuration['partyKinds'] ?? ['party']).' before filing.',
                'handled_on.required' => 'Enter the actual '.strtolower($configuration['handledLabel'] ?? 'handled date').' before filing.',
                'carrier.required' => 'Enter the carrier before filing this waybill.',
                'references.*.value.required' => 'Enter a value or remove this reference row.',
            ]);
            $validator->after(function ($validator) use ($data, $configuration, $filingRules, $newParty) {
                $configuration ??= ['partyKinds' => [], 'requiredReference' => null];
                if ($newParty && filled($data['party_id'])) {
                    $validator->errors()->add('party_id', 'Choose an existing party or enter a new one, not both.');
                }
                if (filled($data['party_id']) && is_scalar($data['party_id'])) {
                    $party = Party::find($data['party_id']);
                    if ($party && ! in_array($party->kind, $configuration['partyKinds'], true)) {
                        $validator->errors()->add('party_id', 'This party does not match the selected document category.');
                    }
                }
                $seen = [];
                $kinds = [];
                foreach (is_array($data['references']) ? $data['references'] : [] as $index => $reference) {
                    if (! is_array($reference) || ! is_string($reference['value'] ?? null) || ! is_string($reference['kind'] ?? null)) {
                        continue;
                    }
                    $normalized = Normalization::reference($reference['value']);
                    $key = $reference['kind'].'|'.$normalized;
                    if ($normalized === '') {
                        $validator->errors()->add('references.'.$index.'.value', 'A reference must contain a letter or number.');
                    } elseif (isset($seen[$key])) {
                        $validator->errors()->add('references.'.$index.'.value', 'This reference is already entered for this document.');
                    }
                    $seen[$key] = true;
                    if ($normalized !== '') {
                        $kinds[] = $reference['kind'];
                    }
                }
                if ($filingRules && $configuration['requiredReference'] && ! in_array($configuration['requiredReference'], $kinds, true)) {
                    $label = ['po' => 'PO', 'job' => 'job'][$configuration['requiredReference']];
                    $validator->errors()->add('references', 'Enter at least one '.$label.' reference before filing.');
                }
            });
            $validated = $validator->validate();
            if ($newParty) {
                $party = Party::firstOrCreate(['normalized_name' => Normalization::value($validated['new_party_name']), 'kind' => $validated['new_party_kind']], ['name' => $validated['new_party_name']]);
                $validated['party_id'] = $party->id;
            }
            $document->fill(array_intersect_key($validated, array_flip(['document_type_id', 'party_id', 'document_date', 'handled_on', 'carrier', 'notes'])));
            if ($file) {
                $document->forceFill(['status' => 'filed', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            }
            $document->save();
            $references = collect($validated['references'])->map(fn ($reference) => ['kind' => $reference['kind'], 'value' => $reference['value'], 'key' => $reference['kind'].'|'.Normalization::reference($reference['value'])]);
            // Keep unchanged reference IDs; remove only references omitted by the user.
            $existing = $document->references()->get()->keyBy(fn ($reference) => $reference->kind.'|'.$reference->normalized_value);
            foreach ($existing as $key => $reference) {
                if (! $references->contains('key', $key)) {
                    $reference->delete();
                }
            }
            foreach ($references as $reference) {
                if ($existing->has($reference['key'])) {
                    $existing[$reference['key']]->update(['value' => $reference['value']]);
                } else {
                    $document->references()->create(['kind' => $reference['kind'], 'value' => $reference['value']]);
                }
            }

            return $document;
        });
    }
}
