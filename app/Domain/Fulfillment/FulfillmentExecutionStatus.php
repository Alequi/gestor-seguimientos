<?php

namespace App\Domain\Fulfillment;

enum FulfillmentExecutionStatus: string
{
    case Completed = 'COMPLETED';
    case TrackingExists = 'TRACKING_EXISTS';
    case AlreadyFulfilled = 'ALREADY_FULFILLED';
    case SkippedAfterRevalidation = 'SKIPPED_AFTER_REVALIDATION';
    case Error = 'ERROR';
}
