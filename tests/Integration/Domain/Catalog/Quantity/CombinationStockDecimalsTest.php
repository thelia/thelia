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

use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\DTO\ProductWithPSECreateDTO;
use Thelia\Domain\Catalog\Product\DTO\PSEUpdateDTO;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Domain\Catalog\Product\PSEFacade;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A stock written from the back office holds the decimals its combination is sold with,
 * and nothing more: neither the minimum nor the step of a cart line apply to it. A
 * combination created or cloned keeps rules a cart can decide with.
 */
final class CombinationStockDecimalsTest extends ActionIntegrationTestCase
{
    public function testAStockWithMoreDecimalsThanTheCombinationIsRefusedAndNothingIsWritten(): void
    {
        [$product, $combination] = $this->combination(new QuantityRule(minQuantity: 2.0, step: 6.0));

        try {
            $this->update($product, $combination, stock: 12.5, rule: null);
            self::fail('A combination sold by the piece cannot keep half a piece in stock.');
        } catch (QuantityRefusedException $refusal) {
            self::assertSame('The quantity must be a whole number.', $refusal->getMessage());
        }

        self::assertSame(10.0, $this->stockOf($combination));
    }

    public function testAStockIsJudgedOnTheDecimalsTheSameWriteGivesTheCombination(): void
    {
        [$product, $combination] = $this->combination(new QuantityRule());

        $this->update($product, $combination, stock: 37.25, rule: new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2, unitCode: UnitCode::KILOGRAM));

        self::assertSame(37.25, $this->stockOf($combination));
    }

    /**
     * A stock below the minimum, or off the step, is a stock all the same: the rules of a
     * cart line do not hold there.
     */
    public function testAStockOffTheStepOrAtZeroIsKept(): void
    {
        [$product, $combination] = $this->combination(new QuantityRule(minQuantity: 2.0, step: 6.0));

        $this->update($product, $combination, stock: 7.0, rule: null);
        self::assertSame(7.0, $this->stockOf($combination));

        $this->update($product, $combination, stock: 0.0, rule: null);
        self::assertSame(0.0, $this->stockOf($combination));
    }

    public function testTheStockOfTheFacadeIsJudgedTheSameWay(): void
    {
        [, $combination] = $this->combination(new QuantityRule(minQuantity: 0.5, step: 0.5, decimals: 1));
        $facade = $this->getService(PSEFacade::class);

        $facade->updateStock($combination->getId(), 12.5);
        self::assertSame(12.5, $this->stockOf($combination));

        $this->expectException(QuantityRefusedException::class);
        $facade->updateStock($combination->getId(), 12.25);
    }

    public function testAProductCreatedWithItsCombinationIsSoldByThePiece(): void
    {
        $product = $this->getService(ProductFacade::class)->createWithPSE(new ProductWithPSECreateDTO(
            ref: 'BOLT-'.uniqid(),
            title: 'Bolt',
            locale: 'en_US',
            defaultCategoryId: $this->factory->category()->getId(),
            price: 0.5,
            currencyId: $this->factory->currency()->getId(),
            quantity: 40,
        ));

        $combination = $this->reloaded(ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne());

        self::assertEquals(new QuantityRule(), QuantityRule::fromProductSaleElements($combination));
        self::assertSame(40.0, (float) $combination->getQuantity());
    }

    /**
     * A clone of a combination sold by measure keeps its rules, and so its stock with
     * decimals: given the rules of a combination sold by the piece, the clone would refuse
     * the very stock it copies.
     */
    public function testACloneKeepsTheRulesAndTheStockOfItsCombination(): void
    {
        $rule = new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2, unitQuantity: 0.75, referenceUnitQuantity: 1.0, unitCode: UnitCode::LITRE);
        [$product, $combination] = $this->combination($rule);
        $combination->setQuantity(37.25)->save();
        $product->setLocale('en_US')->setTitle('Cloneable product')->save();

        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clone = $this->reloaded(ProductSaleElementsQuery::create()->filterByProductId($event->getClonedProduct()->getId())->findOne());
        self::assertEquals($rule, QuantityRule::fromProductSaleElements($clone));
        self::assertSame(37.25, (float) $clone->getQuantity());
    }

    /**
     * The generated combinations are sold by the piece: a stock of 2.5 given for all of
     * them is refused, where it was cut down to 2.
     */
    public function testGeneratedCombinationsRefuseAStockTheyCannotSell(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $attribute = $this->factory->attribute();
        $event = (new ProductCombinationGenerationEvent($product, $this->factory->currency()->getId(), [[$this->factory->attributeAv($attribute)->getId()]]))
            ->setPrice(10.0)
            ->setSalePrice(10.0)
            ->setWeight(0.0)
            ->setQuantity(2.5)
            ->setOnsale(false)
            ->setIsnew(false)
            ->setEanCode('');

        $this->expectException(QuantityRefusedException::class);
        $this->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);
    }

    /**
     * @return array{0: Product, 1: ProductSaleElements}
     */
    private function combination(QuantityRule $rule): array
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
        $combination = $product->getDefaultSaleElements();
        $rule->applyTo($combination);
        $combination->save($this->getPropelConnection());

        return [$product, $combination];
    }

    private function update(Product $product, ProductSaleElements $combination, float $stock, ?QuantityRule $rule): void
    {
        $this->getService(PSEFacade::class)->update($product->getId(), $combination->getId(), new PSEUpdateDTO(
            reference: $combination->getRef(),
            price: 10.0,
            currencyId: $this->factory->currency()->getId(),
            weight: 0.0,
            quantity: $stock,
            salePrice: 10.0,
            onSale: false,
            isNew: false,
            isDefault: true,
            eanCode: null,
            taxRuleId: (int) $product->getTaxRuleId(),
            quantityRule: $rule,
        ));
    }

    private function stockOf(ProductSaleElements $combination): float
    {
        return (float) $this->reloaded($combination)->getQuantity();
    }

    private function reloaded(?ProductSaleElements $combination): ProductSaleElements
    {
        self::assertNotNull($combination);
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
