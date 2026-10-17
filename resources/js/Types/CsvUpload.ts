export interface CsvUploadConfig {
    validationUrl: string;
    executionUrl: string;
    reportUrl: string;
    maxFileSizeMb: number;
    maxRows: number;
    requiredHeaders: string[];
}

export type ShipmentAssignment = 'AUTOMATIC' | 'MANUAL';

export interface ShipmentSelection {
    originAgency: string;
    trackingNumber: string;
    orderId: string;
    assignment: ShipmentAssignment;
}

export type FulfillmentExecutionStatus =
    | 'COMPLETED'
    | 'TRACKING_EXISTS'
    | 'ALREADY_FULFILLED'
    | 'SKIPPED_AFTER_REVALIDATION'
    | 'ERROR';

export interface FulfillmentExecutionResult {
    originAgency: string;
    trackingNumber: string;
    orderId: string;
    orderName: string | null;
    adminUrl: string | null;
    status: FulfillmentExecutionStatus;
    message: string;
    notificationRequested: boolean;
    processedAt: string;
}

export interface ExecutionReportRow {
    reference: string;
    originAgency: string;
    trackingNumber: string;
    postalCode: string;
    registrationDate: string;
    nacexStatus: string;
    trackingUrl: string;
    matchStatus: string;
    shopifyOrderName: string;
    shopifyCustomer: string;
    shopifyOrderDate: string;
    previousOrderStatus: string;
    fulfillmentResult: string;
    notificationRequested: string;
    processedAt: string;
    errorMessage: string;
}

export interface CsvValidationResult {
    fileName: string;
    sizeInBytes: number;
    rowCount: number;
    requestedRowCount: number;
    processableRowCount: number;
    duplicateConflictCount: number;
    encoding: string;
    headers: string[];
    rows: NacexCsvRow[];
    duplicateConflicts: NacexCsvDuplicateConflict[];
}

export interface NacexCsvSourceRow {
    originAgency: string;
    trackingNumber: string;
    registrationDate: string;
    reference: string;
    normalizedReference: string;
    postalCode: string;
    status: string;
    trackingUrl: string;
}

export type ShipmentMatchStatus =
    | 'MATCHED_SAFE'
    | 'NO_MATCH'
    | 'AMBIGUOUS'
    | 'ALREADY_FULFILLED'
    | 'TRACKING_EXISTS'
    | 'FULFILLMENT_EXISTS'
    | 'PARTIAL_FULFILLMENT'
    | 'ERROR';

export interface ShopifyOrderPreview {
    id: string;
    name: string;
    createdAt: string;
    shippingName: string | null;
    fulfillmentStatus: string;
    adminUrl: string | null;
}

export interface NacexCsvRow extends NacexCsvSourceRow {
    matchStatus: ShipmentMatchStatus | null;
    shopifyOrder: ShopifyOrderPreview | null;
    candidateOrders: ShopifyOrderPreview[];
    errorMessage: string | null;
}

export interface NacexCsvDuplicateConflict {
    originAgency: string;
    trackingNumber: string;
    rows: NacexCsvSourceRow[];
}

export interface CsvValidationResponse {
    data: CsvValidationResult;
}

export interface CsvValidationErrorResponse {
    message?: string;
    errors?: {
        csv?: string[];
    };
}
