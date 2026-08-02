<?php

namespace App\Application\Data;

final readonly class NacexCsvRow
{
    public function __construct(
        public string $originAgency,
        public string $trackingNumber,
        public string $registrationDate,
        public string $reference,
        public string $postalCode,
        public string $status,
    ) {}

    /**
     * @return array{
     *     originAgency: string,
     *     trackingNumber: string,
     *     registrationDate: string,
     *     reference: string,
     *     postalCode: string,
     *     status: string
     * }
     */
    public function toArray(): array
    {
        return [
            'originAgency' => $this->originAgency,
            'trackingNumber' => $this->trackingNumber,
            'registrationDate' => $this->registrationDate,
            'reference' => $this->reference,
            'postalCode' => $this->postalCode,
            'status' => $this->status,
        ];
    }
}
