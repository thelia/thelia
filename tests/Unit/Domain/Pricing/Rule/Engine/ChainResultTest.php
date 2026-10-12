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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Engine;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Engine\ChainResult;

/**
 * Two periods merge only when they give the same price, attributed to the same rule,
 * with the same catalog price display: a price equal by coincidence under another
 * rule is still another period.
 */
final class ChainResultTest extends TestCase
{
    public function testTheSamePriceFromTheSameRuleIsTheSameResult(): void
    {
        $january = new ChainResult(80.0, 3, true, [3]);
        $february = new ChainResult(80.0, 3, true, [1, 3]);

        self::assertTrue($january->sameAs($february));
    }

    public function testTheSamePriceFromAnotherRuleIsAnotherResult(): void
    {
        $january = new ChainResult(80.0, 3, true, [3]);
        $february = new ChainResult(80.0, 4, true, [4]);

        self::assertFalse($january->sameAs($february));
    }

    public function testTheSamePriceShownWithoutTheCatalogPriceIsAnotherResult(): void
    {
        $january = new ChainResult(80.0, 3, true, [3]);
        $february = new ChainResult(80.0, 3, false, [3]);

        self::assertFalse($january->sameAs($february));
    }

    public function testAPriceOneCentApartIsAnotherResult(): void
    {
        $january = new ChainResult(80.0, 3, true, [3]);
        $february = new ChainResult(80.01, 3, true, [3]);

        self::assertFalse($january->sameAs($february));
    }

    public function testPricesHalfAMillionthApartAreAnotherResult(): void
    {
        $january = new ChainResult(0.0000005, 3, true, [3]);
        $february = new ChainResult(0.0, 3, true, [3]);

        self::assertFalse($january->sameAs($february));
    }
}
