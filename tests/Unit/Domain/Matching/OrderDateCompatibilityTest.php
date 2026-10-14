<?php

namespace Tests\Unit\Domain\Matching;

use App\Domain\Matching\OrderDateCompatibility;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderDateCompatibilityTest extends TestCase
{
    #[DataProvider('orderDates')]
    public function test_it_compares_calendar_days_in_madrid_with_inclusive_limits(
        string $createdAt,
        bool $expected,
    ): void {
        $compatibility = new OrderDateCompatibility(30, 7, new DateTimeZone('Europe/Madrid'));

        self::assertSame(
            $expected,
            $compatibility->includes('14/10/2026', new DateTimeImmutable($createdAt)),
        );
    }

    public static function orderDates(): array
    {
        return [
            'day before first limit' => ['2026-09-13T21:59:59Z', false],
            'first limit in Madrid' => ['2026-09-13T22:00:00Z', true],
            'last limit in Madrid' => ['2026-10-21T21:59:59Z', true],
            'day after last limit' => ['2026-10-21T22:00:00Z', false],
        ];
    }

    public function test_it_rejects_invalid_or_ambiguous_nacex_dates(): void
    {
        $compatibility = new OrderDateCompatibility(30, 7, new DateTimeZone('Europe/Madrid'));

        $this->expectException(InvalidArgumentException::class);

        $compatibility->includes('31/02/2026', new DateTimeImmutable('2026-10-14T12:00:00Z'));
    }

    public function test_it_uses_the_configured_window(): void
    {
        $compatibility = new OrderDateCompatibility(1, 2, new DateTimeZone('Europe/Madrid'));

        self::assertTrue($compatibility->includes('14/10/2026', new DateTimeImmutable('2026-10-13T12:00:00Z')));
        self::assertFalse($compatibility->includes('14/10/2026', new DateTimeImmutable('2026-10-12T12:00:00Z')));
    }
}
