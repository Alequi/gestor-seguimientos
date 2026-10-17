<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type {
    ExecutionReportRow,
    FulfillmentExecutionResult,
    FulfillmentExecutionStatus,
    NacexCsvRow,
    ShopifyOrderPreview,
    ShipmentSelection,
} from '../../Types/CsvUpload';
import { isSafeShopifyAdminUrl } from '../../Utils/shopifyAdminUrl';

const props = defineProps<{
    executionUrl: string;
    reportUrl: string;
    sourceFile: File;
    selections: ShipmentSelection[];
    rows: NacexCsvRow[];
}>();

const isConfirmationOpen = ref(false);
const notifyCustomer = ref(true);
const isExecuting = ref(false);
const isDownloading = ref(false);
const requestError = ref<string | null>(null);
const downloadError = ref<string | null>(null);
const results = ref<FulfillmentExecutionResult[]>([]);
const confirmationDialog = ref<HTMLElement | null>(null);
const executionTrigger = ref<HTMLButtonElement | null>(null);

const resultByKey = computed(() => new Map(results.value.map((result) => [resultKey(result), result])));
const pendingSelections = computed(() =>
    props.selections.filter((selection) => {
        const result = resultByKey.value.get(selectionKey(selection));
        return result === undefined || result.status === 'ERROR';
    }),
);
const notificationCount = computed(() => notifyCustomer.value ? pendingSelections.value.length : 0);
const executionLabel = computed(() => pendingSelections.value.length === 1
    ? 'Completar 1 pedido'
    : `Completar ${pendingSelections.value.length} pedidos`);

const STATUS_LABELS: Record<FulfillmentExecutionStatus, string> = {
    COMPLETED: 'Completado',
    TRACKING_EXISTS: 'Seguimiento existente',
    ALREADY_FULFILLED: 'Ya preparado',
    SKIPPED_AFTER_REVALIDATION: 'Revisión necesaria',
    ERROR: 'Error',
};

function openConfirmation(): void {
    if (pendingSelections.value.length === 0 || isExecuting.value) {
        return;
    }

    requestError.value = null;
    isConfirmationOpen.value = true;
    void nextTick(() => confirmationDialog.value?.focus());
}

function closeConfirmation(): void {
    if (!isExecuting.value) {
        isConfirmationOpen.value = false;
        void nextTick(() => executionTrigger.value?.focus());
    }
}

async function confirmExecution(): Promise<void> {
    await execute(pendingSelections.value);

    if (requestError.value === null) {
        isConfirmationOpen.value = false;
        void nextTick(() => executionTrigger.value?.focus());
    }
}

async function retry(result: FulfillmentExecutionResult): Promise<void> {
    const selection = props.selections.find(
        (candidate) => selectionKey(candidate) === resultKey(result),
    );

    if (selection) {
        await execute([selection]);
    }
}

async function execute(selections: ShipmentSelection[]): Promise<void> {
    if (selections.length === 0 || isExecuting.value) {
        return;
    }

    isExecuting.value = true;
    requestError.value = null;
    downloadError.value = null;

    const formData = new FormData();
    formData.append('csv', props.sourceFile);
    formData.append('selections', JSON.stringify(selections));
    formData.append('notifyCustomer', notifyCustomer.value ? '1' : '0');

    try {
        const response = await fetch(props.executionUrl, {
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
            requestError.value = readErrorMessage(payload);
            return;
        }

        const nextResults = normalizeResults(
            payload,
            notifyCustomer.value,
            formatProcessingDate(new Date()),
        );
        if (nextResults === null) {
            requestError.value = 'El servidor ha devuelto un resultado incompleto. Revisa Shopify antes de reintentarlo.';
            return;
        }

        const merged = new Map(results.value.map((result) => [resultKey(result), result]));
        nextResults.forEach((result) => merged.set(resultKey(result), result));
        results.value = Array.from(merged.values());
    } catch {
        requestError.value = 'No se ha podido conectar con el servidor. Revisa Shopify antes de reintentarlo.';
    } finally {
        isExecuting.value = false;
    }
}

async function downloadReport(): Promise<void> {
    if (results.value.length === 0 || isDownloading.value) {
        return;
    }

    isDownloading.value = true;
    downloadError.value = null;

    try {
        const response = await fetch(props.reportUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/csv, application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ rows: buildReportRows() }),
        });

        if (!response.ok) {
            const payload = response.headers.get('content-type')?.includes('application/json')
                ? await response.json()
                : null;
            downloadError.value = readReportError(payload);
            return;
        }

        const downloadUrl = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = 'informe-ejecucion-nacex.csv';
        link.click();
        URL.revokeObjectURL(downloadUrl);
    } catch {
        downloadError.value = 'No se ha podido descargar el informe. Los resultados siguen disponibles en esta página.';
    } finally {
        isDownloading.value = false;
    }
}

