<?php

return [
    'vendor-packing-slips' => ['name' => 'Vendor Packing Slips', 'label' => 'Vendor Packing Slips', 'section' => 'receiving', 'requires_handled_on' => true],
    'material-test-reports' => ['name' => 'Material Test Reports (MTRs)', 'label' => 'MTRs', 'section' => 'receiving', 'requires_handled_on' => false],
    'return-merchandise-authorizations' => ['name' => 'Return Merchandise Authorizations (RMAs)', 'label' => 'RMAs', 'section' => 'receiving', 'requires_handled_on' => true],
    'customer-packing-slips' => ['name' => 'Customer Packing Slips', 'label' => 'Customer Packing Slips', 'section' => 'shipping', 'requires_handled_on' => true],
    'waybills' => ['name' => 'Waybills', 'label' => 'Waybills', 'section' => 'shipping', 'requires_handled_on' => true],
    'job-routers' => ['name' => 'Job Routers', 'label' => 'Job Routers', 'section' => 'routers', 'requires_handled_on' => false],
];
