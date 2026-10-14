<?php

namespace App\Application\Actions;

use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateCompatibility;
use App\Domain\Matching\RecipientNameCompatibility;
use App\Domain\Shipment\NacexShipmentNormalizer;

final readonly class FindShippingAddressMatchesForShipment
{
    public function __construct(
        private FindShopifyOrdersForNacexDate $orders,
        private OrderDateCompatibility $dates,
        private RecipientNameCompatibility $names,
    ) {}

    /**
     * @return list<ShopifyOrderSnapshot>
     */
    public function handle(NacexShipmentCandidate $shipment): array
    {
        $row = $shipment->normalizedRow;
        $matches = [];

        foreach ($this->orders->handle($row->sourceRow->registrationDate) as $order) {
            if (! $this->dates->includes($row->sourceRow->registrationDate, $order->createdAt)
                || $order->shippingName === null
                || $order->shippingPostalCode === null
                || preg_match('/^[0-9]{1,5}$/D', $order->shippingPostalCode) !== 1
                || NacexShipmentNormalizer::postalCode($order->shippingPostalCode) !== $row->postalCode
                || ! $this->names->matches($row->sourceRow->reference, $order->shippingName)) {
                continue;
            }

            $matches[] = $order;
        }

        return $matches;
    }
}
