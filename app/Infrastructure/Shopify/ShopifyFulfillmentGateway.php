<?php

namespace App\Infrastructure\Shopify;

use App\Application\Contracts\ShopifyFulfillmentGateway as ShopifyFulfillmentGatewayContract;
use App\Application\Data\ShopifyFulfillmentOrder;
use App\Application\Data\ShopifyOrderRevalidation;
use App\Application\Exceptions\ShopifyCommunicationException;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class ShopifyFulfillmentGateway implements ShopifyFulfillmentGatewayContract
{
    private const REVALIDATE_QUERY = <<<'GRAPHQL'
        query RevalidateOrder($id: ID!) {
          order(id: $id) {
            id
            name
            createdAt
            cancelledAt
            displayFulfillmentStatus
            shippingAddress { name firstName lastName zip }
            fulfillmentsCount { count }
            fulfillments(first: 250) {
              trackingInfo(first: 250) { number }
            }
            fulfillmentOrders(first: 2) {
              nodes { id status supportedActions { action } }
              pageInfo { hasNextPage }
            }
          }
        }
        GRAPHQL;

    private const CREATE_FULFILLMENT_MUTATION = <<<'GRAPHQL'
        mutation CreateNacexFulfillment($fulfillment: FulfillmentInput!) {
          fulfillmentCreate(fulfillment: $fulfillment) {
            fulfillment { id status }
            userErrors { field message }
          }
        }
        GRAPHQL;

    public function __construct(
        private ShopifyAdminGraphqlClient $client,
        private ShopifyStoreEndpoint $endpoint,
    ) {}

    public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
    {
        $data = $this->client->execute(self::REVALIDATE_QUERY, ['id' => $orderId]);
        $order = $data['order'] ?? null;

        if ($order === null) {
            return null;
        }

        if (! is_array($order)) {
            throw new ShopifyCommunicationException('Shopify no devolvió un pedido válido al revalidarlo.');
        }

        return $this->adaptOrder($order);
    }

    public function createFulfillment(
        string $fulfillmentOrderId,
        string $trackingNumber,
        string $trackingUrl,
        bool $notifyCustomer,
    ): void {
        $data = $this->client->execute(self::CREATE_FULFILLMENT_MUTATION, [
            'fulfillment' => [
                'notifyCustomer' => $notifyCustomer,
                'trackingInfo' => [
                    'company' => 'NACEX',
                    'number' => $trackingNumber,
                    'url' => $trackingUrl,
                ],
                'lineItemsByFulfillmentOrder' => [[
                    'fulfillmentOrderId' => $fulfillmentOrderId,
                ]],
            ],
        ]);

        $payload = $data['fulfillmentCreate'] ?? null;
        if (! is_array($payload)
            || ! is_array($payload['userErrors'] ?? null)
            || ! array_is_list($payload['userErrors'])) {
            throw new ShopifyCommunicationException('Shopify no devolvió el resultado del cumplimiento correctamente.');
        }

        if ($payload['userErrors'] !== []) {
            throw new ShopifyCommunicationException('Shopify rechazó la creación del cumplimiento.');
        }

        $fulfillment = $payload['fulfillment'] ?? null;
        if (! is_array($fulfillment)
            || ! is_string($fulfillment['id'] ?? null)
            || ! is_string($fulfillment['status'] ?? null)) {
            throw new ShopifyCommunicationException('Shopify no confirmó la creación del cumplimiento.');
        }
    }

    private function adaptOrder(array $order): ShopifyOrderRevalidation
    {
        if (! is_string($order['id'] ?? null)
            || ! is_string($order['name'] ?? null)
            || ! is_string($order['createdAt'] ?? null)
            || ! is_string($order['displayFulfillmentStatus'] ?? null)
            || (isset($order['cancelledAt']) && ! is_string($order['cancelledAt']))) {
            throw new ShopifyCommunicationException('Shopify no devolvió un pedido válido al revalidarlo.');
        }

        $shipping = $this->adaptShippingAddress($order['shippingAddress'] ?? null);
        $trackingNumbers = $this->adaptTrackingNumbers($order);
        $fulfillmentCount = $order['fulfillmentsCount']['count'] ?? null;

        if (! is_int($fulfillmentCount)
            || $fulfillmentCount < 0
            || $fulfillmentCount !== count($order['fulfillments'] ?? [])) {
            throw new ShopifyCommunicationException('Shopify no devolvió todos los cumplimientos al revalidar el pedido.');
        }

        $fulfillmentOrders = $this->adaptFulfillmentOrders($order['fulfillmentOrders'] ?? null);

        try {
            $createdAt = new DateTimeImmutable($order['createdAt']);
            $cancelledAt = isset($order['cancelledAt'])
                ? new DateTimeImmutable($order['cancelledAt'])
                : null;
            $adminUrl = $this->endpoint->orderAdminUrl($order['id']);
        } catch (InvalidArgumentException) {
            throw new ShopifyCommunicationException('Shopify no devolvió un identificador de pedido válido.');
        } catch (Throwable) {
            throw new ShopifyCommunicationException('Shopify no devolvió una fecha de pedido válida.');
        }

        return new ShopifyOrderRevalidation(
            id: $order['id'],
            name: $order['name'],
            createdAt: $createdAt,
            cancelledAt: $cancelledAt,
            fulfillmentStatus: $order['displayFulfillmentStatus'],
            shippingName: $shipping['name'],
            shippingPostalCode: $shipping['zip'],
            trackingNumbers: $trackingNumbers,
            fulfillmentCount: $fulfillmentCount,
            fulfillmentOrders: $fulfillmentOrders,
            adminUrl: $adminUrl,
        );
    }

    /** @return array{name: string|null, zip: string|null} */
    private function adaptShippingAddress(mixed $shipping): array
    {
        if ($shipping === null) {
            return ['name' => null, 'zip' => null];
        }

        if (! is_array($shipping)
            || (isset($shipping['name']) && ! is_string($shipping['name']))
            || (isset($shipping['firstName']) && ! is_string($shipping['firstName']))
            || (isset($shipping['lastName']) && ! is_string($shipping['lastName']))
            || (isset($shipping['zip']) && ! is_string($shipping['zip']))) {
            throw new ShopifyCommunicationException('Shopify no devolvió una dirección de envío válida.');
        }

        $name = $shipping['name'] ?? null;
        if ($name === null || trim($name) === '') {
            $firstName = trim($shipping['firstName'] ?? '');
            $lastName = trim($shipping['lastName'] ?? '');
            $name = $firstName !== '' && $lastName !== '' ? $firstName.' '.$lastName : null;
        }

        return ['name' => $name, 'zip' => $shipping['zip'] ?? null];
    }

    /** @return list<string> */
    private function adaptTrackingNumbers(array $order): array
    {
        $fulfillments = $order['fulfillments'] ?? null;
        if (! is_array($fulfillments) || ! array_is_list($fulfillments)) {
            throw new ShopifyCommunicationException('Shopify no devolvió los seguimientos al revalidar el pedido.');
        }

        $numbers = [];
        foreach ($fulfillments as $fulfillment) {
            if (! is_array($fulfillment)
                || ! is_array($fulfillment['trackingInfo'] ?? null)
                || ! array_is_list($fulfillment['trackingInfo'])) {
                throw new ShopifyCommunicationException('Shopify no devolvió los seguimientos al revalidar el pedido.');
            }

            foreach ($fulfillment['trackingInfo'] as $tracking) {
                if (! is_array($tracking)
                    || (isset($tracking['number']) && ! is_string($tracking['number']))) {
                    throw new ShopifyCommunicationException('Shopify no devolvió un seguimiento válido.');
                }

                if (isset($tracking['number'])) {
                    $numbers[] = $tracking['number'];
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    /** @return list<ShopifyFulfillmentOrder> */
    private function adaptFulfillmentOrders(mixed $connection): array
    {
        if (! is_array($connection)
            || ! is_array($connection['nodes'] ?? null)
            || ! array_is_list($connection['nodes'])
            || ! is_bool($connection['pageInfo']['hasNextPage'] ?? null)) {
            throw new ShopifyCommunicationException('Shopify no devolvió las órdenes de cumplimiento correctamente.');
        }

        if ($connection['pageInfo']['hasNextPage']) {
            return [];
        }

        $orders = [];
        foreach ($connection['nodes'] as $node) {
            if (! is_array($node)
                || ! is_string($node['id'] ?? null)
                || ! is_string($node['status'] ?? null)
                || ! is_array($node['supportedActions'] ?? null)
                || ! array_is_list($node['supportedActions'])) {
                throw new ShopifyCommunicationException('Shopify no devolvió una orden de cumplimiento válida.');
            }

            $actions = [];
            foreach ($node['supportedActions'] as $supportedAction) {
                if (! is_array($supportedAction) || ! is_string($supportedAction['action'] ?? null)) {
                    throw new ShopifyCommunicationException('Shopify no devolvió acciones de cumplimiento válidas.');
                }

                $actions[] = $supportedAction['action'];
            }

            $orders[] = new ShopifyFulfillmentOrder($node['id'], $node['status'], $actions);
        }

        return $orders;
    }
}
