<?php

namespace App\Application\Actions;

use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\ShopifyOrderSnapshot;

final readonly class FindShippingAddressMatchesForShipment
{
    public function __construct(
        private FindShopifyOrdersForNacexDate $orders,
        private ShipmentMatchesShopifyOrder $shipmentMatchesOrder,
    ) {}

    /**
     * @return list<ShopifyOrderSnapshot>
     */
    public function handle(NacexShipmentCandidate $shipment): array
    {
        $row = $shipment->normalizedRow;
        $matches = [];

        foreach ($this->orders->handle($row->sourceRow->registrationDate) as $order) {
            if (! $this->shipmentMatchesOrder->handle(
                $shipment,
                $order->createdAt,
                $order->shippingName,
                $order->shippingPostalCode,
            )) {
                continue;
            }

            $matches[] = $order;
        }

        return $matches;
    }
}
