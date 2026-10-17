import { computed, ref, watch, type Ref } from 'vue';
import type {
    NacexCsvRow,
    ShipmentSelection,
    ShopifyOrderPreview,
} from '../Types/CsvUpload';

export function useShipmentSelection(rows: Readonly<Ref<NacexCsvRow[]>>) {
    const selectedRowKeys = ref<Set<string>>(new Set());
    const excludedRowKeys = ref<Set<string>>(new Set());
    const assignedOrderIds = ref<Map<string, string>>(new Map());

    watch(
        rows,
        (nextRows) => {
            selectedRowKeys.value = new Set(
                nextRows
                    .filter(
                        (row) =>
                            row.matchStatus === 'MATCHED_SAFE' && row.shopifyOrder !== null,
                    )
                    .map(rowKey),
            );
            excludedRowKeys.value = new Set();
            assignedOrderIds.value = new Map();
        },
        { immediate: true },
    );

    const selectedCount = computed(() => selectedRowKeys.value.size);
    const excludedCount = computed(() => excludedRowKeys.value.size);
    const selectedShipments = computed<ShipmentSelection[]>(() =>
        rows.value.flatMap((row) => {
            if (!isSelected(row)) {
                return [];
            }

            const order = selectedOrder(row);
            if (order === null) {
                return [];
            }

            return [{
                originAgency: row.originAgency,
                trackingNumber: row.trackingNumber,
                orderId: order.id,
                assignment: row.matchStatus === 'AMBIGUOUS' ? 'MANUAL' : 'AUTOMATIC',
            }];
        }),
    );

    function rowKey(row: NacexCsvRow): string {
        return `${row.originAgency}:${row.trackingNumber}`;
    }

    function assignedOrderId(row: NacexCsvRow): string | null {
        return assignedOrderIds.value.get(rowKey(row)) ?? null;
    }

    function selectedOrder(row: NacexCsvRow): ShopifyOrderPreview | null {
        if (row.matchStatus === 'MATCHED_SAFE') {
            return row.shopifyOrder;
        }

        if (row.matchStatus !== 'AMBIGUOUS') {
            return null;
        }

        const orderId = assignedOrderId(row);

        return row.candidateOrders.find((order) => order.id === orderId) ?? null;
    }

    function isExcluded(row: NacexCsvRow): boolean {
        return excludedRowKeys.value.has(rowKey(row));
    }

    function canSelect(row: NacexCsvRow): boolean {
        return !isExcluded(row) && selectedOrder(row) !== null;
    }

    function isSelected(row: NacexCsvRow): boolean {
        return selectedRowKeys.value.has(rowKey(row));
    }

    function setSelected(row: NacexCsvRow, selected: boolean): void {
        const nextSelection = new Set(selectedRowKeys.value);
        const key = rowKey(row);

        if (selected && canSelect(row)) {
            nextSelection.add(key);
        } else {
            nextSelection.delete(key);
        }

        selectedRowKeys.value = nextSelection;
    }

    function assignOrder(row: NacexCsvRow, orderId: string): void {
        if (
            row.matchStatus !== 'AMBIGUOUS' ||
            !row.candidateOrders.some((order) => order.id === orderId)
        ) {
            return;
        }

        const nextAssignments = new Map(assignedOrderIds.value);
        nextAssignments.set(rowKey(row), orderId);
        assignedOrderIds.value = nextAssignments;

        setSelected(row, false);
    }

    function toggleExcluded(row: NacexCsvRow): void {
        const key = rowKey(row);
        const nextExcluded = new Set(excludedRowKeys.value);

        if (nextExcluded.has(key)) {
            nextExcluded.delete(key);
        } else {
            nextExcluded.add(key);
            setSelected(row, false);
        }

        excludedRowKeys.value = nextExcluded;
    }

    function selectSafeMatches(): void {
        selectedRowKeys.value = new Set(
            rows.value
                .filter(
                    (row) =>
                        row.matchStatus === 'MATCHED_SAFE' &&
                        row.shopifyOrder !== null &&
                        !isExcluded(row),
                )
                .map(rowKey),
        );
    }

    function clearSelection(): void {
        selectedRowKeys.value = new Set();
    }

    return {
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
        selectedOrder,
        selectSafeMatches,
        setSelected,
        toggleExcluded,
    };
}
