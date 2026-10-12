export interface DocumentTypeOption {
    id: number;
    name: string;
}
export interface ArchiveFile {
    original_name: string;
    mime: string;
    size: number;
}
export interface InboxDocument {
    is_demo?: boolean;
    uuid: string;
    category: string;
    uploaded_by: string;
    created_at: string;
    status: string;
    file: ArchiveFile | null;
}
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
export const fileSize = (bytes: number): string =>
    bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
export const uploadDate = (date: string): string =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
export const fileType = (mime: string): string => ({ 'application/pdf': 'PDF', 'image/jpeg': 'JPG', 'image/png': 'PNG' })[mime] || 'File';

export type ReferenceKind = 'po' | 'job' | 'invoice' | 'tracking';
export interface DocumentReference {
    kind: ReferenceKind;
    value: string;
}
export interface PartyOption {
    id: number;
    name: string;
    kind: 'vendor' | 'customer';
}
export interface MetadataConfiguration {
    partyKinds: ('vendor' | 'customer')[];
    primaryKind: ReferenceKind | null;
    partyRequired: boolean;
    requiredReference: ReferenceKind | null;
    carrierRequired: boolean;
    showCarrier: boolean;
    showHandled: boolean;
    handledLabel: string;
    requiresHandled: boolean;
}
export interface MetadataDocumentType extends DocumentTypeOption {
    slug: string;
    section: string;
    metadata: MetadataConfiguration;
}
export interface FiledDocument extends InboxDocument {
    category_slug: string;
    party: PartyOption | null;
    document_date: string | null;
    handled_on: string | null;
    carrier: string | null;
    references: DocumentReference[];
    connections?: DocumentReference[];
}
export interface ReviewDocument extends FiledDocument {
    document_type_id: number;
    party_id: number | null;
    notes: string | null;
    file_available: boolean;
    can_update: boolean;
    can_file: boolean;
    can_discard: boolean;
    reviewed_by: string | null;
    reviewed_at: string | null;
}
export interface DocumentFilters {
    document_type_id?: string | number;
    q: string;
    party_id: string | number;
    from: string;
    to: string;
    date_field: string;
    sort: string;
}
export interface DocumentPage<T = FiledDocument> {
    data: T[];
    links: PaginationLink[];
    total: number;
    from?: number | null;
    to?: number | null;
}
export const referenceLabels: Record<ReferenceKind, string> = { po: 'PO', job: 'Job', invoice: 'Invoice', tracking: 'Tracking' };
export const businessDate = (value: string | null): string =>
    value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`)) : '—';
