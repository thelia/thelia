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

namespace Thelia\Tests\Unit\Domain\Pricing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\MoneyRounding;

final class MoneyRoundingTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float}>
     */
    public static function amounts(): iterable
    {
        // 0.75 x 0.7 is 0.52499999… as a float: PHP 8.4 dropped the pre-rounding of round()
        // that brought it back to 0.525, and would charge 0.52.
        yield '0.7 kg at 0.75' => [0.75 * 0.7, 0.53];
        yield '1.25 kg at 14.82, 3 off' => [14.82 * 1.25 - 3, 15.53];
        yield '1.25 kg at 14.82' => [1.25 * 14.82, 18.53];
        yield 'a half cent written as such' => [1.005, 1.01];
        yield 'a refund of 0.7 kg at 0.75' => [-0.75 * 0.7, -0.53];
        yield 'under the half cent' => [0.5249, 0.52];
        yield 'already a cent' => [12.3, 12.3];
    }

    #[DataProvider('amounts')]
    public function testItRoundsHalfUpToTheCent(float $amount, float $expected): void
    {
        self::assertSame($expected, MoneyRounding::toTheCent($amount));
    }
}
