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

namespace Thelia\Tests\Unit\Domain\Quote\Cart;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Cart\QuoteCartInspector;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;

final class QuoteCartInspectorTest extends TestCase
{
    public function testACartThatNamesAQuoteIsTheCartOfAQuote(): void
    {
        $inspector = new QuoteCartInspector();

        self::assertFalse($inspector->isQuoteCart(new Cart()));
        self::assertTrue($inspector->isQuoteCart((new Cart())->setQuoteId(12)));
    }

    public function testALineThatNamesTheLineOfAQuoteIsFrozen(): void
    {
        $inspector = new QuoteCartInspector();

        self::assertFalse($inspector->isFrozenLine(new CartItem()));
        self::assertTrue($inspector->isFrozenLine((new CartItem())->setQuoteItemId(3)));
    }

    public function testACartWithoutAQuoteHasNoQuoteNorRevision(): void
    {
        $inspector = new QuoteCartInspector();

        self::assertNull($inspector->quoteOf(new Cart()));
        self::assertNull($inspector->acceptedRevisionOf(new Cart()));
        self::assertNull($inspector->acceptedRevisionOf((new Cart())->setQuoteId(12)), 'no revision number: no revision');
    }
}
