<?php

namespace App\Application\Data;

final readonly class PreparedNacexCsv
{
    /**
     * @param  list<NacexShipmentCandidate>  $shipments
     * @param  list<NacexCsvDuplicateConflict>  $duplicateConflicts
     */
    public function __construct(
        public NacexCsvValidationResult $inspection,
        public int $requestedRowCount,
        public array $shipments,
        public array $duplicateConflicts,
    ) {}
}
