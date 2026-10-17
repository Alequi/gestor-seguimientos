<?php

namespace App\Application\Data;

final readonly class ExecutionReportRow
{
    public function __construct(
        public string $reference,
        public string $originAgency,
        public string $trackingNumber,
        public string $postalCode,
        public string $registrationDate,
        public string $nacexStatus,
        public string $trackingUrl,
        public string $matchStatus,
        public string $shopifyOrderName,
        public string $shopifyCustomer,
        public string $shopifyOrderDate,
        public string $previousOrderStatus,
        public string $fulfillmentResult,
        public string $notificationRequested,
        public string $processedAt,
        public string $errorMessage,
    ) {}

    /** @return list<string> */
    public function values(): array
    {
        return [
            $this->reference,
            $this->originAgency,
            $this->trackingNumber,
            $this->postalCode,
            $this->registrationDate,
            $this->nacexStatus,
            $this->trackingUrl,
            $this->matchStatus,
            $this->shopifyOrderName,
            $this->shopifyCustomer,
            $this->shopifyOrderDate,
            $this->previousOrderStatus,
            $this->fulfillmentResult,
            $this->notificationRequested,
            $this->processedAt,
            $this->errorMessage,
        ];
    }
}
