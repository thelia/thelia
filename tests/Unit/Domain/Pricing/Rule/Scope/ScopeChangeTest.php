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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Scope;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Scope\ScopeChange;

/**
 * What has to be repriced after a materialization is everything the rule covered
 * before or covers now, each sale element once, as a list indexed from zero again; the
 * order the ids come in says nothing.
 */
final class ScopeChangeTest extends TestCase
{
    public function testASaleElementCoveredBeforeAndAfterIsRepricedOnce(): void
    {
        $change = new ScopeChange(1, [4, 7], [7, 9]);

        // A list reindexed from zero, not [0 => 4, 1 => 7, 3 => 9].
        self::assertSame([4, 7, 9], $change->touchedProductSaleElementsIds());
    }

    public function testTheSameSaleElementsInAnotherOrderAreAnUnchangedScope(): void
    {
        self::assertTrue((new ScopeChange(1, [9, 4], [4, 9]))->isUnchanged());
        self::assertTrue((new ScopeChange(1, [4, 9], [9, 4]))->isUnchanged());
    }

    public function testASaleElementThatLeftIsAChangedScope(): void
    {
        self::assertFalse((new ScopeChange(1, [4, 9], [9]))->isUnchanged());
    }
}
