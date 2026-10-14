<?php

namespace Tests\Feature;

use App\Infrastructure\Shopify\ShopifyAdminGraphqlClient;
use App\Infrastructure\Shopify\ShopifyCommunicationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ShopifyAdminGraphqlClientTest extends TestCase
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

    public function test_it_exchanges_credentials_and_sends_a_versioned_graphql_request(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::response(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1']]], 200),
        ]);

        $client = app(ShopifyAdminGraphqlClient::class);

        $this->assertSame(['shop' => ['id' => 'gid://shopify/Shop/1']], $client->execute('query ShopifyConnectionTest { shop { id } }'));
        $this->assertSame(['shop' => ['id' => 'gid://shopify/Shop/1']], $client->execute('query ShopifyConnectionTest { shop { id } }'));

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::TOKEN_URL
            && $request->method() === 'POST'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'example-client'
            && $request['client_secret'] === 'test-secret'
            && str_contains((string) $request->header('Content-Type')[0], 'application/x-www-form-urlencoded'));
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && $request->method() === 'POST'
            && $request->hasHeader('X-Shopify-Access-Token', 'test-token')
            && $request['query'] === 'query ShopifyConnectionTest { shop { id } }'
            && ! array_key_exists('variables', $request->data()));
    }

    public function test_it_sends_named_variables_as_an_object(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::response(['data' => ['node' => ['id' => 'gid://shopify/Shop/1']]], 200),
        ]);

        app(ShopifyAdminGraphqlClient::class)->execute(
            'query ShopifyConnectionTest($shopId: ID!) { node(id: $shopId) { id } }',
            ['shopId' => 'gid://shopify/Shop/1'],
        );

        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
            && $request['variables'] === ['shopId' => 'gid://shopify/Shop/1']);
    }

    public function test_it_rejects_unkeyed_variables_before_contacting_shopify(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(ShopifyAdminGraphqlClient::class)->execute('query ShopifyConnectionTest { shop { id } }', ['unnamed']);
    }

    public function test_it_requests_a_new_token_when_the_current_one_is_near_expiry(): void
    {
        CarbonImmutable::setTestNow('2026-10-14 10:00:00');

        try {
            Http::fake([
                self::TOKEN_URL => Http::sequence()
                    ->push(['access_token' => 'first-token', 'expires_in' => 120], 200)
                    ->push(['access_token' => 'second-token', 'expires_in' => 120], 200),
                self::GRAPHQL_URL => Http::response(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1']]], 200),
            ]);

            $client = app(ShopifyAdminGraphqlClient::class);
            $client->execute('query ShopifyConnectionTest { shop { id } }');

            CarbonImmutable::setTestNow('2026-10-14 10:01:01');
            $client->execute('query ShopifyConnectionTest { shop { id } }');

            Http::assertSentCount(4);
            Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPHQL_URL
                && $request->hasHeader('X-Shopify-Access-Token', 'second-token'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_does_not_send_a_request_without_configuration(): void
    {
        config()->set('shopify.client_secret', '');

        Http::fake();

        try {
            app(ShopifyAdminGraphqlClient::class)->execute('query ShopifyConnectionTest { shop { id } }');
            $this->fail('Expected missing credentials to stop the request.');
        } catch (ShopifyCommunicationException $exception) {
            $this->assertSame('Faltan las credenciales de Shopify.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_hides_token_endpoint_error_details(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['error' => 'test-secret should not appear'], 401),
        ]);

        $this->expectException(ShopifyCommunicationException::class);
        $this->expectExceptionMessage('Shopify no devolvió un token de acceso válido.');

        app(ShopifyAdminGraphqlClient::class)->execute('query ShopifyConnectionTest { shop { id } }');
    }

    public function test_it_rejects_graphql_errors_without_exposing_response_data(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 86399], 200),
            self::GRAPHQL_URL => Http::response([
                'data' => ['shop' => null],
                'errors' => [['message' => 'personal data and test-token should not appear']],
            ], 200),
        ]);

        $this->expectException(ShopifyCommunicationException::class);
        $this->expectExceptionMessage('Shopify no devolvió una respuesta GraphQL válida.');

        app(ShopifyAdminGraphqlClient::class)->execute('query ShopifyConnectionTest { shop { id } }');
    }
}
