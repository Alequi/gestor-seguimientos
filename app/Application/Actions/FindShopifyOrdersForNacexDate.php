<?php

namespace App\Application\Actions;

use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateCompatibility;

final readonly class FindShopifyOrdersForNacexDate
{
    public function __construct(
        private OrderDateCompatibility $dates,
        private ShopifyOrderReader $orders,
    ) {}

    /**
     * @return list<ShopifyOrderSnapshot>
     */
    public function handle(string $registrationDate): array
    {
        return $this->orders->findCreatedIn($this->dates->window($registrationDate));
    }
}
