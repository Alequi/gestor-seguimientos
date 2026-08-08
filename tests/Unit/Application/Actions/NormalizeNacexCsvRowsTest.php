<?php

namespace Tests\Unit\Application\Actions;

use App\Application\Actions\NormalizeNacexCsvRows;
use App\Application\Data\NacexCsvRow;
use PHPUnit\Framework\TestCase;

class NormalizeNacexCsvRowsTest extends TestCase
{
    public function test_it_creates_a_normalized_candidate_and_preserves_the_source_row(): void
    {
        $sourceRow = new NacexCsvRow(
            originAgency: '304',
            trackingNumber: '0014910300',
            registrationDate: '01/08/2026',
            reference: 'PEÑA-MUÑOZ, Álvarez',
            postalCode: '8019',
            status: 'Solicitada',
        );

        $candidates = (new NormalizeNacexCsvRows)->handle([$sourceRow]);

        self::assertCount(1, $candidates);
        self::assertSame($sourceRow, $candidates[0]->sourceRow);
        self::assertSame('304', $sourceRow->originAgency);
        self::assertSame('8019', $sourceRow->postalCode);
        self::assertSame('PEÑA-MUÑOZ, Álvarez', $sourceRow->reference);
        self::assertSame('0304', $candidates[0]->originAgency);
        self::assertSame('08019', $candidates[0]->postalCode);
        self::assertSame('peña muñoz alvarez', $candidates[0]->normalizedReference);
        self::assertSame('PEÑA-MUÑOZ, Álvarez', $candidates[0]->toArray()['reference']);
    }
}
