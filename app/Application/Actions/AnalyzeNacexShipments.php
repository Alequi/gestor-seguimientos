<?php

namespace App\Application\Actions;

use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\ShipmentAnalysisResult;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Application\Exceptions\ShopifyCommunicationException;
use App\Domain\Matching\ShipmentMatchStatus;
use App\Domain\Shipment\NacexShipmentIdentity;
use InvalidArgumentException;

final readonly class AnalyzeNacexShipments
{
    public function __construct(
        private FindShippingAddressMatchesForShipment $matches,
    ) {}

    /**
     * @param  list<NacexShipmentCandidate>  $shipments
     * @return list<ShipmentAnalysisResult>
     */
    public function handle(array $shipments): array
    {
        return array_map(
            fn (NacexShipmentCandidate $shipment): ShipmentAnalysisResult => $this->analyze($shipment),
            $shipments,
        );
    }

    private function analyze(NacexShipmentCandidate $shipment): ShipmentAnalysisResult
    {
        try {
            $matches = array_values(array_filter(
                $this->matches->handle($shipment),
                static fn (ShopifyOrderSnapshot $order): bool => $order->cancelledAt === null,
            ));
        } catch (InvalidArgumentException) {
            return new ShipmentAnalysisResult(
                shipment: $shipment,
                status: ShipmentMatchStatus::Error,
                errorMessage: 'La fecha de alta de NACEX no es válida.',
            );
        } catch (ShopifyCommunicationException) {
            return new ShipmentAnalysisResult(
                shipment: $shipment,
                status: ShipmentMatchStatus::Error,
                errorMessage: 'No se pudo consultar Shopify para esta expedición.',
            );
        }

        if ($matches === []) {
            return new ShipmentAnalysisResult(
                shipment: $shipment,
                status: ShipmentMatchStatus::NoMatch,
            );
        }

        if (count($matches) > 1) {
            return new ShipmentAnalysisResult(
                shipment: $shipment,
                status: ShipmentMatchStatus::Ambiguous,
                candidateOrders: $matches,
            );
        }

        $order = $matches[0];
        $trackingNumber = NacexShipmentIdentity::from(
            $shipment->normalizedRow->sourceRow->originAgency,
            $shipment->normalizedRow->sourceRow->trackingNumber,
        )->trackingNumber;

        if (in_array($trackingNumber, $order->trackingNumbers, true)) {
            return $this->forOrder($shipment, ShipmentMatchStatus::TrackingExists, $order);
        }

        if ($order->fulfillmentStatus === 'FULFILLED') {
            return $this->forOrder($shipment, ShipmentMatchStatus::AlreadyFulfilled, $order);
        }

        if ($order->fulfillmentStatus === 'PARTIALLY_FULFILLED'
            || count($order->fulfillmentOrderStatuses) > 1) {
            return $this->forOrder($shipment, ShipmentMatchStatus::PartialFulfillment, $order);
        }

        if ($order->fulfillmentCount > 0) {
            return $this->forOrder($shipment, ShipmentMatchStatus::FulfillmentExists, $order);
        }

        if ($order->fulfillmentStatus === 'UNFULFILLED'
            && $order->fulfillmentOrderStatuses === ['OPEN']) {
            return $this->forOrder($shipment, ShipmentMatchStatus::MatchedSafe, $order);
        }

        return new ShipmentAnalysisResult(
            shipment: $shipment,
            status: ShipmentMatchStatus::Error,
            order: $order,
            errorMessage: 'Shopify no devolvió un estado que permita procesar el pedido de forma segura.',
        );
    }

    private function forOrder(
        NacexShipmentCandidate $shipment,
        ShipmentMatchStatus $status,
        ShopifyOrderSnapshot $order,
    ): ShipmentAnalysisResult {
        return new ShipmentAnalysisResult(
            shipment: $shipment,
            status: $status,
            order: $order,
        );
    }
}
