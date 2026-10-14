<?php

namespace Tests\Unit\Domain\Matching;

use App\Domain\Matching\RecipientNameCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecipientNameCompatibilityTest extends TestCase
{
    #[DataProvider('names')]
    public function test_it_matches_only_a_prefix_or_a_short_inverse_suffix_in_the_same_word(
        string $reference,
        string $shopifyName,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            (new RecipientNameCompatibility(2))->matches($reference, $shopifyName),
        );
    }

    public static function names(): array
    {
        return [
            'same name' => ['María López', 'Maria Lopez', true],
            'normal truncated reference' => ['María López Garc', 'Maria Lopez Garcia', true],
            'inverse one character' => ['María López Garc', 'Maria Lopez Gar', true],
            'inverse two characters' => ['María López Garcia', 'Maria Lopez Garc', true],
            'inverse three characters' => ['María López García', 'Maria Lopez Gar', false],
            'inverse new word' => ['María López García', 'Maria Lopez', false],
            'different name' => ['María López', 'Maria Luisa Lopez', false],
            'different enya' => ['Peña', 'Pena', false],
            'empty reference' => ['', 'Maria Lopez', false],
            'empty name' => ['Maria Lopez', '', false],
        ];
    }
}