function buildReportRows(): ExecutionReportRow[] {
    const executions = new Map(
        results.value.map((result) => [shipmentIdentity(result.originAgency, result.trackingNumber), result]),
    );

    return props.rows.map((row) => {
        const execution = executions.get(shipmentIdentity(row.originAgency, row.trackingNumber));
        const order = reportOrder(row, execution);

        return {
            reference: row.reference,
            originAgency: row.originAgency,
            trackingNumber: row.trackingNumber,
            postalCode: row.postalCode,
            registrationDate: row.registrationDate,
            nacexStatus: row.status,
            trackingUrl: row.trackingUrl,
            matchStatus: row.matchStatus ?? '',
            shopifyOrderName: order?.name ?? execution?.orderName ?? '',
            shopifyCustomer: order?.shippingName ?? '',
            shopifyOrderDate: order?.createdAt ?? '',
            previousOrderStatus: order?.fulfillmentStatus ?? '',
            fulfillmentResult: execution?.status ?? '',
            notificationRequested: execution?.notificationRequested ? 'SÍ' : 'NO',
            processedAt: execution?.processedAt ?? '',
            errorMessage: execution?.status === 'ERROR'
                ? execution.message
                : row.errorMessage ?? '',
        };
    });
}

function reportOrder(
    row: NacexCsvRow,
    execution: FulfillmentExecutionResult | undefined,
): ShopifyOrderPreview | null {
    if (execution) {
        const executedOrder = [row.shopifyOrder, ...row.candidateOrders]
            .find((order) => order?.id === execution.orderId);

        if (executedOrder) {
            return executedOrder;
        }
    }

    return row.shopifyOrder;
}

function shipmentIdentity(originAgency: string, trackingNumber: string): string {
    return `${originAgency}:${trackingNumber}`;
}

