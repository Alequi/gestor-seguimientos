<?php

namespace Tests\Feature;

use App\Application\Actions\AnalyzeNacexShipments;
use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\NacexCsvCandidate;
use App\Application\Data\NacexCsvRow;
use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Application\Exceptions\ShopifyCommunicationException;
use App\Domain\Matching\OrderDateWindow;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyzeNacexShipmentsTest extends TestCase
{
    public function test_it_requires_a_unique_non_cancelled_match(): void
    {
        $cancelled = $this->order(id: '1', cancelled: true);
        $this->bindReader([$cancelled]);

        $result = app(AnalyzeNacexShipments::class)->handle([$this->shipment()]);

        $this->assertSame('NO_MATCH', $result[0]->status->value);

        $this->bindReader([$this->order(id: '1'), $this->order(id: '2')]);

        $result = app(AnalyzeNacexShipments::class)->handle([$this->shipment()]);

        $this->assertSame('AMBIGUOUS', $result[0]->status->value);
        $this->assertCount(2, $result[0]->candidateOrders);
        $this->assertNull($result[0]->order);
    }

    public function test_tracking_exists_takes_precedence_over_already_fulfilled(): void
    {
        $this->bindReader([$this->order(
            status: 'FULFILLED',
            trackingNumbers: ['14910300'],
            fulfillmentCount: 1,
            fulfillmentOrderStatuses: ['CLOSED'],
        )]);

        $result = app(AnalyzeNacexShipments::class)->handle([$this->shipment()]);

        $this->assertSame('TRACKING_EXISTS', $result[0]->status->value);
    }

    #[DataProvider('classifiedOrderProvider')]
    public function test_it_classifies_a_unique_order_safely(
        string $expected,
        string $status,
        array $trackingNumbers,
        int $fulfillmentCount,
        array $fulfillmentOrderStatuses,
    ): void {
        $this->bindReader([$this->order(
            status: $status,
            trackingNumbers: $trackingNumbers,
            fulfillmentCount: $fulfillmentCount,
            fulfillmentOrderStatuses: $fulfillmentOrderStatuses,
        )]);

        $result = app(AnalyzeNacexShipments::class)->handle([$this->shipment()]);

        $this->assertSame($expected, $result[0]->status->value);
        $this->assertNotNull($result[0]->order);
    }

    /** @return iterable<string, array{string, string, list<string>, int, list<string>}> */
    public static function classifiedOrderProvider(): iterable
    {
        yield 'safe' => ['MATCHED_SAFE', 'UNFULFILLED', [], 0, ['OPEN']];
        yield 'fulfilled' => ['ALREADY_FULFILLED', 'FULFILLED', [], 1, ['CLOSED']];
        yield 'partially fulfilled' => ['PARTIAL_FULFILLMENT', 'PARTIALLY_FULFILLED', [], 1, ['OPEN']];
        yield 'multiple fulfillment orders' => ['PARTIAL_FULFILLMENT', 'UNFULFILLED', [], 0, ['OPEN', 'OPEN']];
        yield 'existing fulfillment without matching tracking' => ['FULFILLMENT_EXISTS', 'UNFULFILLED', ['OTHER'], 1, ['OPEN']];
        yield 'unsupported state' => ['ERROR', 'ON_HOLD', [], 0, ['ON_HOLD']];
    }

    public function test_a_shopify_error_is_isolated_to_its_row(): void
    {
        $reader = new class($this->order()) implements ShopifyOrderReader
        {
            private int $calls = 0;

            public function __construct(private readonly ShopifyOrderSnapshot $order) {}

            public function findCreatedIn(OrderDateWindow $window): array
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new ShopifyCommunicationException('Detalle técnico que no debe exponerse.');
                }

                return [$this->order];
            }
        };
        $this->app->instance(ShopifyOrderReader::class, $reader);

        $results = app(AnalyzeNacexShipments::class)->handle([
            $this->shipment('14910300'),
            $this->shipment('14910301'),
        ]);

        $this->assertSame('ERROR', $results[0]->status->value);
        $this->assertSame('No se pudo consultar Shopify para esta expedición.', $results[0]->errorMessage);
        $this->assertSame('MATCHED_SAFE', $results[1]->status->value);
    }

    public function test_shipments_with_the_same_date_reuse_the_read_only_shopify_result(): void
    {
        $reader = new class($this->order()) implements ShopifyOrderReader
        {
            public int $calls = 0;

            public function __construct(private readonly ShopifyOrderSnapshot $order) {}

            public function findCreatedIn(OrderDateWindow $window): array
            {
                $this->calls++;

                return [$this->order];
            }
        };
        $this->app->instance(ShopifyOrderReader::class, $reader);

        app(AnalyzeNacexShipments::class)->handle([
            $this->shipment('14910300'),
            $this->shipment('14910301'),
        ]);

        $this->assertSame(1, $reader->calls);
    }

    /** @param list<ShopifyOrderSnapshot> $orders */
    private function bindReader(array $orders): void
    {
        $this->app->instance(ShopifyOrderReader::class, new class($orders) implements ShopifyOrderReader
        {
            /** @param list<ShopifyOrderSnapshot> $orders */
            public function __construct(private readonly array $orders) {}

            public function findCreatedIn(OrderDateWindow $window): array
            {
                return $this->orders;
            }
        });
    }

    private function shipment(string $trackingNumber = '14910300'): NacexShipmentCandidate
    {
        $row = new NacexCsvRow('304', $trackingNumber, '14/10/2026', 'Ana García', '8019', 'Solicitada');

        return new NacexShipmentCandidate(
            normalizedRow: new NacexCsvCandidate($row, '0304', 'ana garcia', '08019'),
            trackingUrl: 'https://www.nacex.es/seguimientoDetalle.do',
        );
    }

    /** @param list<string> $trackingNumbers @param list<string> $fulfillmentOrderStatuses */
    private function order(
        string $id = '1',
        string $status = 'UNFULFILLED',
        array $trackingNumbers = [],
        int $fulfillmentCount = 0,
        array $fulfillmentOrderStatuses = ['OPEN'],
        bool $cancelled = false,
    ): ShopifyOrderSnapshot {
        return new ShopifyOrderSnapshot(
            id: 'gid://shopify/Order/'.$id,
            name: '#TEST'.$id,
            createdAt: new DateTimeImmutable('2026-10-14T10:00:00Z'),
            cancelledAt: $cancelled ? new DateTimeImmutable('2026-10-15T10:00:00Z') : null,
            fulfillmentStatus: $status,
            shippingName: 'Ana García',
            shippingPostalCode: '08019',
            trackingNumbers: $trackingNumbers,
            fulfillmentCount: $fulfillmentCount,
            fulfillmentOrderStatuses: $fulfillmentOrderStatuses,
        );
    }
}
