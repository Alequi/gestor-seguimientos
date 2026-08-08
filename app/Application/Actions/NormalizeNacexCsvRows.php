<?php

namespace App\Application\Actions;

use App\Application\Data\NacexCsvCandidate;
use App\Application\Data\NacexCsvRow;
use App\Domain\Shipment\NacexShipmentNormalizer;

final readonly class NormalizeNacexCsvRows
{
    /**
     * @param  list<NacexCsvRow>  $rows
     * @return list<NacexCsvCandidate>
     */
    public function handle(array $rows): array
    {
        return array_map(
            static fn (NacexCsvRow $row): NacexCsvCandidate => new NacexCsvCandidate(
                sourceRow: $row,
                originAgency: NacexShipmentNormalizer::originAgency($row->originAgency),
                normalizedReference: NacexShipmentNormalizer::reference($row->reference),
                postalCode: NacexShipmentNormalizer::postalCode($row->postalCode),
            ),
            $rows,
        );
    }
}
