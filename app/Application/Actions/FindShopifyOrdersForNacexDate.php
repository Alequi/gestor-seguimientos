<?php

namespace App\Application\Actions;

use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateCompatibility;

final class FindShopifyOrdersForNacexDate
{
    /** @var array<string, list<ShopifyOrderSnapshot>> */
    private array $ordersByDate = [];

    public function __construct(
        private OrderDateCompatibility $dates,
        private ShopifyOrderReader $orders,
    ) {}

    /**
     * @return list<ShopifyOrderSnapshot>
     */
    public function handle(string $registrationDate): array
    {
        if (! array_key_exists($registrationDate, $this->ordersByDate)) {
            $this->ordersByDate[$registrationDate] = $this->orders->findCreatedIn(
                $this->dates->window($registrationDate),
            );
        }

        return $this->ordersByDate[$registrationDate];
    }
}
