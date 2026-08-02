<?php

namespace App\Application\Actions;

use App\Application\Data\NacexCsvDeduplicationResult;
use App\Application\Data\NacexCsvDuplicateConflict;
use App\Application\Data\NacexCsvRow;
use App\Domain\Shipment\NacexShipmentIdentity;

final readonly class DeduplicateNacexCsvRows
{
    /**
     * @param  list<NacexCsvRow>  $rows
     */
    public function handle(array $rows): NacexCsvDeduplicationResult
    {
        $groups = [];

        foreach ($rows as $row) {
            $identity = NacexShipmentIdentity::from(
                $row->originAgency,
                $row->trackingNumber,
            );
            $key = $identity->key();

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'identity' => $identity,
                    'rows' => [],
                ];
            }

            $groups[$key]['rows'][] = $row;
        }

        $uniqueRows = [];
        $conflicts = [];

        foreach ($groups as $group) {
            $firstRow = $group['rows'][0];

            if ($this->hasConflictingData($group['rows'], $firstRow)) {
                $conflicts[] = new NacexCsvDuplicateConflict(
                    originAgency: $group['identity']->originAgency,
                    trackingNumber: $group['identity']->trackingNumber,
                    rows: $group['rows'],
                );

                continue;
            }

            $uniqueRows[] = $firstRow;
        }

        return new NacexCsvDeduplicationResult($uniqueRows, $conflicts);
    }

    /**
     * @param  non-empty-list<NacexCsvRow>  $rows
     */
    private function hasConflictingData(array $rows, NacexCsvRow $firstRow): bool
    {
        foreach ($rows as $row) {
            if (
                $row->registrationDate !== $firstRow->registrationDate
                || $row->reference !== $firstRow->reference
                || $row->postalCode !== $firstRow->postalCode
            ) {
                return true;
            }
        }

        return false;
    }
}
