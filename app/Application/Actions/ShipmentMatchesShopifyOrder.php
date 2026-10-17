<?php

namespace App\Application\Actions;

use App\Application\Data\NacexShipmentCandidate;
use App\Domain\Matching\OrderDateCompatibility;
use App\Domain\Matching\RecipientNameCompatibility;
use App\Domain\Shipment\NacexShipmentNormalizer;
use DateTimeImmutable;

final readonly class ShipmentMatchesShopifyOrder
{
    public function __construct(
        private OrderDateCompatibility $dates,
        private RecipientNameCompatibility $names,
    ) {}

    public function handle(
        NacexShipmentCandidate $shipment,
        DateTimeImmutable $orderCreatedAt,
        ?string $shippingName,
        ?string $shippingPostalCode,
    ): bool {
        $row = $shipment->normalizedRow;

        return $this->dates->includes($row->sourceRow->registrationDate, $orderCreatedAt)
            && $shippingName !== null
            && $shippingPostalCode !== null
            && preg_match('/^[0-9]{1,5}$/D', $shippingPostalCode) === 1
            && NacexShipmentNormalizer::postalCode($shippingPostalCode) === $row->postalCode
            && $this->names->matches($row->sourceRow->reference, $shippingName);
    }
}
