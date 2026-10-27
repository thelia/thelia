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

namespace Thelia\Tests\Integration\Domain\Pricing\CustomerPrice;

use Thelia\Domain\Pricing\CustomerPrice\CustomerGroupPriceLoader;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroup;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What a group pays of its own, in the currency asked: the price typed there, or the
 * price of the default currency converted at the rates; an absence is no price.
 */
final class CustomerGroupPriceLoaderTest extends ActionIntegrationTestCase
{
    private CustomerGroupPriceLoader $loader;

    private Currency $defaultCurrency;

    private CustomerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loader = $this->getService(CustomerGroupPriceLoader::class);
        $this->defaultCurrency = Currency::getDefaultCurrency();
        $this->group = $this->factory->customerGroup();
    }

    public function testAPriceTypedInTheCurrencyIsReadAsTyped(): void
    {
        $other = $this->otherCurrency();
        $pse = $this->saleElement();
        $this->factory->customerGroupProductPrice($this->group, $pse, $this->defaultCurrency, 40.0, 30.0);
        $this->factory->customerGroupProductPrice($this->group, $pse, $other, 99.0, null);

        $price = $this->loader->load([$pse->getId()], (int) $this->group->getId(), $other)[$pse->getId()];

        self::assertEqualsWithDelta(99.0, $price->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(30.0 * 3.0 / $this->defaultRate(), $price->untaxedPromoPrice, 0.000001, 'the price absent there comes from the default currency');
    }

    public function testAPriceMissingInTheCurrencyIsConvertedFromTheDefaultCurrency(): void
    {
        $other = $this->otherCurrency();
        $pse = $this->saleElement();
        $this->factory->customerGroupProductPrice($this->group, $pse, $this->defaultCurrency, 40.0, null);

        $price = $this->loader->load([$pse->getId()], (int) $this->group->getId(), $other)[$pse->getId()];

        self::assertEqualsWithDelta(40.0 * 3.0 / $this->defaultRate(), $price->untaxedPrice, 0.000001);
        self::assertNull($price->untaxedPromoPrice);
    }

    public function testARowFlaggedAsConvertedIsConvertedAgain(): void
    {
        $other = $this->otherCurrency();
        $pse = $this->saleElement();
        $this->factory->customerGroupProductPrice($this->group, $pse, $this->defaultCurrency, 40.0, null);
        $this->factory->customerGroupProductPrice($this->group, $pse, $other, 999.0, null)->setFromDefaultCurrency(1)->save();

        $price = $this->loader->load([$pse->getId()], (int) $this->group->getId(), $other)[$pse->getId()];

        self::assertEqualsWithDelta(40.0 * 3.0 / $this->defaultRate(), $price->untaxedPrice, 0.000001);
    }

    public function testASaleElementTheGroupDoesNotPriceIsAbsent(): void
    {
        $withoutRow = $this->saleElement();
        $withEmptyRow = $this->saleElement();
        $ofAnotherGroup = $this->saleElement();
        $this->factory->customerGroupProductPrice($this->group, $withEmptyRow, $this->defaultCurrency);
        $this->factory->customerGroupProductPrice($this->factory->customerGroup(), $ofAnotherGroup, $this->defaultCurrency, 10.0);

        self::assertSame([], $this->loader->load(
            [$withoutRow->getId(), $withEmptyRow->getId(), $ofAnotherGroup->getId()],
            (int) $this->group->getId(),
            $this->defaultCurrency,
        ));
    }

    private function otherCurrency(): Currency
    {
        return $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 3.0, 'visible' => 1]);
    }

    private function defaultRate(): float
    {
        return (float) $this->defaultCurrency->getRate();
    }

    private function saleElement(): ProductSaleElements
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->defaultCurrency, ['basePrice' => 100.0])
            ->getDefaultSaleElements();
    }
}
