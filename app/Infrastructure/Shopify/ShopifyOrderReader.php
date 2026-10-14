<?php

namespace App\Infrastructure\Shopify;

use App\Application\Contracts\ShopifyOrderReader as ShopifyOrderReaderContract;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateWindow;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final readonly class ShopifyOrderReader implements ShopifyOrderReaderContract
{
    private const PAGE_SIZE = 100;

    private const QUERY = <<<'GRAPHQL'
        query SearchOrdersByCreationDate($first: Int!, $after: String, $search: String!) {
          orders(first: $first, after: $after, query: $search, sortKey: CREATED_AT) {
            pageInfo { hasNextPage endCursor }
            nodes {
              id
              name
              createdAt
              cancelledAt
              displayFulfillmentStatus
              shippingAddress { name firstName lastName zip }
            }
          }
        }
        GRAPHQL;

    public function __construct(private ShopifyAdminGraphqlClient $client) {}

    public function findCreatedIn(OrderDateWindow $window): array
    {
        $utc = new DateTimeZone('UTC');
        $search = sprintf(
            'created_at:>=%s created_at:<%s',
            $window->startInclusive->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            $window->endExclusive->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        );
        $cursor = null;
        $seenCursors = [];
        $orders = [];

        do {
            $data = $this->client->execute(self::QUERY, [
                'first' => self::PAGE_SIZE,
                'after' => $cursor,
                'search' => $search,
            ]);

            $connection = $data['orders'] ?? null;
            if (! is_array($connection)
                || ! isset($connection['nodes'], $connection['pageInfo'])
                || ! is_array($connection['nodes'])
                || ! array_is_list($connection['nodes'])
                || ! is_array($connection['pageInfo'])) {
                throw new ShopifyCommunicationException('Shopify no devolvió una lista de pedidos válida.');
            }

            foreach ($connection['nodes'] as $node) {
                $order = $this->adaptOrder($node);

                if (! $window->includes($order->createdAt)) {
                    continue;
                }

                if (isset($orders[$order->id])) {
                    throw new ShopifyCommunicationException('Shopify devolvió pedidos duplicados.');
                }

                $orders[$order->id] = $order;
            }

            $pageInfo = $connection['pageInfo'];
            $hasNextPage = $pageInfo['hasNextPage'] ?? null;
            $endCursor = $pageInfo['endCursor'] ?? null;

            if (! is_bool($hasNextPage)
                || ($hasNextPage && (! is_string($endCursor)
                    || $endCursor === ''
                    || isset($seenCursors[$endCursor])))) {
                throw new ShopifyCommunicationException('Shopify no devolvió una paginación válida.');
            }

            if ($hasNextPage) {
                $seenCursors[$endCursor] = true;
            }

            $cursor = $hasNextPage ? $endCursor : null;
        } while ($cursor !== null);

        return array_values($orders);
    }

    private function adaptOrder(mixed $node): ShopifyOrderSnapshot
    {
        if (! is_array($node)
            || ! is_string($node['id'] ?? null)
            || $node['id'] === ''
            || ! is_string($node['name'] ?? null)
            || ! is_string($node['createdAt'] ?? null)
            || ! is_string($node['displayFulfillmentStatus'] ?? null)
            || (isset($node['cancelledAt']) && ! is_string($node['cancelledAt']))) {
            throw new ShopifyCommunicationException('Shopify no devolvió un pedido válido.');
        }

        $shipping = $node['shippingAddress'] ?? null;

        if ($shipping !== null && (! is_array($shipping)
            || (isset($shipping['name']) && ! is_string($shipping['name']))
            || (isset($shipping['firstName']) && ! is_string($shipping['firstName']))
            || (isset($shipping['lastName']) && ! is_string($shipping['lastName']))
            || (isset($shipping['zip']) && ! is_string($shipping['zip'])))) {
            throw new ShopifyCommunicationException('Shopify no devolvió una dirección de envío válida.');
        }

        $shippingName = $shipping['name'] ?? null;
        if ($shippingName === null || trim($shippingName) === '') {
            $firstName = trim($shipping['firstName'] ?? '');
            $lastName = trim($shipping['lastName'] ?? '');
            $shippingName = $firstName !== '' && $lastName !== ''
                ? $firstName.' '.$lastName
                : null;
        }

        try {
            $createdAt = new DateTimeImmutable($node['createdAt']);
            $cancelledAt = isset($node['cancelledAt'])
                ? new DateTimeImmutable($node['cancelledAt'])
                : null;
        } catch (Throwable) {
            throw new ShopifyCommunicationException('Shopify no devolvió una fecha de pedido válida.');
        }

        return new ShopifyOrderSnapshot(
            id: $node['id'],
            name: $node['name'],
            createdAt: $createdAt,
            cancelledAt: $cancelledAt,
            fulfillmentStatus: $node['displayFulfillmentStatus'],
            shippingName: $shippingName,
            shippingPostalCode: $shipping['zip'] ?? null,
        );
    }
}
