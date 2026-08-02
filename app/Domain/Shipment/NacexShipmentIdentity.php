<?php

namespace App\Domain\Shipment;

final readonly class NacexShipmentIdentity
{
    private function __construct(
        public string $originAgency,
        public string $trackingNumber,
    ) {}

    public static function from(string $originAgency, string $trackingNumber): self
    {
        return new self(
            originAgency: str_pad(self::trimExteriorSpacing($originAgency), 4, '0', STR_PAD_LEFT),
            trackingNumber: self::trimExteriorSpacing($trackingNumber),
        );
    }

    public function key(): string
    {
        return strlen($this->originAgency).':'.$this->originAgency.$this->trackingNumber;
    }

    private static function trimExteriorSpacing(string $value): string
    {
        return preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $value) ?? trim($value);
    }
}
