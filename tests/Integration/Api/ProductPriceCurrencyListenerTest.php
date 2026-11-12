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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Api\Resource\Currency as CurrencyResource;
use Thelia\Api\Resource\ProductPrice as ProductPriceResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\IntegrationTestCase;

/**
 * The price a front read answers in the currency the shop is browsed in: the row of the
 * default currency is converted when the currency has none of its own. The amount of a row
 * is nullable on the resource (a visitor who is not shown a price reads none): a row that
 * states no amount converts to no price, and never reaches the converter as a null.
 */
final class ProductPriceCurrencyListenerTest extends IntegrationTestCase
{
    public function testTheDefaultCurrencyRowIsConvertedAtTheRateOfTheCurrentCurrency(): void
    {
        $resource = $this->readIn(100.0, 80.0);

        $prices = $resource->getProductPrices();
        self::assertCount(1, $prices);
        self::assertSame(200.0, $prices[0]->getPrice());
        self::assertSame(160.0, $prices[0]->getPromoPrice());
    }

    public function testARowThatStatesNoAmountIsNotConvertedAndLeavesTheRowsAsTheyWere(): void
    {
        foreach ([[null, 80.0], [100.0, null], [null, null]] as [$price, $promoPrice]) {
            $resource = $this->readIn($price, $promoPrice);

            $prices = $resource->getProductPrices();
            self::assertCount(1, $prices, 'the only row is the default currency one, as it was');
            self::assertSame($price, $prices[0]->getPrice());
            self::assertSame($promoPrice, $prices[0]->getPromoPrice());
        }
    }

    private function readIn(?float $price, ?float $promoPrice): ProductSaleElementsResource
    {
        $other = $this->createFixtureFactory()->currency(['code' => 'XTS', 'rate' => 2.0, 'visible' => 1]);
        $default = Currency::getDefaultCurrency();

        $row = (new ProductPriceResource())
            ->setCurrency((new CurrencyResource())->setId($default->getId()))
            ->setPrice($price)
            ->setPromoPrice($promoPrice)
            ->setFromDefaultCurrency(true);
        $resource = (new ProductSaleElementsResource())->setProductPrices([$row]);

        $listener = $this->getService(ProductPriceCurrencyListener::class);
        $current = new \ReflectionProperty($listener, 'currentCurrency');
        $current->setValue($listener, $other);

        $event = (new ModelToResourceEvent(new ProductSaleElements(), context: ['groups' => ['front:product_sale_elements:read']]))->setResource($resource);

        try {
            $listener->selectPriceOfCurrentCurrency($event);
        } finally {
            $listener->forgetCurrentCurrency();
        }

        return $resource;
    }
}
