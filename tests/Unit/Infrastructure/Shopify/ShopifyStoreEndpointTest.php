<?php

namespace Tests\Unit\Infrastructure\Shopify;

use App\Infrastructure\Shopify\ShopifyStoreEndpoint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShopifyStoreEndpointTest extends TestCase
{
    public function test_builds_only_the_store_token_and_versioned_graphql_urls(): void
    {
        $endpoint = new ShopifyStoreEndpoint('example-store', '2026-10');

        $this->assertSame('https://example-store.myshopify.com/admin/oauth/access_token', $endpoint->tokenUrl());
        $this->assertSame('https://example-store.myshopify.com/admin/api/2026-10/graphql.json', $endpoint->graphqlUrl());
    }

    #[DataProvider('invalidShops')]
    public function test_rejects_invalid_shop_subdomains(string $shop): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShopifyStoreEndpoint($shop, '2026-10');
    }

    public static function invalidShops(): array
    {
        return [
            'empty' => [''],
            'full domain' => ['example.myshopify.com'],
            'other host' => ['https://example.org'],
            'path injection' => ['example/admin'],
            'leading hyphen' => ['-example'],
        ];
    }

    public function test_rejects_an_invalid_api_version(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShopifyStoreEndpoint('example-store', 'latest');
    }
}
