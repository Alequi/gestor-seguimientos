<?php

namespace App\Application\Contracts;

use App\Application\Data\ShopifyOrderRevalidation;

interface ShopifyFulfillmentGateway
{
    public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation;

    public function createFulfillment(
        string $fulfillmentOrderId,
        string $trackingNumber,
        string $trackingUrl,
        bool $notifyCustomer,
    ): void;
}
