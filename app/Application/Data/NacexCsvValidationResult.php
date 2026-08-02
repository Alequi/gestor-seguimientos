<?php

namespace App\Application\Data;

final readonly class NacexCsvValidationResult
{
    /**
     * @param  list<string>  $headers
     * @param  list<NacexCsvRow>  $rows
     */
    public function __construct(
        public string $fileName,
        public int $sizeInBytes,
        public int $rowCount,
        public string $encoding,
        public array $headers,
        public array $rows,
    ) {}

    /**
     * @return array{
     *     fileName: string,
     *     sizeInBytes: int,
     *     rowCount: int,
     *     encoding: string,
     *     headers: list<string>,
     *     rows: list<array{
     *         originAgency: string,
     *         trackingNumber: string,
     *         registrationDate: string,
     *         reference: string,
     *         postalCode: string,
     *         status: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'fileName' => $this->fileName,
            'sizeInBytes' => $this->sizeInBytes,
            'rowCount' => $this->rowCount,
            'encoding' => $this->encoding,
            'headers' => $this->headers,
            'rows' => array_map(
                static fn (NacexCsvRow $row): array => $row->toArray(),
                $this->rows,
            ),
        ];
    }
}
