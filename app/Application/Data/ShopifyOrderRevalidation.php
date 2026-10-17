<?php

namespace App\Application\Data;

use DateTimeImmutable;

final readonly class ShopifyOrderRevalidation
{
    /**
     * @param  list<string>  $trackingNumbers
     * @param  list<ShopifyFulfillmentOrder>  $fulfillmentOrders
     */
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $cancelledAt,
        public string $fulfillmentStatus,
        public ?string $shippingName,
        public ?string $shippingPostalCode,
        public array $trackingNumbers,
        public int $fulfillmentCount,
        public array $fulfillmentOrders,
        public string $adminUrl,
    ) {}
}
