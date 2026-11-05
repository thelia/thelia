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

namespace Thelia\Tests\Unit\Domain\Catalog\Stock;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;

final class OutOfStockPolicyTest extends TestCase
{
    public function testTheStoredNumbersMapToTheModes(): void
    {
        self::assertSame(OutOfStockPolicy::Refuse, OutOfStockPolicy::fromStored(0));
        self::assertSame(OutOfStockPolicy::Unlimited, OutOfStockPolicy::fromStored(1));
        self::assertSame(OutOfStockPolicy::Limited, OutOfStockPolicy::fromStored(2));
    }

    public function testAMissingOrUnknownValueRefusesTheSale(): void
    {
        self::assertSame(OutOfStockPolicy::Refuse, OutOfStockPolicy::fromStored(null));
        self::assertSame(OutOfStockPolicy::Refuse, OutOfStockPolicy::fromStored(7));
        self::assertSame(OutOfStockPolicy::Refuse, OutOfStockPolicy::fromStored(-1));
    }
}
