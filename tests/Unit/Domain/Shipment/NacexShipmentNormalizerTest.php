<?php

namespace Tests\Unit\Domain\Shipment;

use App\Domain\Shipment\NacexShipmentNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NacexShipmentNormalizerTest extends TestCase
{
    #[DataProvider('agencyProvider')]
    public function test_it_normalizes_the_origin_agency(string $input, string $expected): void
    {
        self::assertSame($expected, NacexShipmentNormalizer::originAgency($input));
    }

    public static function agencyProvider(): array
    {
        return [
            'three digits' => ['304', '0304'],
            'two digits' => ['25', '0025'],
            'four digits' => ['1304', '1304'],
            'confirmed exterior spacing' => ["\u{00A0}304\t", '0304'],
        ];
    }

    #[DataProvider('postalCodeProvider')]
    public function test_it_normalizes_the_postal_code(string $input, string $expected): void
    {
        self::assertSame($expected, NacexShipmentNormalizer::postalCode($input));
    }

    public static function postalCodeProvider(): array
    {
        return [
            'four digits' => ['8019', '08019'],
            'five digits' => ['08019', '08019'],
        ];
    }

    #[DataProvider('referenceProvider')]
    public function test_it_normalizes_the_reference_without_converting_enye(
        string $input,
        string $expected,
    ): void {
        self::assertSame($expected, NacexShipmentNormalizer::reference($input));
    }

    public static function referenceProvider(): array
    {
        return [
            'case accents and enyes' => ['  PEÑA MUÑOZ Álvarez  ', 'peña muñoz alvarez'],
            'punctuation becomes spaces' => ["Ana-María D'Ángelo, Ruiz", 'ana maria d angelo ruiz'],
            'symbols and repeated spacing' => ['María & José / Núñez', 'maria jose nuñez'],
        ];
    }
}
