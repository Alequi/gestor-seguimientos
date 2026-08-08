<?php

namespace Tests\Unit\Application\Actions;

use App\Application\Actions\BuildNacexTrackingUrls;
use App\Application\Data\NacexCsvCandidate;
use App\Application\Data\NacexCsvRow;
use App\Domain\Shipment\NacexTrackingUrlBuilder;
use PHPUnit\Framework\TestCase;

class BuildNacexTrackingUrlsTest extends TestCase
{
    public function test_it_adds_a_tracking_url_without_changing_the_normalized_candidate(): void
    {
        $sourceRow = new NacexCsvRow(
            originAgency: '304',
            trackingNumber: '0014910300',
            registrationDate: '01/08/2026',
            reference: 'Peña Muñoz',
            postalCode: '8019',
            status: 'Solicitada',
        );
        $normalizedRow = new NacexCsvCandidate(
            sourceRow: $sourceRow,
            originAgency: '0304',
            normalizedReference: 'peña muñoz',
            postalCode: '08019',
        );
        $action = new BuildNacexTrackingUrls(new NacexTrackingUrlBuilder(
            baseUrl: 'https://www.nacex.es/seguimientoDetalle.do',
            status: '4',
            international: '0',
            external: 'N',
        ));

        $candidates = $action->handle([$normalizedRow]);

        self::assertCount(1, $candidates);
        self::assertSame($normalizedRow, $candidates[0]->normalizedRow);
        self::assertSame(
            'https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=0014910300&estado=4&internacional=0&externo=N',
            $candidates[0]->trackingUrl,
        );
    }
}
