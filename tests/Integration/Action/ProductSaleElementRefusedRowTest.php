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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\Propel;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\TaxRule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The back office saves the combinations grid row after row on the same product, each
 * row carrying the tax rule of the product. A row refused for its GTIN is rolled back,
 * the later rows are saved: the tax rule they carry must reach the database.
 *
 * No wrapping transaction here: inside one, the rollback of the refused row is only a
 * nested one and nothing is undone, which hides the defect.
 */
final class ProductSaleElementRefusedRowTest extends ActionIntegrationTestCase
{
    protected bool $useTransaction = false;

    private ?Product $product = null;
    private ?Category $category = null;

    /** @var list<TaxRule> */
    private array $taxRules = [];

    protected function tearDown(): void
    {
        Propel::disableInstancePooling();
        ProductQuery::create()->filterById($this->product?->getId())->delete();
        $this->category?->delete();
        foreach ($this->taxRules as $taxRule) {
            $taxRule->delete();
        }

        parent::tearDown();
    }

    public function testATaxRuleChangeSavedWithTheGridIsKeptWhenAnEarlierRowIsRefused(): void
    {
        Propel::enableInstancePooling();

        $currency = $this->factory->currency();
        $this->taxRules[] = $initialTaxRule = $this->factory->taxRule(['isDefault' => false]);
        $this->taxRules[] = $newTaxRule = $this->factory->taxRule(['isDefault' => false]);
        $this->category = $this->factory->category();
        $this->product = $product = $this->factory->product($this->category, $initialTaxRule, $currency);

        $refusedRow = $this->factory->productSaleElement($product);
        $savedRow = $this->factory->productSaleElement($product);

        try {
            $this->dispatcher->dispatch(
                $this->row($product, $refusedRow, $currency, $newTaxRule)->setEanCode('4006381333932'),
                TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT,
            );
            self::fail('The first row carries a wrong check digit and must be refused.');
        } catch (InvalidGtinException) {
        }

        $this->dispatcher->dispatch(
            $this->row($product, $savedRow, $currency, $newTaxRule)->setEanCode('4006381333931'),
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT,
        );

        ProductTableMap::clearInstancePool();
        $stored = ProductQuery::create()->findPk($product->getId());

        self::assertSame(
            $newTaxRule->getId(),
            $stored?->getTaxRuleId(),
            'The second row was saved with the new tax rule of the product, yet the product keeps its former one.',
        );
    }

    private function row(Product $product, ProductSaleElements $combination, Currency $currency, TaxRule $taxRule): ProductSaleElementUpdateEvent
    {
        return (new ProductSaleElementUpdateEvent($product, $combination->getId()))
            ->setReference($combination->getRef())
            ->setQuantity((float) $combination->getQuantity())
            ->setWeight((float) $combination->getWeight())
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault((bool) $combination->getIsDefault())
            ->setTaxRuleId($taxRule->getId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(10.0)
            ->setSalePrice(10.0);
    }
}
