<?php

namespace Tests\Feature;

use App\Application\Contracts\ShopifyFulfillmentGateway;
use App\Application\Exceptions\ShopifyCommunicationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyFulfillmentGatewayTest extends TestCase
{
    private const TOKEN_URL = 'https://example-store.myshopify.com/admin/oauth/access_token';

    private const GRAPHQL_URL = 'https://example-store.myshopify.com/admin/api/2026-10/graphql.json';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('shopify.shop', 'example-store');
        config()->set('shopify.client_id', 'example-client');
        config()->set('shopify.client_secret', 'test-secret');
        Http::preventStrayRequests();
    }

    public function test_it_revalidates_the_order_and_creates_an_all_remaining_items_fulfillment(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399]),
            self::GRAPHQL_URL => Http::sequence()
                ->push(['data' => ['order' => $this->order()]], 200)
                ->push(['data' => ['fulfillmentCreate' => [
                    'fulfillment' => ['id' => 'gid://shopify/Fulfillment/20', 'status' => 'SUCCESS'],
                    'userErrors' => [],
                ]]], 200),
        ]);

        $gateway = app(ShopifyFulfillmentGateway::class);
        $order = $gateway->revalidateOrder('gid://shopify/Order/1');

        $this->assertNotNull($order);
        $this->assertSame('gid://shopify/FulfillmentOrder/10', $order->fulfillmentOrders[0]->id);
        $this->assertSame(['CREATE_FULFILLMENT'], $order->fulfillmentOrders[0]->supportedActions);
        $this->assertSame('https://example-store.myshopify.com/admin/orders/1', $order->adminUrl);

        $gateway->createFulfillment(
            fulfillmentOrderId: 'gid://shopify/FulfillmentOrder/10',
            trackingNumber: '0014910300',
            trackingUrl: 'https://www.nacex.es/seguimientoDetalle.do?test=1',
            notifyCustomer: true,
        );

        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && str_contains($request['query'], 'query RevalidateOrder')
            && $request['variables']['id'] === 'gid://shopify/Order/1');
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && str_contains($request['query'], 'mutation CreateNacexFulfillment')
            && $request['variables']['fulfillment'] === [
                'notifyCustomer' => true,
                'trackingInfo' => [
                    'company' => 'NACEX',
                    'number' => '0014910300',
                    'url' => 'https://www.nacex.es/seguimientoDetalle.do?test=1',
                ],
                'lineItemsByFulfillmentOrder' => [[
                    'fulfillmentOrderId' => 'gid://shopify/FulfillmentOrder/10',
                ]],
            ]);
    }

    public function test_it_returns_null_when_the_order_no_longer_exists(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399]),
            self::GRAPHQL_URL => Http::response(['data' => ['order' => null]], 200),
        ]);

        $this->assertNull(app(ShopifyFulfillmentGateway::class)->revalidateOrder('gid://shopify/Order/1'));
    }

    public function test_it_rejects_fulfillment_user_errors_without_exposing_them(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399]),
            self::GRAPHQL_URL => Http::response(['data' => ['fulfillmentCreate' => [
                'fulfillment' => null,
                'userErrors' => [['field' => ['fulfillment'], 'message' => 'sensitive test detail']],
            ]]], 200),
        ]);

        $this->expectException(ShopifyCommunicationException::class);
        $this->expectExceptionMessage('Shopify rechazó la creación del cumplimiento.');

        app(ShopifyFulfillmentGateway::class)->createFulfillment(
            'gid://shopify/FulfillmentOrder/10',
            '0014910300',
            'https://www.nacex.es/seguimientoDetalle.do?test=1',
            false,
        );
    }

    /** @return array<string, mixed> */
    private function order(): array
    {
        return [
            'id' => 'gid://shopify/Order/1',
            'name' => '#TEST1',
            'createdAt' => '2026-08-01T10:00:00Z',
            'cancelledAt' => null,
            'displayFulfillmentStatus' => 'UNFULFILLED',
            'shippingAddress' => ['name' => 'Peña Muñoz', 'zip' => '08019'],
            'fulfillmentsCount' => ['count' => 0],
            'fulfillments' => [],
            'fulfillmentOrders' => [
                'nodes' => [[
                    'id' => 'gid://shopify/FulfillmentOrder/10',
                    'status' => 'OPEN',
                    'supportedActions' => [['action' => 'CREATE_FULFILLMENT']],
                ]],
                'pageInfo' => ['hasNextPage' => false],
            ],
        ];
    }
}
