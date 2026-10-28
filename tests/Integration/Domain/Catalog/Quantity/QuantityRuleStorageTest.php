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

namespace Thelia\Tests\Integration\Domain\Catalog\Quantity;

use Thelia\Domain\Catalog\Product\DTO\PSEUpdateDTO;
use Thelia\Domain\Catalog\Product\PSEFacade;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The quantity rules and the unit of a combination: stored in their columns, read back
 * as numbers, and written by the update of a combination only when it carries them.
 */
final class QuantityRuleStorageTest extends ActionIntegrationTestCase
{
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
    }

    public function testACombinationCreatedWithoutRulesSellsByThePieceFromOne(): void
    {
        $combination = $this->factory->productSaleElement($this->product());

        self::assertEquals(new QuantityRule(), QuantityRule::fromProductSaleElements($this->reloaded($combination)));
    }

    public function testTheRulesOfTheFixtureAreStoredAndReadBack(): void
    {
        $combination = $this->factory->productSaleElement($this->product(), [
            'minQuantity' => 2,
            'quantityStep' => 6,
            'unitQuantity' => 0.75,
            'referenceUnitQuantity' => 1,
            'unitCode' => 'l',
        ]);

        $rule = QuantityRule::fromProductSaleElements($this->reloaded($combination));

        self::assertSame(2.0, $rule->minQuantity);
        self::assertSame(6.0, $rule->step);
        self::assertSame(0, $rule->decimals);
        self::assertSame(0.75, $rule->unitQuantity);
        self::assertSame(1.0, $rule->referenceUnitQuantity);
        self::assertSame(UnitCode::LITRE, $rule->unitCode);
    }

    public function testTheUpdateOfACombinationWritesItsRules(): void
    {
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product);

        $this->facade()->update($product->getId(), $combination->getId(), $this->update($combination, new QuantityRule(
            minQuantity: 1,
            step: 0.25,
            decimals: 2,
            unitQuantity: 1,
            referenceUnitQuantity: 1,
            unitCode: UnitCode::KILOGRAM,
        )));

        $stored = $this->reloaded($combination);
        self::assertSame('1.000000', $stored->getMinQuantity());
        self::assertSame('0.250000', $stored->getQuantityStep());
        self::assertSame(2, $stored->getQuantityDecimals());
        self::assertSame('1.000000', $stored->getUnitQuantity());
        self::assertSame('1.000000', $stored->getReferenceUnitQuantity());
        self::assertSame('kg', $stored->getUnitCode());
    }

    public function testAnUpdateThatLeavesTheRulesOutKeepsThem(): void
    {
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product, ['minQuantity' => 2, 'quantityStep' => 6, 'unitCode' => 'pc']);

        $this->facade()->update($product->getId(), $combination->getId(), $this->update($combination, null));

        $stored = $this->reloaded($combination);
        self::assertSame('2.000000', $stored->getMinQuantity());
        self::assertSame('6.000000', $stored->getQuantityStep());
        self::assertSame('pc', $stored->getUnitCode());
    }

    public function testTheNameOfAUnitIsTranslated(): void
    {
        self::assertSame('kilogramme', UnitCode::KILOGRAM->label('fr_FR'));
        self::assertSame('mètre carré', UnitCode::SQUARE_METRE->label('fr_FR'));
        self::assertSame('kilogram', UnitCode::KILOGRAM->label('en_US'));
    }

    private function update(ProductSaleElements $combination, ?QuantityRule $quantityRule): PSEUpdateDTO
    {
        return new PSEUpdateDTO(
            reference: $combination->getRef(),
            price: 10.0,
            currencyId: $this->currency->getId(),
            quantity: (float) $combination->getQuantity(),
            taxRuleId: (int) $combination->getProduct()->getTaxRuleId(),
            quantityRule: $quantityRule,
        );
    }

    private function facade(): PSEFacade
    {
        return $this->getService(PSEFacade::class);
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);
    }

    private function reloaded(ProductSaleElements $combination): ProductSaleElements
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
