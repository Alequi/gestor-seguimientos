<?php

namespace App\Domain\Shipment;

final class NacexShipmentNormalizer
{
    public static function originAgency(string $originAgency): string
    {
        return str_pad(self::trimExteriorSpacing($originAgency), 4, '0', STR_PAD_LEFT);
    }

    public static function postalCode(string $postalCode): string
    {
        return str_pad($postalCode, 5, '0', STR_PAD_LEFT);
    }

    public static function reference(string $reference): string
    {
        $normalized = mb_strtolower($reference, 'UTF-8');
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private static function trimExteriorSpacing(string $value): string
    {
        return preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $value) ?? trim($value);
    }
}
