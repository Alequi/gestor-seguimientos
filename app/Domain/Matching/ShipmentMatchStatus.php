<?php

namespace App\Domain\Matching;

enum ShipmentMatchStatus: string
{
    case MatchedSafe = 'MATCHED_SAFE';
    case NoMatch = 'NO_MATCH';
    case Ambiguous = 'AMBIGUOUS';
    case AlreadyFulfilled = 'ALREADY_FULFILLED';
    case TrackingExists = 'TRACKING_EXISTS';
    case FulfillmentExists = 'FULFILLMENT_EXISTS';
    case PartialFulfillment = 'PARTIAL_FULFILLMENT';
    case Error = 'ERROR';
}
