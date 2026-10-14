<?php

namespace App\Domain\Matching;

use App\Domain\Shipment\NacexShipmentNormalizer;
use InvalidArgumentException;

final readonly class RecipientNameCompatibility
{
    public function __construct(private int $maxInverseSuffixCharacters)
    {
        if ($maxInverseSuffixCharacters < 0) {
            throw new InvalidArgumentException('El límite de coincidencia inversa no puede ser negativo.');
        }
    }

    public function matches(string $nacexReference, string $shopifyName): bool
    {
        $reference = NacexShipmentNormalizer::reference($nacexReference);
        $name = NacexShipmentNormalizer::reference($shopifyName);

        if ($reference === '' || $name === '') {
            return false;
        }

        if (str_starts_with($name, $reference)) {
            return true;
        }

        if (! str_starts_with($reference, $name)) {
            return false;
        }

        $suffix = mb_substr($reference, mb_strlen($name, 'UTF-8'), null, 'UTF-8');

        return $suffix !== ''
            && mb_strlen($suffix, 'UTF-8') <= $this->maxInverseSuffixCharacters
            && ! str_contains($suffix, ' ');
    }
}
