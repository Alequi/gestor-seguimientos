<?php

namespace Tests\Unit\Domain\Shipment;

use App\Domain\Shipment\NacexShipmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NacexShipmentStatusTest extends TestCase
{
    #[DataProvider('requestedStatusProvider')]
    public function test_it_recognizes_requested_status_without_case_or_spacing_differences(
        string $status,
    ): void {
        self::assertTrue(NacexShipmentStatus::from($status)->isRequested());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requestedStatusProvider(): iterable
    {
        yield 'canonical' => ['Solicitada'];
        yield 'uppercase' => ['SOLICITADA'];
        yield 'lowercase with exterior spaces' => ['  solicitada  '];
        yield 'tabs and duplicated spacing' => ["\t  Solicitada \t"];
        yield 'unicode exterior spacing' => ["\u{00A0}Solicitada\u{00A0}"];
    }

    #[DataProvider('ignoredStatusProvider')]
    public function test_it_rejects_any_other_status(string $status): void
    {
        self::assertFalse(NacexShipmentStatus::from($status)->isRequested());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredStatusProvider(): iterable
    {
        yield 'in transit' => ['En tránsito'];
        yield 'incident' => ['Incidencia'];
        yield 'delivered' => ['Entregado'];
        yield 'empty' => [''];
    }
}
