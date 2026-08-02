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
    encoding: string;
    headers: string[];
    rows: NacexCsvRow[];
}

export interface NacexCsvRow {
    originAgency: string;
    trackingNumber: string;
    registrationDate: string;
    reference: string;
    postalCode: string;
    status: string;
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
