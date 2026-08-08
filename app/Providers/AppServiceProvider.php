<?php

namespace App\Providers;

use App\Application\Contracts\NacexCsvInspector;
use App\Domain\Shipment\NacexTrackingUrlBuilder;
use App\Infrastructure\Nacex\Csv\NativeNacexCsvInspector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
