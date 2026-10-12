export const archiveCategories: Record<string, { description: string; searchPlaceholder: string }> = {
    'vendor-packing-slips': {
        description: 'Receiving paperwork filed with vendor deliveries.',
        searchPlaceholder: 'Search vendor packing slip references...',
    },
    'material-test-reports': {
        description: 'Material certifications and mill test reports.',
        searchPlaceholder: 'Search material test report references...',
    },
    'return-merchandise-authorizations': {
        description: 'Return merchandise authorizations and related records.',
        searchPlaceholder: 'Search RMA references...',
    },
    'customer-packing-slips': {
        description: 'Packing slips for outbound customer orders.',
        searchPlaceholder: 'Search customer packing slip references...',
    },
    waybills: { description: 'Freight waybills and shipment tracking records.', searchPlaceholder: 'Search waybill references...' },
    'job-routers': { description: 'Production job routers and traveler documents.', searchPlaceholder: 'Search job router references...' },
};
