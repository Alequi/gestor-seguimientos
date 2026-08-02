<?php

namespace App\Domain\Shipment;

final readonly class NacexShipmentStatus
{
    private const string REQUESTED = 'solicitada';

    private function __construct(
        private string $normalizedValue,
    ) {}

    public static function from(string $value): self
    {
        $normalizedSpacing = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value;

        return new self(
            mb_strtolower(trim($normalizedSpacing), 'UTF-8'),
        );
    }

    public function isRequested(): bool
    {
        return $this->normalizedValue === self::REQUESTED;
    }
}
