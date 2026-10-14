<?php

namespace App\Providers;

use App\Application\Contracts\NacexCsvInspector;
use App\Domain\Matching\OrderDateCompatibility;
use App\Domain\Shipment\NacexTrackingUrlBuilder;
use App\Infrastructure\Nacex\Csv\NativeNacexCsvInspector;
use App\Infrastructure\Shopify\ShopifyAdminGraphqlClient;
use App\Infrastructure\Shopify\ShopifyClientCredentialsTokenProvider;
use App\Infrastructure\Shopify\ShopifyStoreEndpoint;
use DateTimeZone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ShopifyStoreEndpoint::class,
            fn (): ShopifyStoreEndpoint => new ShopifyStoreEndpoint(
                shop: (string) config('shopify.shop'),
                apiVersion: (string) config('shopify.api_version'),
            ),
        );

        $this->app->singleton(
            ShopifyClientCredentialsTokenProvider::class,
            fn (): ShopifyClientCredentialsTokenProvider => new ShopifyClientCredentialsTokenProvider(
                endpoint: $this->app->make(ShopifyStoreEndpoint::class),
                clientId: (string) config('shopify.client_id'),
                clientSecret: (string) config('shopify.client_secret'),
            ),
        );

        $this->app->singleton(
            ShopifyAdminGraphqlClient::class,
            fn (): ShopifyAdminGraphqlClient => new ShopifyAdminGraphqlClient(
                endpoint: $this->app->make(ShopifyStoreEndpoint::class),
                tokens: $this->app->make(ShopifyClientCredentialsTokenProvider::class),
            ),
        );

        $this->app->singleton(
            OrderDateCompatibility::class,
            fn (): OrderDateCompatibility => new OrderDateCompatibility(
                daysBefore: (int) config('nacex.order_date.days_before'),
                daysAfter: (int) config('nacex.order_date.days_after'),
                timeZone: new DateTimeZone((string) config('nacex.order_date.time_zone')),
            ),
        );

        $this->app->singleton(
            NacexCsvInspector::class,
            fn (): NativeNacexCsvInspector => new NativeNacexCsvInspector(
                delimiter: (string) config('nacex.csv.delimiter'),
                encodings: array_values(config('nacex.csv.encodings')),
                requiredHeaders: array_values(config('nacex.csv.required_headers')),
                maxRows: (int) config('nacex.csv.max_rows'),
            ),
        );

        $this->app->singleton(
            NacexTrackingUrlBuilder::class,
            fn (): NacexTrackingUrlBuilder => new NacexTrackingUrlBuilder(
                baseUrl: (string) config('nacex.tracking.base_url'),
                status: (string) config('nacex.tracking.status'),
                international: (string) config('nacex.tracking.international'),
                external: (string) config('nacex.tracking.external'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
