<?php

namespace Tests\Feature;

use App\Application\Actions\FindShippingAddressMatchesForShipment;
use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\NacexCsvCandidate;
use App\Application\Data\NacexCsvRow;
use App\Application\Data\NacexShipmentCandidate;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateWindow;
use DateTimeImmutable;
use Tests\TestCase;

class FindShippingAddressMatchesForShipmentTest extends TestCase
{
    public function test_it_requires_name_postal_code_and_date_without_discarding_order_status(): void
    {
        $this->app->instance(ShopifyOrderReader::class, new class implements ShopifyOrderReader
        {
            public function findCreatedIn(OrderDateWindow $window): array
            {
                return [
                    self::order('1', 'María López García', '8019', '2026-10-14T10:00:00Z'),
                    self::order('2', 'Maria Lopez Gar', '08019', '2026-10-14T10:00:00Z', 'FULFILLED'),
                    self::order('3', 'Maria Lopez', '08019', '2026-10-14T10:00:00Z'),
                    self::order('4', 'María López García', '08018', '2026-10-14T10:00:00Z'),
                    self::order('5', 'María López García', '08019', '2026-09-13T21:59:59Z'),
                    self::order('6', null, '08019', '2026-10-14T10:00:00Z'),
                ];
            }

            private static function order(
                string $id,
                ?string $recipient,
                string $postalCode,
                string $createdAt,
                string $status = 'UNFULFILLED',
            ): ShopifyOrderSnapshot {
                return new ShopifyOrderSnapshot(
                    id: 'gid://shopify/Order/'.$id,
                    name: '#TEST'.$id,
                    createdAt: new DateTimeImmutable($createdAt),
                    cancelledAt: null,
                    fulfillmentStatus: $status,
                    shippingName: $recipient,
                    shippingPostalCode: $postalCode,
                );
            }
        });

        $row = new NacexCsvRow('304', '14910300', '14/10/2026', 'María López Garc', '8019', 'Solicitada');
        $shipment = new NacexShipmentCandidate(
            new NacexCsvCandidate($row, '0304', 'maria lopez garc', '08019'),
            'https://www.nacex.es/seguimientoDetalle.do',
        );

        $matches = app(FindShippingAddressMatchesForShipment::class)->handle($shipment);

        $this->assertCount(2, $matches);
        $this->assertSame('gid://shopify/Order/1', $matches[0]->id);
        $this->assertSame('gid://shopify/Order/2', $matches[1]->id);
        $this->assertSame('FULFILLED', $matches[1]->fulfillmentStatus);
    }
}
