<?php

namespace App\Application\Data;

final readonly class NacexCsvDeduplicationResult
{
    /**
     * @param  list<NacexCsvRow>  $rows
     * @param  list<NacexCsvDuplicateConflict>  $conflicts
     */
    public function __construct(
        public array $rows,
        public array $conflicts,
    ) {}
}
