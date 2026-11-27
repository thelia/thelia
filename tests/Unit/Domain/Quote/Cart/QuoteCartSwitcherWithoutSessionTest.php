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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Quote\Cart\QuoteCartInspector;
use Thelia\Domain\Quote\Cart\QuoteCartSwitcher;
use Thelia\Model\Cart;

final class QuoteCartSwitcherWithoutSessionTest extends TestCase
{
    public function testACommandHasNoCartToSwitch(): void
    {
        $switcher = new QuoteCartSwitcher(new RequestStack(), new QuoteCartInspector());
        $quoteCart = (new Cart())->setQuoteId(12)->setSetAsideCartId(4);

        self::assertFalse($switcher->putInSession($quoteCart));
        self::assertNull($switcher->restoreSetAside());
        self::assertSame(4, $quoteCart->getSetAsideCartId(), 'nothing was touched');
    }

    public function testARequestWithoutASessionHasNoCartToSwitchEither(): void
    {
        $switcher = new QuoteCartSwitcher(new RequestStack(), new QuoteCartInspector());
        $quoteCart = (new Cart())->setQuoteId(12);

        self::assertFalse($switcher->putInSession($quoteCart, new Request()));
        self::assertNull($switcher->restoreSetAside(new Request(), $quoteCart));
    }
}
