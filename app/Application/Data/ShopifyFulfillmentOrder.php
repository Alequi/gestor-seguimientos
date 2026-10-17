<?php

namespace App\Application\Data;

final readonly class ShopifyFulfillmentOrder
{
    /** @param list<string> $supportedActions */
    public function __construct(
        public string $id,
        public string $status,
        public array $supportedActions,
    ) {}
}
