<?php

namespace App\Application\Data;

use App\Domain\Fulfillment\FulfillmentExecutionStatus;

final readonly class FulfillmentExecutionResult
{
    public function __construct(
        public string $originAgency,
        public string $trackingNumber,
        public string $orderId,
        public ?string $orderName,
        public ?string $adminUrl,
        public FulfillmentExecutionStatus $status,
        public string $message,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'originAgency' => $this->originAgency,
            'trackingNumber' => $this->trackingNumber,
            'orderId' => $this->orderId,
            'orderName' => $this->orderName,
            'adminUrl' => $this->adminUrl,
            'status' => $this->status->value,
            'message' => $this->message,
        ];
    }
}
