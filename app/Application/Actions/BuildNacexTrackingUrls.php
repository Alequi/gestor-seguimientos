<?php

namespace App\Application\Actions;

use App\Application\Data\NacexCsvCandidate;
use App\Application\Data\NacexShipmentCandidate;
use App\Domain\Shipment\NacexTrackingUrlBuilder;

final readonly class BuildNacexTrackingUrls
{
    public function __construct(
        private NacexTrackingUrlBuilder $urlBuilder,
    ) {}

    /**
     * @param  list<NacexCsvCandidate>  $candidates
     * @return list<NacexShipmentCandidate>
     */
    public function handle(array $candidates): array
    {
        return array_map(
            fn (NacexCsvCandidate $candidate): NacexShipmentCandidate => new NacexShipmentCandidate(
                normalizedRow: $candidate,
                trackingUrl: $this->urlBuilder->build(
                    $candidate->originAgency,
                    $candidate->sourceRow->trackingNumber,
                ),
            ),
            $candidates,
        );
    }
}
