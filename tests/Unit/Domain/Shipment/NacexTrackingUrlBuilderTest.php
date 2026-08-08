<?php

namespace Tests\Unit\Domain\Shipment;

use App\Domain\Shipment\NacexTrackingUrlBuilder;
use PHPUnit\Framework\TestCase;

class NacexTrackingUrlBuilderTest extends TestCase
{
    public function test_it_builds_the_nacex_tracking_url_with_the_configured_constants(): void
    {
        $builder = new NacexTrackingUrlBuilder(
            baseUrl: 'https://www.nacex.es/seguimientoDetalle.do',
            status: '4',
            international: '0',
            external: 'N',
        );

        self::assertSame(
            'https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=14910300&estado=4&internacional=0&externo=N',
            $builder->build('0304', '14910300'),
        );
    }

    public function test_it_preserves_leading_zeroes_and_encodes_query_values(): void
    {
        $builder = new NacexTrackingUrlBuilder(
            baseUrl: 'https://tracking.test/detail',
            status: '4',
            international: '0',
            external: 'N',
        );

        self::assertSame(
            'https://tracking.test/detail?agencia_origen=0304&numero_albaran=00149%2010300&estado=4&internacional=0&externo=N',
            $builder->build('0304', '00149 10300'),
        );
    }
}
