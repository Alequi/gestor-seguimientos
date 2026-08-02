<?php

namespace App\Application\Actions;

use App\Application\Contracts\NacexCsvInspector;
use App\Application\Data\NacexCsvValidationResult;

final readonly class ValidateNacexCsv
{
    public function __construct(
        private NacexCsvInspector $inspector,
    ) {}

    public function handle(
        string $path,
        string $originalName,
        int $sizeInBytes,
    ): NacexCsvValidationResult {
        return $this->inspector->inspect($path, $originalName, $sizeInBytes);
    }
}
