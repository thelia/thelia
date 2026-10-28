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

namespace Thelia\Tests\Unit\Domain\Catalog\Quantity;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Quantity\UnitCode;

final class UnitCodeTest extends TestCase
{
    public function testTheCodesAreTheOnesStoredAndExchanged(): void
    {
        self::assertSame(['pc', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm2', 'm3'], UnitCode::values());
    }

    public function testTheSymbolWritesTheSquareAndTheCubeAsExponents(): void
    {
        self::assertSame('m²', UnitCode::SQUARE_METRE->symbol());
        self::assertSame('m³', UnitCode::CUBIC_METRE->symbol());
        self::assertSame('kg', UnitCode::KILOGRAM->symbol());
        self::assertSame('l', UnitCode::LITRE->symbol());
        self::assertSame('pc', UnitCode::PIECE->symbol());
    }
}