function formatProcessingDate(date: Date): string {
    const parts = new Intl.DateTimeFormat('es-ES', {
        timeZone: 'Europe/Madrid',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(date);
    const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

    return `${values.day}/${values.month}/${values.year} ${values.hour}:${values.minute}:${values.second}`;
}

function readReportError(payload: unknown): string {
    if (isRecord(payload) && typeof payload.message === 'string') {
        return payload.message;
    }

    return 'No se ha podido generar el informe. Los resultados siguen disponibles en esta página.';
}

function selectionKey(selection: ShipmentSelection): string {
    return `${selection.originAgency}:${selection.trackingNumber}:${selection.orderId}`;
}

function resultKey(result: FulfillmentExecutionResult): string {
    return `${result.originAgency}:${result.trackingNumber}:${result.orderId}`;
}

function resultTone(status: FulfillmentExecutionStatus): string {
    if (status === 'COMPLETED') return 'success';
    if (status === 'ERROR') return 'danger';
    return 'warning';
}

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function parseJsonResponse(response: Response): Promise<unknown> {
    if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('The server response is not JSON.');
    }

    return response.json();
}

function readErrorMessage(payload: unknown): string {
    if (!isRecord(payload)) {
        return 'No se ha podido iniciar el cierre de pedidos.';
    }

    if (isRecord(payload.errors)) {
        const firstError = Object.values(payload.errors).find(Array.isArray);
        if (Array.isArray(firstError) && typeof firstError[0] === 'string') {
            return firstError[0];
        }
    }

    return typeof payload.message === 'string'
        ? payload.message
        : 'No se ha podido iniciar el cierre de pedidos.';
}

function normalizeResults(
    payload: unknown,
    notificationRequested: boolean,
    processedAt: string,
): FulfillmentExecutionResult[] | null {
    if (!isRecord(payload) || !isRecord(payload.data) || !Array.isArray(payload.data.results)) {
        return null;
    }

    const normalized = payload.data.results.map((result) => normalizeResult(
        result,
        notificationRequested,
        processedAt,
    ));
    return normalized.every((result): result is FulfillmentExecutionResult => result !== null)
        ? normalized
        : null;
}

function normalizeResult(
    value: unknown,
    notificationRequested: boolean,
    processedAt: string,
): FulfillmentExecutionResult | null {
    if (!isRecord(value)
        || typeof value.originAgency !== 'string'
        || typeof value.trackingNumber !== 'string'
        || typeof value.orderId !== 'string'
        || (value.orderName !== null && typeof value.orderName !== 'string')
        || typeof value.status !== 'string'
        || !isExecutionStatus(value.status)
        || typeof value.message !== 'string') {
        return null;
    }

    if (value.adminUrl !== null && !isSafeShopifyAdminUrl(value.adminUrl)) {
        return null;
    }

    return {
        originAgency: value.originAgency,
        trackingNumber: value.trackingNumber,
        orderId: value.orderId,
        orderName: value.orderName,
        adminUrl: value.adminUrl,
        status: value.status,
        message: value.message,
        notificationRequested: value.status === 'COMPLETED' && notificationRequested,
        processedAt,
    };
}

function isExecutionStatus(value: string): value is FulfillmentExecutionStatus {
    return value in STATUS_LABELS;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
</script>

<template>
    <section class="execution-panel" aria-labelledby="execution-title">
        <div>
            <p class="step-label">Confirmación y ejecución</p>
            <h3 id="execution-title">Completar pedidos en Shopify</h3>
            <p>
                Cada pedido se volverá a consultar justo antes de crear el cumplimiento. Si algo ha
                cambiado, esa expedición se detendrá sin bloquear las demás.
            </p>
        </div>

        <button
            ref="executionTrigger"
            class="execute-action"
            type="button"
            :disabled="pendingSelections.length === 0 || isExecuting"
            @click="openConfirmation"
        >
            {{ isExecuting ? 'Completando pedidos…' : executionLabel }}
        </button>

        <p v-if="requestError" class="request-error" role="alert">{{ requestError }}</p>

        <div v-if="results.length > 0" class="execution-results" aria-live="polite">
            <div class="results-heading">
                <div>
                    <h4>Resultado de la ejecución</h4>
                    <p>El informe incluye todas las expediciones analizadas y no se guardará en la aplicación.</p>
                </div>
                <button
                    type="button"
                    class="download-action"
                    :disabled="isDownloading || isExecuting"
                    @click="downloadReport"
                >
                    {{ isDownloading ? 'Preparando informe…' : 'Descargar informe CSV' }}
                </button>
            </div>
            <p v-if="downloadError" class="request-error" role="alert">{{ downloadError }}</p>
            <ul>
                <li v-for="result in results" :key="resultKey(result)">
                    <span class="result-copy">
                        <strong>{{ result.orderName || result.trackingNumber }}</strong>
                        <small>{{ result.message }}</small>
                    </span>
                    <span class="result-actions">
                        <span class="result-badge" :class="`result-${resultTone(result.status)}`">
                            {{ STATUS_LABELS[result.status] }}
                        </span>
                        <a
                            v-if="result.adminUrl"
                            :href="result.adminUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >Abrir pedido</a>
                        <button
                            v-if="result.status === 'ERROR'"
                            type="button"
                            :disabled="isExecuting"
                            @click="retry(result)"
                        >Reintentar</button>
                    </span>
                </li>
            </ul>
        </div>

        <div
            v-if="isConfirmationOpen"
            class="modal-backdrop"
            role="presentation"
            @click.self="closeConfirmation"
        >
            <section
                ref="confirmationDialog"
                class="confirmation-dialog"
                role="dialog"
                tabindex="-1"
                aria-modal="true"
                aria-labelledby="confirmation-title"
                aria-describedby="confirmation-copy"
                @keydown.esc="closeConfirmation"
            >
                <p class="dialog-label">Última comprobación</p>
                <h3 id="confirmation-title">¿Completar los pedidos seleccionados?</h3>
                <p id="confirmation-copy">
                    Shopify se modificará al confirmar. Cada pedido se revalidará de forma
                    independiente antes de crear su cumplimiento NACEX.
                </p>

                <dl>
                    <div>
                        <dt>Pedidos seleccionados</dt>
                        <dd>{{ pendingSelections.length.toLocaleString('es-ES') }}</dd>
                    </div>
                    <div>
                        <dt>Notificaciones solicitadas</dt>
                        <dd>{{ notificationCount.toLocaleString('es-ES') }}</dd>
                    </div>
                </dl>

                <label class="notification-option">
                    <input v-model="notifyCustomer" type="checkbox" :disabled="isExecuting">
                    <span>
                        <strong>Notificar al cliente</strong>
                        <small>Activado por defecto. Solo se solicita al crear un cumplimiento nuevo.</small>
                    </span>
                </label>

                <p v-if="requestError" class="request-error" role="alert">{{ requestError }}</p>

                <div class="dialog-actions">
                    <button type="button" class="cancel-action" :disabled="isExecuting" @click="closeConfirmation">
                        Cancelar
                    </button>
                    <button type="button" class="confirm-action" :disabled="isExecuting" @click="confirmExecution">
                        {{ isExecuting ? 'Revalidando y completando…' : 'Confirmar y completar' }}
                    </button>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.execution-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px 28px;
    align-items: center;
    padding: 26px 30px 30px;
    border-top: 1px solid var(--color-border);
    background: #fbfcff;
}

.step-label,
.dialog-label {
    margin: 0 0 7px;
    color: var(--color-accent);
    font-size: 0.7rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.execution-panel h3,
.confirmation-dialog h3 {
    margin: 0;
    color: var(--color-ink);
    letter-spacing: -0.025em;
}

.execution-panel > div:first-child > p:last-child,
.confirmation-dialog > p {
    max-width: 64ch;
    margin: 8px 0 0;
    color: var(--color-muted);
    line-height: 1.55;
}

.execute-action,
.confirm-action,
.cancel-action,
.download-action,
.result-actions button {
    border-radius: 10px;
    cursor: pointer;
    font: inherit;
    font-weight: 700;
    transition: background-color 150ms ease, box-shadow 150ms ease, transform 140ms var(--ease-out);
}

.execute-action,
.confirm-action {
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    color: #fff;
    background: var(--color-accent);
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

button:focus-visible,
.result-actions a:focus-visible {
    outline: 3px solid var(--color-focus);
    outline-offset: 2px;
}

.request-error {
    grid-column: 1 / -1;
    margin: 0;
    padding: 12px 14px;
    border: 1px solid #efc7c5;
    border-radius: 10px;
    color: #8f2f2a;
    background: #fff5f4;
}

.execution-results {
    grid-column: 1 / -1;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

.execution-results h4 {
    margin: 0;
    color: var(--color-ink);
}

.results-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 12px;
}

.results-heading p {
    margin: 4px 0 0;
    color: var(--color-muted);
    font-size: 0.8125rem;
}

.execution-results ul {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.execution-results li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 12px 14px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-surface);
}

.result-copy {
    display: grid;
    gap: 3px;
}

.result-copy small {
    color: var(--color-muted);
    line-height: 1.4;
}

.result-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 10px;
}

.result-actions a {
    color: var(--color-accent);
    font-size: 0.78rem;
    font-weight: 650;
}

.result-actions button,
.download-action,
.cancel-action {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #d8dde5;
    color: #4a5462;
    background: var(--color-surface);
}

.result-badge {
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 750;
    white-space: nowrap;
}

.result-success { color: #19623a; background: #e8f6ed; }
.result-warning { color: #755817; background: #fff5d8; }
.result-danger { color: #8f2f2a; background: #fff0ef; }

.modal-backdrop {
    position: fixed;
    z-index: 20;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgb(18 25 38 / 46%);
}

.confirmation-dialog {
    width: min(100%, 520px);
    padding: 28px;
    border: 1px solid rgb(255 255 255 / 55%);
    border-radius: 20px;
    background: var(--color-surface);
    box-shadow: 0 24px 72px rgb(15 23 42 / 24%);
}

.confirmation-dialog dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin: 22px 0;
}

.confirmation-dialog dl div {
    padding: 12px;
    border-radius: 10px;
    background: #f5f7fa;
}

.confirmation-dialog dt {
    color: var(--color-subtle);
    font-size: 0.72rem;
}

.confirmation-dialog dd {
    margin: 3px 0 0;
    color: var(--color-ink);
    font-size: 1.15rem;
    font-weight: 760;
}

.notification-option {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-radius: 11px;
    cursor: pointer;
}

.notification-option input {
    width: 18px;
    height: 18px;
    margin: 2px 0 0;
    accent-color: var(--color-accent);
}

.notification-option span {
    display: grid;
    gap: 3px;
}

.notification-option small {
    color: var(--color-muted);
    line-height: 1.4;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 22px;
}

@media (hover: hover) and (pointer: fine) {
    .execute-action:not(:disabled):hover,
    .confirm-action:not(:disabled):hover {
        background: var(--color-accent-hover);
        box-shadow: 0 8px 20px rgb(47 91 210 / 20%);
    }

    .cancel-action:not(:disabled):hover,
    .download-action:not(:disabled):hover,
    .result-actions button:not(:disabled):hover {
        background: #f5f7fa;
    }

    button:not(:disabled):active {
        transform: scale(0.97);
    }
}

@media (max-width: 700px) {
    .execution-panel {
        grid-template-columns: 1fr;
        padding: 24px 20px;
    }

    .execute-action {
        width: 100%;
    }

    .execution-results li,
    .results-heading,
    .result-actions {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 460px) {
    .confirmation-dialog {
        padding: 22px;
    }

    .confirmation-dialog dl {
        grid-template-columns: 1fr;
    }

    .dialog-actions {
        flex-direction: column-reverse;
    }

    .dialog-actions button {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .execute-action,
    .confirm-action,
    .cancel-action,
    .download-action,
    .result-actions button {
        transition-duration: 0ms;
    }
}
</style>
