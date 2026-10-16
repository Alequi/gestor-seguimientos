<?php

namespace App\Application\Data;

final readonly class NacexCsvValidationResult
{
    /**
     * @param  list<string>  $headers
     * @param  list<NacexCsvRow>  $rows
     * @param  list<ShipmentAnalysisResult>  $candidateRows
     * @param  list<NacexCsvDuplicateConflict>  $duplicateConflicts
     */
    public function __construct(
        public string $fileName,
        public int $sizeInBytes,
        public int $rowCount,
        public string $encoding,
        public array $headers,
        public array $rows,
        public array $candidateRows = [],
        public int $requestedRowCount = 0,
        public array $duplicateConflicts = [],
    ) {}

    /**
     * @param  list<ShipmentAnalysisResult>  $candidateRows
     */
    public function withCandidateRows(
        array $candidateRows,
        int $requestedRowCount,
        array $duplicateConflicts,
    ): self {
        return new self(
            fileName: $this->fileName,
            sizeInBytes: $this->sizeInBytes,
            rowCount: $this->rowCount,
            encoding: $this->encoding,
            headers: $this->headers,
            rows: $this->rows,
            candidateRows: $candidateRows,
            requestedRowCount: $requestedRowCount,
            duplicateConflicts: $duplicateConflicts,
        );
    }

    /**
     * @return array{
     *     fileName: string,
     *     sizeInBytes: int,
     *     rowCount: int,
     *     requestedRowCount: int,
     *     processableRowCount: int,
     *     duplicateConflictCount: int,
     *     encoding: string,
     *     headers: list<string>,
     *     rows: list<array{
     *         originAgency: string,
     *         trackingNumber: string,
     *         registrationDate: string,
     *         reference: string,
     *         normalizedReference: string,
     *         postalCode: string,
     *         status: string,
     *         trackingUrl: string,
     *         matchStatus: string,
     *         shopifyOrder: array<string, mixed>|null,
     *         candidateOrders: list<array<string, mixed>>,
     *         errorMessage: string|null
     *     }>,
     *     duplicateConflicts: list<array{
     *         originAgency: string,
     *         trackingNumber: string,
     *         rows: non-empty-list<array{
     *             originAgency: string,
     *             trackingNumber: string,
     *             registrationDate: string,
     *             reference: string,
     *             postalCode: string,
     *             status: string
     *         }>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'fileName' => $this->fileName,
            'sizeInBytes' => $this->sizeInBytes,
            'rowCount' => $this->rowCount,
            'requestedRowCount' => $this->requestedRowCount,
            'processableRowCount' => count($this->candidateRows),
            'duplicateConflictCount' => count($this->duplicateConflicts),
            'encoding' => $this->encoding,
            'headers' => $this->headers,
            'rows' => array_map(
                static fn (ShipmentAnalysisResult $row): array => $row->toArray(),
                $this->candidateRows,
            ),
            'duplicateConflicts' => array_map(
                static fn (NacexCsvDuplicateConflict $conflict): array => $conflict->toArray(),
                $this->duplicateConflicts,
            ),
        ];
    }
}
