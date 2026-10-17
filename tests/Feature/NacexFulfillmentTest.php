<?php

namespace Tests\Feature;

use App\Application\Contracts\ShopifyFulfillmentGateway;
use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\ShopifyFulfillmentOrder;
use App\Application\Data\ShopifyOrderRevalidation;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Application\Exceptions\ShopifyCommunicationException;
use App\Domain\Matching\OrderDateWindow;
use DateTimeImmutable;
use Illuminate\Http\Testing\File;
use Tests\TestCase;

class NacexFulfillmentTest extends TestCase
{
    private const CSV = <<<'CSV'
Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado
0304;0014910300;01/08/2026;Peña Muñoz;08019;Solicitada
CSV;

    public function test_home_page_exposes_the_execution_endpoint(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('csvUpload.executionUrl'));
    }

    public function test_it_revalidates_an_automatic_selection_and_creates_the_fulfillment(): void
    {
        $this->app->instance(ShopifyOrderReader::class, $this->matchingOrderReader());
        $gateway = new class($this->revalidatedOrder()) implements ShopifyFulfillmentGateway
        {
            public array $created = [];

            public function __construct(private ShopifyOrderRevalidation $order) {}

            public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
            {
                return $this->order;
            }

            public function createFulfillment(
                string $fulfillmentOrderId,
                string $trackingNumber,
                string $trackingUrl,
                bool $notifyCustomer,
            ): void {
                $this->created[] = compact(
                    'fulfillmentOrderId',
                    'trackingNumber',
                    'trackingUrl',
                    'notifyCustomer',
                );
            }
        };
        $this->app->instance(ShopifyFulfillmentGateway::class, $gateway);

        $this->post(route('fulfillments.execute'), $this->requestData('AUTOMATIC', false))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'COMPLETED')
            ->assertJsonPath('data.results.0.orderName', '#TEST1')
            ->assertJsonPath('data.results.0.adminUrl', 'https://example-store.myshopify.com/admin/orders/1');

        $this->assertSame([[
            'fulfillmentOrderId' => 'gid://shopify/FulfillmentOrder/10',
            'trackingNumber' => '0014910300',
            'trackingUrl' => 'https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=0014910300&estado=4&internacional=0&externo=N',
            'notifyCustomer' => false,
        ]], $gateway->created);
    }

    public function test_tracking_exists_precedes_fulfilled_and_never_creates_a_fulfillment(): void
    {
        $this->app->instance(ShopifyOrderReader::class, $this->matchingOrderReader());
        $order = $this->revalidatedOrder(
            fulfillmentStatus: 'FULFILLED',
            trackingNumbers: ['0014910300'],
            fulfillmentCount: 1,
            fulfillmentOrders: [new ShopifyFulfillmentOrder(
                'gid://shopify/FulfillmentOrder/10',
                'CLOSED',
                [],
            )],
        );
        $gateway = new class($order) implements ShopifyFulfillmentGateway
        {
            public int $createCount = 0;

            public function __construct(private ShopifyOrderRevalidation $order) {}

            public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
            {
                return $this->order;
            }

            public function createFulfillment(string $fulfillmentOrderId, string $trackingNumber, string $trackingUrl, bool $notifyCustomer): void
            {
                $this->createCount++;
            }
        };
        $this->app->instance(ShopifyFulfillmentGateway::class, $gateway);

        $this->post(route('fulfillments.execute'), $this->requestData('AUTOMATIC', true))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'TRACKING_EXISTS');

        $this->assertSame(0, $gateway->createCount);
    }

    public function test_a_manual_assignment_is_skipped_when_the_current_shipping_data_no_longer_matches(): void
    {
        $this->app->instance(ShopifyOrderReader::class, $this->emptyOrderReader());
        $order = $this->revalidatedOrder(shippingPostalCode: '28019');
        $gateway = new class($order) implements ShopifyFulfillmentGateway
        {
            public int $createCount = 0;

            public function __construct(private ShopifyOrderRevalidation $order) {}

            public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
            {
                return $this->order;
            }

            public function createFulfillment(string $fulfillmentOrderId, string $trackingNumber, string $trackingUrl, bool $notifyCustomer): void
            {
                $this->createCount++;
            }
        };
        $this->app->instance(ShopifyFulfillmentGateway::class, $gateway);

        $this->post(route('fulfillments.execute'), $this->requestData('MANUAL', true))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'SKIPPED_AFTER_REVALIDATION');

        $this->assertSame(0, $gateway->createCount);
    }

    public function test_a_row_error_does_not_stop_the_rest_of_the_batch(): void
    {
        $csv = implode("\n", [
            'Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado',
            '0304;0014910300;01/08/2026;Peña Muñoz;08019;Solicitada',
            '0304;0014910301;01/08/2026;Peña Muñoz;08019;Solicitada',
        ])."\n";
        $this->app->instance(ShopifyOrderReader::class, $this->emptyOrderReader());
        $secondOrder = $this->revalidatedOrder(
            id: 'gid://shopify/Order/2',
            name: '#TEST2',
            adminUrl: 'https://example-store.myshopify.com/admin/orders/2',
        );
        $gateway = new class($secondOrder) implements ShopifyFulfillmentGateway
        {
            public array $created = [];

            public function __construct(private ShopifyOrderRevalidation $secondOrder) {}

            public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
            {
                if ($orderId === 'gid://shopify/Order/1') {
                    throw new ShopifyCommunicationException('test');
                }

                return $this->secondOrder;
            }

            public function createFulfillment(string $fulfillmentOrderId, string $trackingNumber, string $trackingUrl, bool $notifyCustomer): void
            {
                $this->created[] = $trackingNumber;
            }
        };
        $this->app->instance(ShopifyFulfillmentGateway::class, $gateway);

        $this->post(route('fulfillments.execute'), [
            'csv' => $this->csvFile($csv),
            'notifyCustomer' => '1',
            'selections' => json_encode([
                $this->selection('0014910300', 'gid://shopify/Order/1', 'MANUAL'),
                $this->selection('0014910301', 'gid://shopify/Order/2', 'MANUAL'),
            ], JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'ERROR')
            ->assertJsonPath('data.results.1.status', 'COMPLETED');

        $this->assertSame(['0014910301'], $gateway->created);
    }

    public function test_duplicate_selections_are_rejected_without_contacting_shopify(): void
    {
        $this->app->instance(ShopifyOrderReader::class, $this->emptyOrderReader());
        $gateway = new class implements ShopifyFulfillmentGateway
        {
            public int $calls = 0;

            public function revalidateOrder(string $orderId): ?ShopifyOrderRevalidation
            {
                $this->calls++;

                return null;
            }

            public function createFulfillment(string $fulfillmentOrderId, string $trackingNumber, string $trackingUrl, bool $notifyCustomer): void
            {
                $this->calls++;
            }
        };
        $this->app->instance(ShopifyFulfillmentGateway::class, $gateway);
        $selection = $this->selection('0014910300', 'gid://shopify/Order/1', 'MANUAL');

        $this->post(route('fulfillments.execute'), [
            'csv' => $this->csvFile(self::CSV),
            'notifyCustomer' => '1',
            'selections' => json_encode([$selection, $selection], JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'ERROR')
            ->assertJsonPath('data.results.1.status', 'ERROR');

        $this->assertSame(0, $gateway->calls);
    }

    public function test_it_rejects_an_invalid_order_identifier_before_processing(): void
    {
        $data = $this->requestData('MANUAL', true);
        $data['selections'] = json_encode([
            $this->selection('0014910300', 'gid://shopify/Customer/1', 'MANUAL'),
        ], JSON_THROW_ON_ERROR);

        $this->post(route('fulfillments.execute'), $data)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede iniciar el cierre de pedidos.')
            ->assertJsonValidationErrors('selections.0.orderId');
    }

    private function requestData(string $assignment, bool $notifyCustomer): array
    {
        return [
            'csv' => $this->csvFile(self::CSV),
            'notifyCustomer' => $notifyCustomer ? '1' : '0',
            'selections' => json_encode([
                $this->selection('0014910300', 'gid://shopify/Order/1', $assignment),
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function selection(string $trackingNumber, string $orderId, string $assignment): array
    {
        return [
            'originAgency' => '0304',
            'trackingNumber' => $trackingNumber,
            'orderId' => $orderId,
            'assignment' => $assignment,
        ];
    }

    private function revalidatedOrder(
        string $id = 'gid://shopify/Order/1',
        string $name = '#TEST1',
        string $fulfillmentStatus = 'UNFULFILLED',
        array $trackingNumbers = [],
        int $fulfillmentCount = 0,
        ?array $fulfillmentOrders = null,
        string $adminUrl = 'https://example-store.myshopify.com/admin/orders/1',
        string $shippingPostalCode = '08019',
    ): ShopifyOrderRevalidation {
        return new ShopifyOrderRevalidation(
            id: $id,
            name: $name,
            createdAt: new DateTimeImmutable('2026-08-01T10:00:00Z'),
            cancelledAt: null,
            fulfillmentStatus: $fulfillmentStatus,
            shippingName: 'Peña Muñoz',
            shippingPostalCode: $shippingPostalCode,
            trackingNumbers: $trackingNumbers,
            fulfillmentCount: $fulfillmentCount,
            fulfillmentOrders: $fulfillmentOrders ?? [new ShopifyFulfillmentOrder(
                'gid://shopify/FulfillmentOrder/10',
                'OPEN',
                ['CREATE_FULFILLMENT'],
            )],
            adminUrl: $adminUrl,
        );
    }

    private function matchingOrderReader(): ShopifyOrderReader
    {
        return new class implements ShopifyOrderReader
        {
            public function findCreatedIn(OrderDateWindow $window): array
            {
                return [new ShopifyOrderSnapshot(
                    id: 'gid://shopify/Order/1',
                    name: '#TEST1',
                    createdAt: new DateTimeImmutable('2026-08-01T10:00:00Z'),
                    cancelledAt: null,
                    fulfillmentStatus: 'UNFULFILLED',
                    shippingName: 'Peña Muñoz',
                    shippingPostalCode: '08019',
                )];
            }
        };
    }

    private function emptyOrderReader(): ShopifyOrderReader
    {
        return new class implements ShopifyOrderReader
        {
            public function findCreatedIn(OrderDateWindow $window): array
            {
                return [];
            }
        };
    }

    private function csvFile(string $contents): File
    {
        return File::createWithContent('expediciones.csv', $contents)->mimeType('text/csv');
    }
}
