import { computed, ref } from 'vue';
import type {
    CsvUploadConfig,
    CsvValidationErrorResponse,
    CsvValidationResponse,
    CsvValidationResult,
} from '../Types/CsvUpload';

const BYTES_PER_MEGABYTE = 1024 * 1024;

export function useCsvValidation(config: CsvUploadConfig) {
    const selectedFile = ref<File | null>(null);
    const validationResult = ref<CsvValidationResult | null>(null);
    const errors = ref<string[]>([]);
    const isValidating = ref(false);

    const canValidate = computed(
        () => selectedFile.value !== null && !isValidating.value,
    );

    function selectFile(file: File | null): void {
        validationResult.value = null;
        errors.value = [];

        if (file === null) {
            selectedFile.value = null;
            return;
        }

        if (!file.name.toLocaleLowerCase('es').endsWith('.csv')) {
            selectedFile.value = null;
            errors.value = ['El archivo debe tener extensión .csv.'];
            return;
        }

        if (file.size > config.maxFileSizeMb * BYTES_PER_MEGABYTE) {
            selectedFile.value = null;
            errors.value = [
                `El archivo CSV no puede superar ${config.maxFileSizeMb} MB.`,
            ];
            return;
        }

        selectedFile.value = file;
    }

    function clearFile(): void {
        selectFile(null);
    }

    async function validateFile(): Promise<void> {
        if (!selectedFile.value || isValidating.value) {
            return;
        }

        isValidating.value = true;
        errors.value = [];
        validationResult.value = null;

        const formData = new FormData();
        formData.append('csv', selectedFile.value);

        try {
            const response = await fetch(config.validationUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            const payload = await parseJsonResponse(response);

            if (!response.ok) {
                const errorPayload = payload as CsvValidationErrorResponse;
                errors.value = errorPayload.errors?.csv ?? [
                    errorPayload.message ?? 'No se ha podido validar el archivo CSV.',
                ];
                return;
            }

            const result = normalizeValidationResult(
                (payload as Partial<CsvValidationResponse>).data,
            );

            if (result === null) {
                errors.value = [
                    'El servidor ha devuelto una respuesta de validación incompleta. Recarga la página e inténtalo de nuevo.',
                ];
                return;
            }

            validationResult.value = result;
        } catch {
            errors.value = [
                'No se ha podido conectar con el servidor. Comprueba que XAMPP siga activo e inténtalo de nuevo.',
            ];
        } finally {
            isValidating.value = false;
        }
    }

    return {
        canValidate,
        clearFile,
        errors,
        isValidating,
        selectedFile,
        selectFile,
        validateFile,
        validationResult,
    };
}

function getCsrfToken(): string {
    return document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
}

async function parseJsonResponse(response: Response): Promise<unknown> {
    const contentType = response.headers.get('content-type');

    if (!contentType?.includes('application/json')) {
        throw new Error('The server response is not JSON.');
    }

    return response.json();
}

function normalizeValidationResult(
    result: Partial<CsvValidationResult> | undefined,
): CsvValidationResult | null {
    if (
        !result ||
        typeof result.fileName !== 'string' ||
        typeof result.sizeInBytes !== 'number' ||
        typeof result.rowCount !== 'number' ||
        typeof result.requestedRowCount !== 'number' ||
        typeof result.encoding !== 'string' ||
        !Array.isArray(result.headers) ||
        !Array.isArray(result.rows)
    ) {
        return null;
    }

    return {
        fileName: result.fileName,
        sizeInBytes: result.sizeInBytes,
        rowCount: result.rowCount,
        requestedRowCount: result.requestedRowCount,
        processableRowCount:
            typeof result.processableRowCount === 'number'
                ? result.processableRowCount
                : result.rows.length,
        duplicateConflictCount:
            typeof result.duplicateConflictCount === 'number'
                ? result.duplicateConflictCount
                : 0,
        encoding: result.encoding,
        headers: result.headers,
        rows: result.rows,
        duplicateConflicts: Array.isArray(result.duplicateConflicts)
            ? result.duplicateConflicts
            : [],
    };
}
