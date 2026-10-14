<?php

namespace App\Application\Data;

use DateTimeImmutable;

final readonly class ShopifyOrderSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $cancelledAt,
        public string $fulfillmentStatus,
        public ?string $shippingName,
        public ?string $shippingPostalCode,
    ) {}
}
