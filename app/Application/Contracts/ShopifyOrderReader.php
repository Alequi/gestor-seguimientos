<?php

namespace App\Application\Contracts;

use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateWindow;

interface ShopifyOrderReader
{
    /**
     * @return list<ShopifyOrderSnapshot>
     */
    public function findCreatedIn(OrderDateWindow $window): array;
}
