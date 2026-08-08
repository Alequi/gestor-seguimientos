<?php

namespace App\Domain\Shipment;

final readonly class NacexTrackingUrlBuilder
{
    public function __construct(
        private string $baseUrl,
        private string $status,
        private string $international,
        private string $external,
    ) {}

    public function build(string $originAgency, string $trackingNumber): string
    {
        return sprintf(
            '%s?agencia_origen=%s&numero_albaran=%s&estado=%s&internacional=%s&externo=%s',
            $this->baseUrl,
            rawurlencode($originAgency),
            rawurlencode($trackingNumber),
            rawurlencode($this->status),
            rawurlencode($this->international),
            rawurlencode($this->external),
        );
    }
}
