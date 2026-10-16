<?php

namespace App\Application\Data;

use App\Domain\Matching\ShipmentMatchStatus;

final readonly class ShipmentAnalysisResult
{
    /**
     * @param  list<ShopifyOrderSnapshot>  $candidateOrders
     */
    public function __construct(
        public NacexShipmentCandidate $shipment,
        public ShipmentMatchStatus $status,
        public ?ShopifyOrderSnapshot $order = null,
        public array $candidateOrders = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->shipment->toArray(),
            'matchStatus' => $this->status->value,
            'shopifyOrder' => $this->order?->toArray(),
            'candidateOrders' => array_map(
                static fn (ShopifyOrderSnapshot $order): array => $order->toArray(),
                $this->candidateOrders,
            ),
            'errorMessage' => $this->errorMessage,
        ];
    }
}
