<?php

namespace App\Support;

use App\Models\DocumentType;

final class DocumentMetadata
{
    public static function configuration(DocumentType $type): array
    {
        [$partyKinds, $primaryKind, $partyRequired, $carrierRequired, $handledLabel] = match ($type->slug) {
            'vendor-packing-slips' => [['vendor'], 'po', true, false, 'Received date'],
            'material-test-reports' => [['vendor'], 'po', false, false, 'Received date'],
            'return-merchandise-authorizations' => [['vendor', 'customer'], null, false, false, 'Handled date'],
            'customer-packing-slips' => [['customer'], 'job', true, false, 'Courier pickup date'],
            'waybills' => [[], 'tracking', false, true, 'Pickup date'],
            'job-routers' => [[], 'job', false, false, 'Handled date'],
            default => [[], null, false, false, 'Handled date'],
        };

        return [
            'partyKinds' => $partyKinds, 'primaryKind' => $primaryKind,
            'partyRequired' => $partyRequired, 'carrierRequired' => $carrierRequired,
            // Tracking/invoice numbers are always optional, including waybills.
            'requiredReference' => in_array($type->slug, ['vendor-packing-slips', 'customer-packing-slips', 'job-routers'], true) ? $primaryKind : null,
            'showCarrier' => $type->section === 'shipping',
            'showHandled' => $type->slug !== 'job-routers' || $type->requires_handled_on,
            'handledLabel' => $handledLabel, 'requiresHandled' => $type->requires_handled_on,
        ];
    }

    public static function types(): array
    {
        return DocumentType::orderBy('sort_order')->get()->map(fn (DocumentType $type) => [
            'id' => $type->id, 'name' => $type->name, 'slug' => $type->slug,
            'section' => $type->section, 'metadata' => self::configuration($type),
        ])->all();
    }
}
