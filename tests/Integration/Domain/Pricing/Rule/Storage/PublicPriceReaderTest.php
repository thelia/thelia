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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Storage;

use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRulePrice;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The stored public price of a batch of sale elements, read at one instant.
 */
final class PublicPriceReaderTest extends ActionIntegrationTestCase
{
    private PublicPriceReader $reader;

    private Currency $currency;

    private CatalogPriceRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->getService(PublicPriceReader::class);
        $this->currency = Currency::getDefaultCurrency();
        $this->rule = $this->factory->catalogPriceRule(['percentageValue' => 20.0, 'active' => true]);
    }

    public function testEverySaleElementOfTheBatchIsAnswered(): void
    {
        $first = $this->pricedSaleElement('80.000000');
        $second = $this->pricedSaleElement('40.000000');

        $prices = $this->reader->currentPrices([$first->getId(), $second->getId()], $this->currency);

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], array_keys($prices));
        self::assertEqualsWithDelta(80.0, $prices[$first->getId()]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(40.0, $prices[$second->getId()]->untaxedPrice, 0.000001);
    }

    public function testAnIdThatIsNotAnIntegerNeverWidensTheStatement(): void
    {
        $asked = $this->pricedSaleElement('80.000000');
        $this->pricedSaleElement('40.000000');

        self::assertSame([$asked->getId()], array_keys($this->reader->currentPrices([$asked->getId().') OR (1 = 1'], $this->currency)));
    }

    private function pricedSaleElement(string $price): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        (new CatalogPriceRulePrice())
            ->setProductSaleElementsId($pse->getId())
            ->setCurrencyId($this->currency->getId())
            ->setPrice($price)
            ->setCatalogPriceRuleId($this->rule->getId())
            ->setDisplayInitialPrice(true)
            ->save();

        return $pse;
    }
}
