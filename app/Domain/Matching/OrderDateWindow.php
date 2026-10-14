<?php

namespace App\Domain\Matching;

use DateTimeImmutable;

final readonly class OrderDateWindow
{
    public function __construct(
        public DateTimeImmutable $startInclusive,
        public DateTimeImmutable $endExclusive,
    ) {}

    public function includes(DateTimeImmutable $createdAt): bool
    {
        return $createdAt >= $this->startInclusive && $createdAt < $this->endExclusive;
    }
}
