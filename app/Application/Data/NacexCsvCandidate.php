<?php

namespace App\Application\Data;

final readonly class NacexCsvCandidate
{
    public function __construct(
        public NacexCsvRow $sourceRow,
        public string $originAgency,
        public string $normalizedReference,
        public string $postalCode,
    ) {}

    /**
     * @return array{
     *     originAgency: string,
     *     trackingNumber: string,
     *     registrationDate: string,
     *     reference: string,
     *     normalizedReference: string,
     *     postalCode: string,
     *     status: string
     * }
     */
    public function toArray(): array
    {
        return [
            'originAgency' => $this->originAgency,
            'trackingNumber' => $this->sourceRow->trackingNumber,
            'registrationDate' => $this->sourceRow->registrationDate,
            'reference' => $this->sourceRow->reference,
            'normalizedReference' => $this->normalizedReference,
            'postalCode' => $this->postalCode,
            'status' => $this->sourceRow->status,
        ];
    }
}
