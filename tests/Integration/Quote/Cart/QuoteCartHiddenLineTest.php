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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Domain\Cart\Exception\CartLineUnavailableException;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Cart\Service\CartLineVisibility;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CustomerTableMap;

/**
 * A product the buyer no longer sees (their group is restricted to other categories) is taken out
 * of an ordinary cart behind their back. It is not taken out of the cart of a quote: the line
 * stays, and the order refuses it, so that nothing the shop agreed to is lost without a word.
 */
final class QuoteCartHiddenLineTest extends QuoteCartTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $group = $this->factory->customerGroup('sofas-only', 0.0, categoryIds: [$this->factory->category()->getId()]);
        $this->factory->assignCustomerGroup($this->buyer, $group);
        CustomerTableMap::clearInstancePool();
        $this->getService('services_resetter')->reset();
    }

    public function testTheLineOfAQuoteStaysAndTheOrderRefusesIt(): void
    {
        $quoteCart = $this->quoteCart();

        self::assertSame([], $this->getService(CartLineVisibility::class)->removeHiddenLines($quoteCart));
        self::assertSame(1, CartItemQuery::create()->filterByCartId($quoteCart->getId())->count());

        $this->expectException(CartLineUnavailableException::class);
        $this->getService(CartGuard::class)->checkLinesAreAvailable($quoteCart);
    }

    public function testTheLineOfAnOrdinaryCartIsTakenOut(): void
    {
        $ordinaryCart = $this->ordinaryCart();

        self::assertNotSame([], $this->getService(CartLineVisibility::class)->removeHiddenLines($ordinaryCart));
        self::assertSame(0, CartItemQuery::create()->filterByCartId($ordinaryCart->getId())->count());
    }
}
