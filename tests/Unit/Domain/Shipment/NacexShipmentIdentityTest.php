<?php

namespace Tests\Unit\Domain\Shipment;

use App\Domain\Shipment\NacexShipmentIdentity;
use PHPUnit\Framework\TestCase;

final class NacexShipmentIdentityTest extends TestCase
{
    public function test_it_normalizes_the_agency_and_exterior_spacing_for_comparison(): void
    {
        $identity = NacexShipmentIdentity::from(
            "\u{00A0}304 ",
            "\t14910300\u{00A0}",
        );

        self::assertSame('0304', $identity->originAgency);
        self::assertSame('14910300', $identity->trackingNumber);
        self::assertSame(
            NacexShipmentIdentity::from('0304', '14910300')->key(),
            $identity->key(),
        );
    }

    public function test_it_keeps_different_agencies_or_tracking_numbers_distinct(): void
    {
        $identity = NacexShipmentIdentity::from('0304', '14910300');

        self::assertNotSame(
            NacexShipmentIdentity::from('1304', '14910300')->key(),
            $identity->key(),
        );
        self::assertNotSame(
            NacexShipmentIdentity::from('0304', '014910300')->key(),
            $identity->key(),
        );
    }
}
