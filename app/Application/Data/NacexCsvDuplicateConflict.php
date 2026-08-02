<?php

namespace App\Application\Data;

final readonly class NacexCsvDuplicateConflict
{
    /**
     * @param  non-empty-list<NacexCsvRow>  $rows
     */
    public function __construct(
        public string $originAgency,
        public string $trackingNumber,
        public array $rows,
    ) {}

    /**
     * @return array{
     *     originAgency: string,
     *     trackingNumber: string,
     *     rows: non-empty-list<array{
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
            'originAgency' => $this->originAgency,
            'trackingNumber' => $this->trackingNumber,
            'rows' => array_map(
                static fn (NacexCsvRow $row): array => $row->toArray(),
                $this->rows,
            ),
        ];
    }
}
