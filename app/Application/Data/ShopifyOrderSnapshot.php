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
        /** @var list<string> */
        public array $trackingNumbers = [],
        public int $fulfillmentCount = 0,
        /** @var list<string> */
        public array $fulfillmentOrderStatuses = [],
        public ?string $adminUrl = null,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     createdAt: string,
     *     shippingName: string|null,
     *     fulfillmentStatus: string,
     *     adminUrl: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'shippingName' => $this->shippingName,
            'fulfillmentStatus' => $this->fulfillmentStatus,
            'adminUrl' => $this->adminUrl,
        ];
    }
}
