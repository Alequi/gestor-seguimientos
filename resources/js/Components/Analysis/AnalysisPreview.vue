<script setup lang="ts">
import { computed, toRef } from 'vue';
import FulfillmentExecutionPanel from '../Fulfillment/FulfillmentExecutionPanel.vue';
import { useShipmentSelection } from '../../Composables/useShipmentSelection';
import type {
    NacexCsvRow,
    ShopifyOrderPreview,
    ShipmentMatchStatus,
} from '../../Types/CsvUpload';

const props = defineProps<{
    rows: NacexCsvRow[];
    sourceFile: File;
    executionUrl: string;
}>();

const {
    assignedOrderId,
    assignOrder,
    canSelect,
    clearSelection,
    excludedCount,
    isExcluded,
    isSelected,
    rowKey,
    selectedCount,
    selectedShipments,
    selectSafeMatches,
    setSelected,
    toggleExcluded,
} = useShipmentSelection(toRef(props, 'rows'));

interface StatusPresentation {
    label: string;
    tone: 'safe' | 'neutral' | 'warning' | 'danger';
}

const STATUS_PRESENTATION: Record<ShipmentMatchStatus, StatusPresentation> = {
    MATCHED_SAFE: { label: 'Coincidencia segura', tone: 'safe' },
    NO_MATCH: { label: 'Sin coincidencia', tone: 'neutral' },
    AMBIGUOUS: { label: 'Coincidencia ambigua', tone: 'warning' },
    ALREADY_FULFILLED: { label: 'Pedido ya preparado', tone: 'warning' },
    TRACKING_EXISTS: { label: 'Seguimiento existente', tone: 'warning' },
    FULFILLMENT_EXISTS: { label: 'Cumplimiento existente', tone: 'warning' },
    PARTIAL_FULFILLMENT: { label: 'Preparación parcial', tone: 'warning' },
    ERROR: { label: 'Error de análisis', tone: 'danger' },
};

const analyzedRowCount = computed(
    () => props.rows.filter((row) => row.matchStatus !== null).length,
);

const safeRowCount = computed(
    () => props.rows.filter((row) => row.matchStatus === 'MATCHED_SAFE').length,
);

function statusPresentation(status: ShipmentMatchStatus | null): StatusPresentation {
    return status === null
        ? { label: 'Análisis no disponible', tone: 'neutral' }
        : STATUS_PRESENTATION[status];
}

function relatedOrders(row: NacexCsvRow): ShopifyOrderPreview[] {
    if (row.shopifyOrder !== null) {
        return [row.shopifyOrder];
    }

    return row.candidateOrders;
}

function shopifyStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        FULFILLED: 'Preparado',
        IN_PROGRESS: 'En preparación',
        ON_HOLD: 'En espera',
        OPEN: 'Abierto',
        PARTIALLY_FULFILLED: 'Preparado parcialmente',
        PENDING_FULFILLMENT: 'Preparación pendiente',
        REQUEST_DECLINED: 'Solicitud rechazada',
        RESTOCKED: 'Repuesto',
        SCHEDULED: 'Programado',
        UNFULFILLED: 'Sin preparar',
    };

    return labels[status] ?? status;
}

function handleSelectionChange(row: NacexCsvRow, event: Event): void {
    setSelected(row, (event.target as HTMLInputElement).checked);
}

function handleAssignmentChange(row: NacexCsvRow, event: Event): void {
    assignOrder(row, (event.target as HTMLInputElement).value);
}
</script>

