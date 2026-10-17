<?php

namespace App\Application\Actions;

use App\Application\Data\NacexCsvValidationResult;

final readonly class ValidateNacexCsv
{
    public function __construct(
        private PrepareNacexCsv $prepareCsv,
        private AnalyzeNacexShipments $analyzeShipments,
    ) {}

    public function handle(
        string $path,
        string $originalName,
        int $sizeInBytes,
    ): NacexCsvValidationResult {
        $prepared = $this->prepareCsv->handle($path, $originalName, $sizeInBytes);
        $analysisResults = $this->analyzeShipments->handle($prepared->shipments);

        return $prepared->inspection->withCandidateRows(
            candidateRows: $analysisResults,
            requestedRowCount: $prepared->requestedRowCount,
            duplicateConflicts: $prepared->duplicateConflicts,
        );
    }
}
