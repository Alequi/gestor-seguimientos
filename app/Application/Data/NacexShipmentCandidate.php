<?php

namespace App\Application\Data;

final readonly class NacexShipmentCandidate
{
    public function __construct(
        public NacexCsvCandidate $normalizedRow,
        public string $trackingUrl,
    ) {}

    /**
     * @return array{
     *     originAgency: string,
     *     trackingNumber: string,
     *     registrationDate: string,
     *     reference: string,
     *     normalizedReference: string,
     *     postalCode: string,
     *     status: string,
     *     trackingUrl: string
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->normalizedRow->toArray(),
            'trackingUrl' => $this->trackingUrl,
        ];
    }
}
