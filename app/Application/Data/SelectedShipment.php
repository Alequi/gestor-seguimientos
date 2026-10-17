<?php

namespace App\Application\Data;

use App\Domain\Shipment\NacexShipmentIdentity;

final readonly class SelectedShipment
{
    public function __construct(
        public string $originAgency,
        public string $trackingNumber,
        public string $orderId,
        public string $assignment,
    ) {}

    public function identity(): NacexShipmentIdentity
    {
        return NacexShipmentIdentity::from($this->originAgency, $this->trackingNumber);
    }
}
