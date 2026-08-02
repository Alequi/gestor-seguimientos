<?php

namespace App\Application\Actions;

use App\Application\Contracts\NacexCsvInspector;
use App\Application\Data\NacexCsvRow;
use App\Application\Data\NacexCsvValidationResult;
use App\Domain\Shipment\NacexShipmentStatus;

final readonly class ValidateNacexCsv
{
    public function __construct(
        private NacexCsvInspector $inspector,
    ) {}

    public function handle(
        string $path,
        string $originalName,
        int $sizeInBytes,
    ): NacexCsvValidationResult {
        $result = $this->inspector->inspect($path, $originalName, $sizeInBytes);
        $requestedRows = array_values(array_filter(
            $result->rows,
            static fn (NacexCsvRow $row): bool => NacexShipmentStatus::from($row->status)
                ->isRequested(),
        ));

        return $result->withRows($requestedRows);
    }
}