<template>
    <section class="analysis-preview" aria-labelledby="analysis-title">
        <header class="analysis-heading">
            <div>
                <p class="step-label">Revisión y selección</p>
                <h2 id="analysis-title">Expediciones analizadas</h2>
                <p>
                    Las coincidencias seguras ya están seleccionadas. Los casos ambiguos requieren
                    asignar un candidato y marcarlos expresamente; todavía no se modificará Shopify.
                </p>
            </div>

            <dl class="analysis-summary" aria-label="Resumen del análisis">
                <div>
                    <dt>Analizadas</dt>
                    <dd>{{ analyzedRowCount.toLocaleString('es-ES') }}</dd>
                </div>
                <div>
                    <dt>Seguras</dt>
                    <dd>{{ safeRowCount.toLocaleString('es-ES') }}</dd>
                </div>
                <div>
                    <dt>Seleccionadas</dt>
                    <dd>{{ selectedCount.toLocaleString('es-ES') }}</dd>
                </div>
                <div>
                    <dt>Excluidas</dt>
                    <dd>{{ excludedCount.toLocaleString('es-ES') }}</dd>
                </div>
            </dl>
        </header>

        <div class="selection-toolbar" aria-label="Acciones de selección">
            <p aria-live="polite">
                <strong>{{ selectedCount.toLocaleString('es-ES') }}</strong>
                {{ selectedCount === 1 ? 'expedición seleccionada' : 'expediciones seleccionadas' }}
            </p>
            <div>
                <button type="button" class="secondary-action" @click="selectSafeMatches">
                    Seleccionar seguras
                </button>
                <button
                    type="button"
                    class="secondary-action"
                    :disabled="selectedCount === 0"
                    @click="clearSelection"
                >
                    Deseleccionar todas
                </button>
            </div>
        </div>

        <div
            class="table-region"
            role="region"
            aria-label="Expediciones analizadas; desplázate horizontalmente para ver todas las columnas"
            tabindex="0"
        >
            <table>
                <thead>
                    <tr>
                        <th scope="col">Seleccionar</th>
                        <th scope="col">Referencia NACEX</th>
                        <th scope="col">Agencia</th>
                        <th scope="col">Albarán</th>
                        <th scope="col">Código postal</th>
                        <th scope="col">Fecha alta</th>
                        <th scope="col">Seguimiento</th>
                        <th scope="col">Pedido Shopify</th>
                        <th scope="col">Destinatario Shopify</th>
                        <th scope="col">Estado Shopify</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="rowKey(row)"
                        :class="{ 'is-excluded': isExcluded(row) }"
                    >
                        <td class="selection-cell">
                            <span v-if="isExcluded(row)" class="selection-note">Excluida</span>
                            <label v-else-if="canSelect(row)" class="selection-control">
                                <input
                                    type="checkbox"
                                    :checked="isSelected(row)"
                                    :aria-label="`Seleccionar expedición ${row.trackingNumber}`"
                                    @change="handleSelectionChange(row, $event)"
                                >
                                <span aria-hidden="true"></span>
                            </label>
                            <span
                                v-else-if="row.matchStatus === 'AMBIGUOUS'"
                                class="selection-note"
                            >
                                Asigna pedido
                            </span>
                            <span v-else class="empty-value">—</span>
                        </td>
                        <td class="reference-cell">{{ row.reference }}</td>
                        <td class="numeric-cell">{{ row.originAgency }}</td>
                        <td class="numeric-cell">{{ row.trackingNumber }}</td>
                        <td class="numeric-cell">{{ row.postalCode }}</td>
                        <td class="date-cell">{{ row.registrationDate }}</td>
                        <td>
                            <a
                                class="tracking-link"
                                :href="row.trackingUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Abrir seguimiento
                                <span class="visually-hidden"> de {{ row.trackingNumber }}</span>
                            </a>
                        </td>
                        <td>
                            <fieldset
                                v-if="row.matchStatus === 'AMBIGUOUS'"
                                class="candidate-options"
                            >
                                <legend class="visually-hidden">
                                    Asignar un pedido a la expedición {{ row.trackingNumber }}
                                </legend>
                                <div
                                    v-for="(order, candidateIndex) in relatedOrders(row)"
                                    :key="order.id"
                                    class="candidate-option"
                                >
                                    <input
                                        :id="`candidate-${rowKey(row)}-${candidateIndex}`"
                                        type="radio"
                                        :name="`candidate-${rowKey(row)}`"
                                        :value="order.id"
                                        :checked="assignedOrderId(row) === order.id"
                                        :disabled="isExcluded(row)"
                                        @change="handleAssignmentChange(row, $event)"
                                    >
                                    <label :for="`candidate-${rowKey(row)}-${candidateIndex}`">
                                        {{ order.name }}
                                    </label>
                                    <a
                                        v-if="order.adminUrl"
                                        class="admin-link"
                                        :href="order.adminUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Abrir
                                        <span class="visually-hidden">
                                            pedido {{ order.name }} en Shopify
                                        </span>
                                    </a>
                                </div>
                            </fieldset>
                            <div v-else-if="row.shopifyOrder" class="order-line">
                                <span>{{ row.shopifyOrder.name }}</span>
                                <a
                                    v-if="row.shopifyOrder.adminUrl"
                                    class="admin-link"
                                    :href="row.shopifyOrder.adminUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir
                                    <span class="visually-hidden">
                                        pedido {{ row.shopifyOrder.name }} en Shopify
                                    </span>
                                </a>
                            </div>
                            <span v-else class="empty-value">—</span>
                        </td>
                        <td>
                            <template v-if="relatedOrders(row).length">
                                <span
                                    v-for="order in relatedOrders(row)"
                                    :key="order.id"
                                    class="order-value"
                                >
                                    {{ order.shippingName || 'Sin destinatario' }}
                                </span>
                            </template>
                            <span v-else class="empty-value">—</span>
                        </td>
                        <td>
                            <template v-if="relatedOrders(row).length">
                                <span
                                    v-for="order in relatedOrders(row)"
                                    :key="order.id"
                                    class="order-value"
                                >
                                    {{ shopifyStatusLabel(order.fulfillmentStatus) }}
                                </span>
                            </template>
                            <span v-else class="empty-value">—</span>
                        </td>
                        <td class="result-cell">
                            <span
                                class="status-badge"
                                :class="`status-${statusPresentation(row.matchStatus).tone}`"
                            >
                                {{ statusPresentation(row.matchStatus).label }}
                            </span>
                            <small v-if="row.errorMessage">{{ row.errorMessage }}</small>
                        </td>
                        <td class="actions-cell">
                            <button
                                type="button"
                                class="exclude-action"
                                :aria-pressed="isExcluded(row)"
                                @click="toggleExcluded(row)"
                            >
                                {{ isExcluded(row) ? 'Restaurar' : 'Excluir' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <FulfillmentExecutionPanel
            :execution-url="executionUrl"
            :source-file="sourceFile"
            :selections="selectedShipments"
        />
    </section>
</template>

<style scoped>
.analysis-preview {
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: 24px;
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
}

.analysis-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 32px;
    padding: 28px 30px 24px;
    border-bottom: 1px solid var(--color-border);
}

.analysis-heading > div:first-child {
    max-width: 650px;
}

.step-label {
    margin: 0 0 8px;
    color: var(--color-accent);
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.analysis-heading h2 {
    margin: 0;
    color: var(--color-ink);
    font-size: clamp(1.4rem, 3vw, 1.8rem);
    letter-spacing: -0.03em;
    line-height: 1.15;
}

.analysis-heading p:last-child {
    margin: 9px 0 0;
    color: var(--color-muted);
    line-height: 1.55;
}

.analysis-summary {
    display: grid;
    flex: 0 0 auto;
    grid-template-columns: repeat(4, minmax(74px, 1fr));
    gap: 8px;
    margin: 0;
}

.analysis-summary div {
    padding: 9px 11px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: #f8f9fb;
}

.analysis-summary dt {
    color: var(--color-subtle);
    font-size: 0.6875rem;
    font-weight: 650;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.analysis-summary dd {
    margin: 2px 0 0;
    color: var(--color-ink);
    font-size: 1rem;
    font-variant-numeric: tabular-nums;
    font-weight: 750;
}

.selection-toolbar {
    display: flex;
    min-height: 58px;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 10px 18px 10px 30px;
    border-bottom: 1px solid var(--color-border);
    background: #fcfcfd;
}

.selection-toolbar p {
    margin: 0;
    color: var(--color-muted);
    font-size: 0.8125rem;
}

.selection-toolbar p strong {
    color: var(--color-ink);
    font-variant-numeric: tabular-nums;
}

.selection-toolbar > div {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.secondary-action,
.exclude-action {
    border: 1px solid #d8dde5;
    border-radius: 8px;
    color: #4a5462;
    background: var(--color-surface);
    cursor: pointer;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 680;
    transition: border-color 140ms ease, color 140ms ease, background-color 140ms ease,
        transform 140ms var(--ease-out);
}

.secondary-action {
    min-height: 34px;
    padding: 0 11px;
}

.secondary-action:disabled {
    color: #9aa1ac;
    background: #f1f3f6;
    cursor: not-allowed;
}

.secondary-action:focus-visible,
.exclude-action:focus-visible,
.selection-control input:focus-visible + span,
.candidate-option input:focus-visible {
    outline: 3px solid var(--color-focus);
    outline-offset: 2px;
}

.table-region {
    max-height: min(68vh, 680px);
    overflow: auto;
    overscroll-behavior: contain;
}

.table-region:focus-visible {
    outline: 3px solid var(--color-focus);
    outline-offset: -3px;
}

table {
    width: 100%;
    min-width: 1580px;
    border-collapse: separate;
    border-spacing: 0;
    color: #343c49;
    font-size: 0.8125rem;
    text-align: left;
}

th,
td {
    padding: 11px 13px;
    border-bottom: 1px solid #e8ebef;
    vertical-align: top;
}

th {
    position: sticky;
    z-index: 1;
    top: 0;
    color: #555f6e;
    background: #f8f9fb;
    box-shadow: 0 1px 0 var(--color-border);
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.035em;
    text-transform: uppercase;
    white-space: nowrap;
}

tbody tr:last-child td {
    border-bottom: 0;
}

tbody tr.is-excluded td {
    color: #8c94a0;
    background: #f7f8fa;
}

.selection-cell {
    min-width: 92px;
    text-align: center;
}

.selection-control {
    position: relative;
    display: inline-grid;
    width: 22px;
    height: 22px;
    place-items: center;
    cursor: pointer;
}

.selection-control input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.selection-control span {
    display: grid;
    width: 18px;
    height: 18px;
    place-items: center;
    border: 1.5px solid #aeb7c4;
    border-radius: 5px;
    background: var(--color-surface);
}

.selection-control input:checked + span {
    border-color: var(--color-accent);
    background: var(--color-accent);
}

.selection-control input:checked + span::after {
    width: 7px;
    height: 4px;
    border-bottom: 2px solid #ffffff;
    border-left: 2px solid #ffffff;
    content: '';
    transform: translateY(-1px) rotate(-45deg);
}

.selection-note {
    display: inline-block;
    max-width: 88px;
    color: #757e8b;
    font-size: 0.6875rem;
    font-weight: 650;
    line-height: 1.25;
}

.reference-cell {
    min-width: 190px;
    max-width: 260px;
    overflow-wrap: anywhere;
    color: var(--color-ink);
    font-weight: 650;
}

.numeric-cell,
.date-cell {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.tracking-link {
    color: var(--color-accent);
    font-weight: 650;
    text-underline-offset: 3px;
    white-space: nowrap;
}

.tracking-link:focus-visible,
.admin-link:focus-visible {
    border-radius: 3px;
    outline: 3px solid var(--color-focus);
    outline-offset: 2px;
}

.order-line {
    display: flex;
    min-width: 125px;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
}

.order-line > span {
    color: #414957;
    font-weight: 650;
}

.admin-link {
    color: var(--color-accent);
    font-size: 0.75rem;
    font-weight: 650;
    text-underline-offset: 3px;
}

.candidate-options {
    display: grid;
    min-width: 180px;
    gap: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.candidate-option {
    display: grid;
    min-height: 29px;
    align-items: center;
    grid-template-columns: 16px minmax(62px, 1fr) auto;
    gap: 7px;
}

.candidate-option + .candidate-option {
    padding-top: 5px;
    border-top: 1px solid #e8ebef;
}

.candidate-option input {
    width: 15px;
    height: 15px;
    margin: 0;
    accent-color: var(--color-accent);
}

.candidate-option label {
    color: #414957;
    cursor: pointer;
    font-weight: 650;
}

.candidate-option input:disabled + label {
    cursor: not-allowed;
}

.order-value {
    display: block;
    min-height: 29px;
    min-width: 120px;
    color: #414957;
}

.order-value + .order-value {
    margin-top: 5px;
    padding-top: 5px;
    border-top: 1px solid #e8ebef;
}

.empty-value {
    color: #9aa1ac;
}

.result-cell {
    min-width: 190px;
}

.actions-cell {
    min-width: 84px;
}

.exclude-action {
    min-height: 30px;
    padding: 0 9px;
}

.exclude-action[aria-pressed='true'] {
    border-color: #c7ced8;
    color: #3f4855;
    background: #eef1f5;
}

.result-cell small {
    display: block;
    max-width: 260px;
    margin-top: 6px;
    color: #7f302b;
    line-height: 1.4;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 8px;
    border: 1px solid;
    border-radius: 999px;
    font-size: 0.71875rem;
    font-weight: 720;
    line-height: 1.2;
    white-space: nowrap;
}

.status-safe {
    border-color: #b9ddc6;
    color: #23623a;
    background: #f1faf4;
}

.status-neutral {
    border-color: #d8dde5;
    color: #5a6472;
    background: #f6f7f9;
}

.status-warning {
    border-color: #e8d49c;
    color: #755817;
    background: #fff9e8;
}

.status-danger {
    border-color: #efc7c5;
    color: #8f2f2a;
    background: #fff5f4;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
}

@media (hover: hover) and (pointer: fine) {
    tbody tr:hover td {
        background: #fafbfc;
    }

    .tracking-link:hover {
        color: var(--color-accent-hover);
    }

    .admin-link:hover {
        color: var(--color-accent-hover);
    }

    .secondary-action:not(:disabled):hover,
    .exclude-action:hover {
        border-color: #aeb8c7;
        color: var(--color-ink);
        background: #f7f8fa;
    }

    .secondary-action:not(:disabled):active,
    .exclude-action:active {
        transform: scale(0.97);
    }
}

@media (max-width: 720px) {
    .analysis-heading {
        align-items: stretch;
        flex-direction: column;
        gap: 20px;
        padding: 24px 20px 20px;
    }

    .analysis-summary {
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .selection-toolbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 14px 20px;
    }

    .selection-toolbar > div {
        justify-content: flex-start;
    }
}

@media (prefers-reduced-motion: reduce) {
    .secondary-action,
    .exclude-action {
        transition-duration: 0ms;
    }
}
</style>
