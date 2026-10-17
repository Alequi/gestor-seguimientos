<?php

namespace App\Application\Actions;

use App\Application\Contracts\ShopifyFulfillmentGateway;
use App\Application\Data\FulfillmentExecutionResult;
use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\SelectedShipment;
use App\Application\Data\ShopifyOrderRevalidation;
use App\Application\Exceptions\ShopifyCommunicationException;
use App\Domain\Fulfillment\FulfillmentExecutionStatus;
use App\Domain\Shipment\NacexShipmentIdentity;
use InvalidArgumentException;

final readonly class ExecuteSelectedNacexShipments
{
    public function __construct(
        private PrepareNacexCsv $prepareCsv,
        private FindShippingAddressMatchesForShipment $findMatches,
        private ShipmentMatchesShopifyOrder $shipmentMatchesOrder,
        private ShopifyFulfillmentGateway $shopify,
    ) {}

    /**
     * @param  list<SelectedShipment>  $selections
     * @return list<FulfillmentExecutionResult>
     */
    public function handle(
        string $path,
        string $originalName,
        int $sizeInBytes,
        array $selections,
        bool $notifyCustomer,
    ): array {
        $prepared = $this->prepareCsv->handle($path, $originalName, $sizeInBytes);
        $shipmentsByIdentity = [];

        foreach ($prepared->shipments as $shipment) {
            $shipmentsByIdentity[$this->shipmentIdentity($shipment)->key()] = $shipment;
        }

        $selectionCounts = [];
        foreach ($selections as $selection) {
            $key = $selection->identity()->key();
            $selectionCounts[$key] = ($selectionCounts[$key] ?? 0) + 1;
        }

        $results = [];
        foreach ($selections as $selection) {
            $key = $selection->identity()->key();

            if ($selectionCounts[$key] > 1) {
                $results[] = $this->result(
                    $selection,
                    FulfillmentExecutionStatus::Error,
                    'La expedición aparece más de una vez en la selección y no se ha procesado.',
                );

                continue;
            }

            $shipment = $shipmentsByIdentity[$key] ?? null;
            if ($shipment === null) {
                $results[] = $this->result(
                    $selection,
                    FulfillmentExecutionStatus::SkippedAfterRevalidation,
                    'La expedición ya no es procesable en el CSV enviado.',
                );

                continue;
            }

            $results[] = $this->executeOne($selection, $shipment, $notifyCustomer);
        }

        return $results;
    }

    private function executeOne(
        SelectedShipment $selection,
        NacexShipmentCandidate $shipment,
        bool $notifyCustomer,
    ): FulfillmentExecutionResult {
        try {
            if ($selection->assignment === 'AUTOMATIC' && ! $this->automaticSelectionRemainsUnique(
                $selection,
                $shipment,
            )) {
                return $this->result(
                    $selection,
                    FulfillmentExecutionStatus::SkippedAfterRevalidation,
                    'La coincidencia automática ha cambiado y requiere una nueva revisión.',
                );
            }

            $order = $this->shopify->revalidateOrder($selection->orderId);
            if ($order === null || $order->id !== $selection->orderId) {
                return $this->result(
                    $selection,
                    FulfillmentExecutionStatus::SkippedAfterRevalidation,
                    'El pedido seleccionado ya no está disponible.',
                );
            }

            if (! $this->shipmentMatchesOrder->handle(
                $shipment,
                $order->createdAt,
                $order->shippingName,
                $order->shippingPostalCode,
            )) {
                return $this->resultForOrder(
                    $selection,
                    $order,
                    FulfillmentExecutionStatus::SkippedAfterRevalidation,
                    'El pedido ya no coincide con los datos de la expedición.',
                );
            }

            $trackingNumber = $this->shipmentIdentity($shipment)->trackingNumber;

            if (in_array($trackingNumber, $order->trackingNumbers, true)) {
                return $this->resultForOrder(
                    $selection,
                    $order,
                    FulfillmentExecutionStatus::TrackingExists,
                    'El seguimiento ya existe en Shopify; no se ha creado otro cumplimiento.',
                );
            }

            if ($order->fulfillmentStatus === 'FULFILLED') {
                return $this->resultForOrder(
                    $selection,
                    $order,
                    FulfillmentExecutionStatus::AlreadyFulfilled,
                    'El pedido ya estaba preparado; no se ha creado otro cumplimiento.',
                );
            }

            if (! $this->canCreateFulfillment($order)) {
                return $this->resultForOrder(
                    $selection,
                    $order,
                    FulfillmentExecutionStatus::SkippedAfterRevalidation,
                    'El estado del pedido ha cambiado y no permite completarlo de forma segura.',
                );
            }

            $this->shopify->createFulfillment(
                fulfillmentOrderId: $order->fulfillmentOrders[0]->id,
                trackingNumber: $trackingNumber,
                trackingUrl: $shipment->trackingUrl,
                notifyCustomer: $notifyCustomer,
            );

            return $this->resultForOrder(
                $selection,
                $order,
                FulfillmentExecutionStatus::Completed,
                $notifyCustomer
                    ? 'Cumplimiento creado y notificación solicitada a Shopify.'
                    : 'Cumplimiento creado sin solicitar notificación al cliente.',
            );
        } catch (ShopifyCommunicationException) {
            return $this->result(
                $selection,
                FulfillmentExecutionStatus::Error,
                'No se pudo completar la operación en Shopify. Puedes reintentarlo desde esta sesión.',
            );
        } catch (InvalidArgumentException) {
            return $this->result(
                $selection,
                FulfillmentExecutionStatus::Error,
                'Los datos de fecha de la expedición no permiten revalidar el pedido.',
            );
        }
    }

    private function automaticSelectionRemainsUnique(
        SelectedShipment $selection,
        NacexShipmentCandidate $shipment,
    ): bool {
        $matches = array_values(array_filter(
            $this->findMatches->handle($shipment),
            static fn ($order): bool => $order->cancelledAt === null,
        ));

        return count($matches) === 1 && $matches[0]->id === $selection->orderId;
    }

    private function canCreateFulfillment(ShopifyOrderRevalidation $order): bool
    {
        if ($order->cancelledAt !== null
            || $order->fulfillmentStatus !== 'UNFULFILLED'
            || $order->fulfillmentCount !== 0
            || count($order->fulfillmentOrders) !== 1) {
            return false;
        }

        $fulfillmentOrder = $order->fulfillmentOrders[0];

        return $fulfillmentOrder->status === 'OPEN'
            && in_array('CREATE_FULFILLMENT', $fulfillmentOrder->supportedActions, true);
    }

    private function shipmentIdentity(NacexShipmentCandidate $shipment): NacexShipmentIdentity
    {
        return NacexShipmentIdentity::from(
            $shipment->normalizedRow->sourceRow->originAgency,
            $shipment->normalizedRow->sourceRow->trackingNumber,
        );
    }

    private function result(
        SelectedShipment $selection,
        FulfillmentExecutionStatus $status,
        string $message,
    ): FulfillmentExecutionResult {
        return new FulfillmentExecutionResult(
            originAgency: $selection->identity()->originAgency,
            trackingNumber: $selection->identity()->trackingNumber,
            orderId: $selection->orderId,
            orderName: null,
            adminUrl: null,
            status: $status,
            message: $message,
        );
    }

    private function resultForOrder(
        SelectedShipment $selection,
        ShopifyOrderRevalidation $order,
        FulfillmentExecutionStatus $status,
        string $message,
    ): FulfillmentExecutionResult {
        return new FulfillmentExecutionResult(
            originAgency: $selection->identity()->originAgency,
            trackingNumber: $selection->identity()->trackingNumber,
            orderId: $selection->orderId,
            orderName: $order->name,
            adminUrl: $order->adminUrl,
            status: $status,
            message: $message,
        );
    }
}
