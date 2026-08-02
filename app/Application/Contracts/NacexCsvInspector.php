<?php

namespace App\Application\Contracts;

use App\Application\Data\NacexCsvValidationResult;

interface NacexCsvInspector
{
    public function inspect(
        string $path,
        string $originalName,
        int $sizeInBytes,
    ): NacexCsvValidationResult;
}
