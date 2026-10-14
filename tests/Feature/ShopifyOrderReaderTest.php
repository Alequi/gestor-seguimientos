<?php

namespace Tests\Feature;

use App\Application\Actions\FindShopifyOrdersForNacexDate;
use App\Infrastructure\Shopify\ShopifyCommunicationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyOrderReaderTest extends TestCase
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

    public function test_it_reads_every_page_and_keeps_orders_needed_for_later_duplicate_checks(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::sequence()
                ->push(['data' => ['orders' => [
                    'nodes' => [
                        $this->order('gid://shopify/Order/1', '2026-09-13T22:00:00Z', 'UNFULFILLED'),
                        $this->order('gid://shopify/Order/2', '2026-10-14T10:00:00Z', 'FULFILLED', '2026-10-15T10:00:00Z'),
                    ],
                    'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'next-page'],
                ]]], 200)
                ->push(['data' => ['orders' => [
                    'nodes' => [
                        $this->order('gid://shopify/Order/3', '2026-10-21T21:59:59Z', 'UNFULFILLED'),
                        $this->order('gid://shopify/Order/4', '2026-10-21T22:00:00Z', 'UNFULFILLED'),
                    ],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'last-page'],
                ]]], 200),
        ]);

        $orders = app(FindShopifyOrdersForNacexDate::class)->handle('14/10/2026');

        $this->assertCount(3, $orders);
        $this->assertSame('gid://shopify/Order/1', $orders[0]->id);
        $this->assertSame('Test Receiver', $orders[0]->shippingName);
        $this->assertSame('08019', $orders[0]->shippingPostalCode);
        $this->assertSame('FULFILLED', $orders[1]->fulfillmentStatus);
        $this->assertNotNull($orders[1]->cancelledAt);
        $this->assertSame('gid://shopify/Order/3', $orders[2]->id);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && $request['variables']['search'] === 'created_at:>=2026-09-13T22:00:00Z created_at:<2026-10-21T22:00:00Z'
            && $request['variables']['first'] === 100
            && $request['variables']['after'] === null);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && $request['variables']['after'] === 'next-page');
    }

    public function test_it_rejects_a_broken_pagination_cursor_instead_of_returning_incomplete_results(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::response(['data' => ['orders' => [
                'nodes' => [],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => null],
            ]]], 200),
        ]);

        $this->expectException(ShopifyCommunicationException::class);

        app(FindShopifyOrdersForNacexDate::class)->handle('14/10/2026');
    }

    public function test_it_uses_shipping_first_and_last_name_when_the_full_name_is_empty(): void
    {
        $order = $this->order('gid://shopify/Order/1', '2026-10-14T10:00:00Z', 'UNFULFILLED');
        $order['shippingAddress'] = [
            'name' => '',
            'firstName' => 'Test',
            'lastName' => 'Receiver',
            'zip' => '08019',
        ];

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::response(['data' => ['orders' => [
                'nodes' => [$order],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]]], 200),
        ]);

        $orders = app(FindShopifyOrdersForNacexDate::class)->handle('14/10/2026');

        $this->assertSame('Test Receiver', $orders[0]->shippingName);
    }

    public function test_it_rejects_a_repeated_cursor_across_pages(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::sequence()
                ->push(['data' => ['orders' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'page-a']]]], 200)
                ->push(['data' => ['orders' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'page-b']]]], 200)
                ->push(['data' => ['orders' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'page-a']]]], 200),
        ]);

        $this->expectException(ShopifyCommunicationException::class);

        app(FindShopifyOrdersForNacexDate::class)->handle('14/10/2026');
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $id, string $createdAt, string $status, ?string $cancelledAt = null): array
    {
        return [
            'id' => $id,
            'name' => '#TEST',
            'createdAt' => $createdAt,
            'cancelledAt' => $cancelledAt,
            'displayFulfillmentStatus' => $status,
            'shippingAddress' => ['name' => 'Test Receiver', 'zip' => '08019'],
        ];
    }
}
