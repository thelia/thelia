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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon;

use Thelia\Domain\Promotion\Coupon\BaseFacade;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * The cart figures the coupons read: the lines a promotion offered never count,
 * and the lines already on special offer count unless the coupon leaves them out.
 */
final class BaseFacadeTest extends ActionIntegrationPromotionTestCase
{
    public function testTheTaxedTotalCountsTheSpecialOfferLinesUnlessTheyAreLeftOut(): void
    {
        $cart = $this->newEmptyCart();
        $offered = $this->addItem($cart, $this->product(['basePrice' => 3.0]));
        $regular = $this->addItem($cart, $this->product(), quantity: 2);
        $onSpecialOffer = $this->addItem($cart, $this->product(['basePrice' => 7.0]));
        $offered->setIsOffered(1)->save();
        $onSpecialOffer->setPromo(1)->save();

        $country = $this->getService(TaxEngine::class)->getDeliveryCountry();
        $regularTotal = $regular->getTotalRealTaxedPrice($country);
        $specialOfferTotal = $onSpecialOffer->getTotalRealTaxedPrice($country);
        $facade = $this->getService(BaseFacade::class);

        self::assertEqualsWithDelta($regularTotal + $specialOfferTotal, $facade->getCartTotalTaxPrice(), 0.001);
        self::assertEqualsWithDelta($regularTotal + $specialOfferTotal, $facade->getCartTotalTaxPrice(true), 0.001);
        self::assertEqualsWithDelta($regularTotal, $facade->getCartTotalTaxPrice(false), 0.001);
    }

    public function testTheOfferedLinesAreNotCountedAsArticles(): void
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(), quantity: 3);
        $this->addItem($cart, $this->product());
        $this->addItem($cart, $this->product())->setIsOffered(1)->save();

        self::assertSame(2, $this->getService(BaseFacade::class)->getNbArticlesInCart());
    }
}
