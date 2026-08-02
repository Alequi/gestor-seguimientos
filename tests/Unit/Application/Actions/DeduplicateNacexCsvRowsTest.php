<?php

namespace Tests\Unit\Application\Actions;

use App\Application\Actions\DeduplicateNacexCsvRows;
use App\Application\Data\NacexCsvRow;
use PHPUnit\Framework\TestCase;

final class DeduplicateNacexCsvRowsTest extends TestCase
{
    public function test_it_keeps_one_row_for_the_same_normalized_identity_and_data(): void
    {
        $first = $this->row(originAgency: '304', status: 'Solicitada');
        $duplicate = $this->row(
            originAgency: ' 0304 ',
            trackingNumber: " 14910300\t",
            status: ' SOLICITADA ',
        );

        $result = (new DeduplicateNacexCsvRows)->handle([$first, $duplicate]);

        self::assertSame([$first], $result->rows);
        self::assertSame([], $result->conflicts);
        self::assertSame('304', $result->rows[0]->originAgency);
        self::assertSame('14910300', $result->rows[0]->trackingNumber);
    }

    public function test_it_keeps_distinct_identities_in_source_order(): void
    {
        $first = $this->row(trackingNumber: '14910300');
        $second = $this->row(trackingNumber: '14910301');

        $result = (new DeduplicateNacexCsvRows)->handle([$first, $second]);

        self::assertSame([$first, $second], $result->rows);
        self::assertSame([], $result->conflicts);
    }

    public function test_it_excludes_every_row_in_an_identity_group_with_conflicting_data(): void
    {
        $first = $this->row(reference: 'Ana García', postalCode: '08019');
        $conflicting = $this->row(reference: 'Ana García', postalCode: '28019');

        $result = (new DeduplicateNacexCsvRows)->handle([$first, $conflicting]);

        self::assertSame([], $result->rows);
        self::assertCount(1, $result->conflicts);
        self::assertSame('0304', $result->conflicts[0]->originAgency);
        self::assertSame('14910300', $result->conflicts[0]->trackingNumber);
        self::assertSame([$first, $conflicting], $result->conflicts[0]->rows);
    }

    public function test_it_treats_date_reference_and_postal_code_differences_as_conflicts(): void
    {
        $base = $this->row();

        foreach ([
            $this->row(registrationDate: '02/08/2026'),
            $this->row(reference: 'Bea Muñoz'),
            $this->row(postalCode: '08020'),
        ] as $differentRow) {
            $result = (new DeduplicateNacexCsvRows)->handle([$base, $differentRow]);

            self::assertSame([], $result->rows);
            self::assertCount(1, $result->conflicts);
        }
    }

    private function row(
        string $originAgency = '0304',
        string $trackingNumber = '14910300',
        string $registrationDate = '01/08/2026',
        string $reference = 'Ana García',
        string $postalCode = '08019',
        string $status = 'Solicitada',
    ): NacexCsvRow {
        return new NacexCsvRow(
            originAgency: $originAgency,
            trackingNumber: $trackingNumber,
            registrationDate: $registrationDate,
            reference: $reference,
            postalCode: $postalCode,
            status: $status,
        );
    }
}
