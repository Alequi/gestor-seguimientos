<?php

namespace App\Application\Actions;

use App\Application\Contracts\NacexCsvInspector;
use App\Application\Data\NacexCsvRow;
use App\Application\Data\PreparedNacexCsv;
use App\Domain\Shipment\NacexShipmentStatus;

final readonly class PrepareNacexCsv
{
    public function __construct(
        private NacexCsvInspector $inspector,
        private DeduplicateNacexCsvRows $deduplicateRows,
        private NormalizeNacexCsvRows $normalizeRows,
        private BuildNacexTrackingUrls $buildTrackingUrls,
    ) {}

    public function handle(string $path, string $originalName, int $sizeInBytes): PreparedNacexCsv
    {
        $inspection = $this->inspector->inspect($path, $originalName, $sizeInBytes);
        $requestedRows = array_values(array_filter(
            $inspection->rows,
            static fn (NacexCsvRow $row): bool => NacexShipmentStatus::from($row->status)
                ->isRequested(),
        ));
        $deduplicated = $this->deduplicateRows->handle($requestedRows);
        $normalizedRows = $this->normalizeRows->handle($deduplicated->rows);

        return new PreparedNacexCsv(
            inspection: $inspection,
            requestedRowCount: count($requestedRows),
            shipments: $this->buildTrackingUrls->handle($normalizedRows),
            duplicateConflicts: $deduplicated->conflicts,
        );
    }
}
