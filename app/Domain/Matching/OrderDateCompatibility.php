<?php

namespace App\Domain\Matching;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class OrderDateCompatibility
{
    public function __construct(
        private int $daysBefore,
        private int $daysAfter,
        private DateTimeZone $timeZone,
    ) {
        if ($daysBefore < 0 || $daysAfter < 0) {
            throw new InvalidArgumentException('La ventana de búsqueda no puede tener días negativos.');
        }
    }

    public function includes(string $registrationDate, DateTimeImmutable $orderCreatedAt): bool
    {
        $nacexDate = DateTimeImmutable::createFromFormat('!d/m/Y', $registrationDate, $this->timeZone);

        if ($nacexDate === false || $nacexDate->format('d/m/Y') !== $registrationDate) {
            throw new InvalidArgumentException('La fecha de alta de NACEX no tiene el formato DD/MM/AAAA.');
        }

        $orderDate = $orderCreatedAt->setTimezone($this->timeZone)->format('Y-m-d');
        $firstDate = $nacexDate->modify("-{$this->daysBefore} days")->format('Y-m-d');
        $lastDate = $nacexDate->modify("+{$this->daysAfter} days")->format('Y-m-d');

        return $orderDate >= $firstDate && $orderDate <= $lastDate;
    }
}
