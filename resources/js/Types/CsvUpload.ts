export interface CsvUploadConfig {
    validationUrl: string;
    maxFileSizeMb: number;
    maxRows: number;
    requiredHeaders: string[];
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

export interface NacexCsvRow {
    originAgency: string;
    trackingNumber: string;
    registrationDate: string;
    reference: string;
    postalCode: string;
    status: string;
}

export interface NacexCsvDuplicateConflict {
    originAgency: string;
    trackingNumber: string;
    rows: NacexCsvRow[];
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
