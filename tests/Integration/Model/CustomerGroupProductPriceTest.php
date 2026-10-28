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

namespace Thelia\Tests\Integration\Model;

use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroupProductPrice;
use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A group price with neither a price nor a promo price is no price: whichever way it is
 * written, the row does not stay, so that the shop does not look like one that prices per
 * group when none does.
 */
final class CustomerGroupProductPriceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->getService(CatalogActivityProbe::class)->reset();
    }

    protected function tearDown(): void
    {
        $this->getService(CatalogActivityProbe::class)->reset();

        parent::tearDown();
    }

    public function testARowWithNeitherPriceIsNotInserted(): void
    {
        $price = $this->newPrice();

        self::assertSame(0, $price->save($this->getPropelConnection()));

        self::assertSame(0, CustomerGroupProductPriceQuery::create()->count());
        self::assertFalse($this->getService(CatalogActivityProbe::class)->read()->hasCustomerGroupPrices);
    }

    public function testAnExistingRowLosingItsLastPriceIsDeleted(): void
    {
        $price = $this->newPrice()->setPrice('42.000000');
        $price->save($this->getPropelConnection());
        self::assertSame(1, CustomerGroupProductPriceQuery::create()->count());

        $price->setPrice(null)->save($this->getPropelConnection());

        self::assertSame(0, CustomerGroupProductPriceQuery::create()->count());
    }

    public function testARowKeepsItsRemainingPriceWhenOnlyOneOfThemIsEmptied(): void
    {
        $price = $this->newPrice()->setPrice('42.000000')->setPromoPrice('30.000000');
        $price->save($this->getPropelConnection());

        $price->setPrice(null)->save($this->getPropelConnection());

        $stored = CustomerGroupProductPriceQuery::create()->findPk($price->getId());
        self::assertNotNull($stored);
        self::assertNull($stored->getPrice());
        self::assertEquals(30.0, (float) $stored->getPromoPrice());
    }

    public function testAPriceOfZeroIsAPrice(): void
    {
        $price = $this->newPrice()->setPrice('0.000000');
        $price->save($this->getPropelConnection());

        self::assertSame(1, CustomerGroupProductPriceQuery::create()->count(), 'free is not empty');
    }

    private function newPrice(): CustomerGroupProductPrice
    {
        $currency = Currency::getDefaultCurrency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['basePrice' => 100.0]);

        return (new CustomerGroupProductPrice())
            ->setCustomerGroupId($this->factory->customerGroup()->getId())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0);
    }
}
