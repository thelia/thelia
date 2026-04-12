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

use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ProductSaleElementActionTest extends ActionIntegrationTestCase
{
    public function testCreateWithoutCombinationReusesOrphanPse(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Product::create() already made a default PSE with no combination.
        // Dispatching CREATE with an empty attribute list should reuse it.
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($defaultPse);

        $event = new ProductSaleElementCreateEvent($product, [], $currency->getId());
        $this->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);

        $pse = $event->getProductSaleElement();
        self::assertNotNull($pse);
        self::assertSame($defaultPse->getId(), $pse->getId());
        self::assertTrue((bool) $pse->getIsDefault());
    }

    public function testCreateWithCombinationAttachesAttributes(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $attribute = $this->factory->attribute();
        $av1 = $this->factory->attributeAv($attribute);
        $av2 = $this->factory->attributeAv($attribute);

        $event = new ProductSaleElementCreateEvent(
            $product,
            [$av1->getId(), $av2->getId()],
            $currency->getId(),
        );
        $this->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);

        $pse = $event->getProductSaleElement();
        self::assertNotNull($pse);

        $combinations = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->find();
        self::assertCount(2, $combinations);

        $avIds = array_map(
            static fn ($c) => $c->getAttributeAvId(),
            iterator_to_array($combinations),
        );
        self::assertContains($av1->getId(), $avIds);
        self::assertContains($av2->getId(), $avIds);
    }

    public function testUpdateChangesPseFieldsAndPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $pse = $this->factory->productSaleElement($product);

        $event = new ProductSaleElementUpdateEvent($product, $pse->getId());
        $event
            ->setReference('UPDATED-REF')
            ->setQuantity(42)
            ->setWeight(1.5)
            ->setOnsale(1)
            ->setIsnew(1)
            ->setIsdefault(true)
            ->setEanCode('1234567890123')
            ->setTaxRuleId($product->getTaxRuleId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(29.99)
            ->setSalePrice(19.99);

        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertNotNull($reloaded);
        self::assertSame('UPDATED-REF', $reloaded->getRef());
        self::assertSame(42.0, $reloaded->getQuantity());
        self::assertEqualsWithDelta(1.5, $reloaded->getWeight(), 0.001);
        self::assertSame(1, $reloaded->getPromo());
        self::assertSame(1, $reloaded->getNewness());
        self::assertTrue((bool) $reloaded->getIsDefault());
        self::assertSame('1234567890123', $reloaded->getEanCode());

        $price = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne();
        self::assertNotNull($price);
        self::assertEqualsWithDelta(29.99, (float) $price->getPrice(), 0.001);
        self::assertEqualsWithDelta(19.99, (float) $price->getPromoPrice(), 0.001);
    }

    public function testUpdatePreventsSingleDefaultFromBecomingNonDefault(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // The product has exactly one default PSE created by Product::create().
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($defaultPse);

        // Try to set it as non-default — it should stay default because
        // it's the only one.
        $event = new ProductSaleElementUpdateEvent($product, $defaultPse->getId());
        $event
            ->setReference($defaultPse->getRef())
            ->setQuantity(1)
            ->setWeight(0)
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault(false)
            ->setEanCode(null)
            ->setTaxRuleId($product->getTaxRuleId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(10.0)
            ->setSalePrice(0.0);

        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $reloaded = ProductSaleElementsQuery::create()->findPk($defaultPse->getId());
        self::assertTrue((bool) $reloaded->getIsDefault());
    }

    public function testDeleteRemovesPseFromDatabase(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Create a second PSE so deletion doesn't hit the "last PSE" logic.
        $extra = $this->factory->productSaleElement($product);
        $extraId = $extra->getId();

        $this->dispatch(
            new ProductSaleElementDeleteEvent($extraId, $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        self::assertNull(ProductSaleElementsQuery::create()->findPk($extraId));
    }

    public function testDeleteLastPseDetachesCombinationsInsteadOfDeleting(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($defaultPse);

        // Attach a fake combination to the default PSE
        $attribute = $this->factory->attribute();
        $av = $this->factory->attributeAv($attribute);

        $combination = new \Thelia\Model\AttributeCombination();
        $combination
            ->setAttributeAvId($av->getId())
            ->setAttributeId($attribute->getId())
            ->setProductSaleElementsId($defaultPse->getId())
            ->save();

        self::assertGreaterThan(
            0,
            AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($defaultPse->getId())
                ->count(),
        );

        // Deleting the last PSE should NOT remove the PSE row,
        // but should clear its attribute combinations.
        $this->dispatch(
            new ProductSaleElementDeleteEvent($defaultPse->getId(), $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        $reloaded = ProductSaleElementsQuery::create()->findPk($defaultPse->getId());
        self::assertNotNull($reloaded, 'Last PSE must not be deleted');
        self::assertTrue((bool) $reloaded->getIsDefault());
        self::assertSame(
            0,
            AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($defaultPse->getId())
                ->count(),
        );
    }

    public function testDeleteDefaultPsePromotesNewestAsDefault(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($defaultPse);

        // Create two more PSEs so the product has 3 total.
        $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);

        $this->dispatch(
            new ProductSaleElementDeleteEvent($defaultPse->getId(), $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        self::assertNull(ProductSaleElementsQuery::create()->findPk($defaultPse->getId()));

        // The action promotes the most-recently-created remaining PSE.
        // When created_at timestamps collide, the pick is non-deterministic,
        // so we only assert that exactly one default still exists.
        $newDefault = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($newDefault, 'A new default PSE must be promoted');
        self::assertNotSame($defaultPse->getId(), $newDefault->getId());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );

        $pse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($pse);

        $originalVisibility = (bool) $pse->getVisible();

        $this->dispatch(
            new ProductSaleElementToggleVisibilityEvent($pse->getId()),
            TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY,
        );

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertSame(!$originalVisibility, (bool) $reloaded->getVisible());
    }

    public function testUpdatePositionMovesToAbsolutePosition(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $pse1 = $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);

        $event = new UpdatePositionEvent(
            $pse1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            3,
        );

        $this->dispatch($event, TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION);

        self::assertSame(
            3,
            ProductSaleElementsQuery::create()->findPk($pse1->getId())->getPosition(),
        );
    }

    public function testGenerateCombinationsReplacesExistingPses(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Product starts with one default PSE.
        $initialCount = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->count();
        self::assertSame(1, $initialCount);

        // Create two attributes with two values each → 2×2 = 4 combinations.
        $attr1 = $this->factory->attribute();
        $av1a = $this->factory->attributeAv($attr1);
        $av1b = $this->factory->attributeAv($attr1);

        $attr2 = $this->factory->attribute();
        $av2a = $this->factory->attributeAv($attr2);
        $av2b = $this->factory->attributeAv($attr2);

        $combinations = [
            [$av1a->getId(), $av2a->getId()],
            [$av1a->getId(), $av2b->getId()],
            [$av1b->getId(), $av2a->getId()],
            [$av1b->getId(), $av2b->getId()],
        ];

        $event = new ProductCombinationGenerationEvent(
            $product,
            $currency->getId(),
            $combinations,
        );
        $event
            ->setPrice(25.0)
            ->setSalePrice(20.0)
            ->setWeight(0.5)
            ->setQuantity(100)
            ->setOnsale(0)
            ->setIsnew(0)
            ->setEanCode('');

        $this->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);

        // All old PSEs are replaced — we should have exactly 4.
        $newPses = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->find();
        self::assertCount(4, $newPses);

        // The first combination is the default.
        $defaults = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->find();
        self::assertCount(1, $defaults);

        // Each PSE has exactly 2 attribute combinations.
        foreach ($newPses as $pse) {
            $combCount = AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->count();
            self::assertSame(2, $combCount);

            // Verify price was set.
            $price = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->filterByCurrencyId($currency->getId())
                ->findOne();
            self::assertNotNull($price);
            self::assertEqualsWithDelta(25.0, (float) $price->getPrice(), 0.001);
        }
    }
}
