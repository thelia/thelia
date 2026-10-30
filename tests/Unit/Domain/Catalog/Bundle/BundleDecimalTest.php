<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Unit\Domain\Catalog\Bundle;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Bundle\BundleDecimal;

final class BundleDecimalTest extends TestCase
{
    public function testADecimalIsNormalisedWithoutTrailingZeros(): void
    {
        self::assertSame('2', BundleDecimal::normalize('2.000000'));
        self::assertSame('0.5', BundleDecimal::normalize('0.50'));
        self::assertSame('0.1', BundleDecimal::normalize(0.1));
        self::assertSame('3', BundleDecimal::normalize(3));
    }

    /**
     * @return iterable<string, array{string|int|float}>
     */
    public static function invalidValues(): iterable
    {
        yield 'negative string' => ['-1'];
        yield 'negative float' => [-0.5];
        yield 'text' => ['two'];
        yield 'seven decimals' => ['0.1234567'];
        yield 'exponent' => ['1e3'];
        yield 'thirteen whole digits' => ['10000000000000'];
        yield 'integer above the bound' => [10_000_000_000_000];
        yield 'the largest integer' => [\PHP_INT_MAX];
        yield 'float above the bound' => [1.0e13];
        yield 'infinite float' => [\INF];
        yield 'not a number' => [\NAN];
        yield 'a very long string' => [str_repeat('9', 5000)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testAnInvalidValueIsRefused(string|int|float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BundleDecimal::toMicro($value);
    }

    public function testAPriceTimesAQuantityKeepsEveryDecimal(): void
    {
        // 0.1 + 0.2 through floats is 0.30000000000000004
        $total = BundleDecimal::multiply(BundleDecimal::toMicro('0.1'), BundleDecimal::toMicro('3')) + BundleDecimal::multiply(BundleDecimal::toMicro('0.2'), BundleDecimal::toMicro('1'));

        self::assertSame('0.500000', BundleDecimal::format($total));
        self::assertSame('33.333300', BundleDecimal::format(BundleDecimal::multiply(BundleDecimal::toMicro('100'), BundleDecimal::toMicro('0.333333'))));
    }

    public function testTheProductIsRoundedHalfUp(): void
    {
        // 0.000001 * 0.5 = 0.0000005 -> 0.000001
        self::assertSame('0.000001', BundleDecimal::format(BundleDecimal::multiply(1, 500_000)));
        self::assertSame('0.000000', BundleDecimal::format(BundleDecimal::multiply(1, 499_999)));
    }

    public function testAnAmountBeyondTheRangeRaisesInsteadOfWrapping(): void
    {
        $this->expectException(\OverflowException::class);

        BundleDecimal::multiply(\PHP_INT_MAX, 2_000_000);
    }
}
