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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Shipping\ProductRule\ShippingSurchargeMode;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductExcludedDeliveryModule;
use Thelia\Model\ProductExcludedDeliveryModuleQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class ProductActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testCreateProductPersistsWithDefaultPSE(): void
    {
        $category = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();

        $event = new ProductCreateEvent();
        $event
            ->setRef('TEST-PROD-1')
            ->setTitle('Test Product')
            ->setLocale('en_US')
            ->setDefaultCategory($category->getId())
            ->setVisible(true)
            ->setVirtual(false)
            ->setBasePrice(29.99)
            ->setBaseWeight(0.5)
            ->setCurrencyId($currency->getId())
            ->setTaxRuleId($taxRule->getId())
            ->setBaseQuantity(100);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CREATE);

        $product = $event->getProduct();
        self::assertNotNull($product);
        self::assertSame('TEST-PROD-1', $product->getRef());

        // Default PSE must be created automatically
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        self::assertNotNull($defaultPse, 'Default PSE must be created with the product');
        self::assertEquals(100, $defaultPse->getQuantity());
        self::assertEqualsWithDelta(0.5, $defaultPse->getWeight(), 0.001);

        // Price must be set in the chosen currency
        $price = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($defaultPse->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne();

        self::assertNotNull($price, 'Price must exist for the default PSE');
        self::assertEqualsWithDelta(29.99, (float) $price->getPrice(), 0.01);
    }

    public function testCreateProductAssignsDefaultCategory(): void
    {
        $category = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();

        $event = new ProductCreateEvent();
        $event
            ->setRef('TEST-CAT-ASSIGN')
            ->setTitle('Category Assignment Test')
            ->setLocale('en_US')
            ->setDefaultCategory($category->getId())
            ->setBasePrice(10.0)
            ->setCurrencyId($currency->getId())
            ->setTaxRuleId($taxRule->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CREATE);

        $product = $event->getProduct();
        self::assertSame($category->getId(), $product->getDefaultCategoryId());
    }

    public function testCreateVirtualProduct(): void
    {
        $category = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();

        $event = new ProductCreateEvent();
        $event
            ->setRef('VIRTUAL-1')
            ->setTitle('Digital Download')
            ->setLocale('en_US')
            ->setDefaultCategory($category->getId())
            ->setVisible(true)
            ->setVirtual(true)
            ->setBasePrice(5.0)
            ->setCurrencyId($currency->getId())
            ->setTaxRuleId($taxRule->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CREATE);

        $product = $event->getProduct();
        self::assertSame(1, $product->getVirtual());
    }

    public function testDeleteProductRemovesFromDatabase(): void
    {
        $this->useTransaction = false;

        $category = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();

        $createEvent = new ProductCreateEvent();
        $createEvent
            ->setRef('TO-DELETE')
            ->setTitle('To Delete')
            ->setLocale('en_US')
            ->setDefaultCategory($category->getId())
            ->setBasePrice(1.0)
            ->setCurrencyId($currency->getId())
            ->setTaxRuleId($taxRule->getId());

        $this->dispatcher->dispatch($createEvent, TheliaEvents::PRODUCT_CREATE);
        $productId = $createEvent->getProduct()->getId();

        $deleteEvent = new ProductDeleteEvent($productId);
        $this->dispatcher->dispatch($deleteEvent, TheliaEvents::PRODUCT_DELETE);

        self::assertNull(ProductQuery::create()->findPk($productId));

        // PSEs should be cascade-deleted
        $remainingPse = ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->count();
        self::assertSame(0, $remainingPse);
    }

    public function testUpdateStoresVirtualDocumentOfTheDefaultSaleElement(): void
    {
        $product = $this->createVirtualProduct();
        $document = $this->createDocument($product);

        $this->dispatcher->dispatch(
            $this->virtualDocumentUpdateEvent($product, $document->getId()),
            TheliaEvents::PRODUCT_UPDATE,
        );

        self::assertSame(
            $document->getId(),
            $this->defaultSaleElement($product)->getVirtualDocument()?->getId(),
        );
    }

    public function testUpdateRemovesVirtualDocumentWhenNoneIsSelected(): void
    {
        $product = $this->createVirtualProduct();
        $document = $this->createDocument($product);
        $defaultPse = $this->defaultSaleElement($product);

        $defaultPse->setVirtualDocument($document->getId());

        $this->dispatcher->dispatch(
            $this->virtualDocumentUpdateEvent($product, 0),
            TheliaEvents::PRODUCT_UPDATE,
        );

        self::assertNull($defaultPse->getVirtualDocument());
    }

    public function testUpdateKeepsTheAssociationsOfAProductWithSeveralCombinations(): void
    {
        $product = $this->createVirtualProduct();
        $document = $this->createDocument($product);
        $defaultPse = $this->defaultSaleElement($product);
        $secondPse = $this->factory->productSaleElement($product);

        $defaultPse->setVirtualDocument($document->getId());
        $secondPse->setVirtualDocument($document->getId());

        // -1 is what the back office sends when the association is managed per combination.
        $this->dispatcher->dispatch(
            $this->virtualDocumentUpdateEvent($product, -1),
            TheliaEvents::PRODUCT_UPDATE,
        );

        self::assertSame($document->getId(), $defaultPse->getVirtualDocument()?->getId());
        self::assertSame($document->getId(), $secondPse->getVirtualDocument()?->getId());
    }

    private function createVirtualProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
    }

    private function createDocument(Product $product): ProductDocument
    {
        $document = new ProductDocument();
        $document
            ->setProductId($product->getId())
            ->setFile('handbook.pdf')
            ->setVisible(0)
            ->setPosition(1)
            ->save();

        return $document;
    }

    private function defaultSaleElement(Product $product): ProductSaleElements
    {
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        self::assertNotNull($defaultPse);

        return $defaultPse;
    }

    private function virtualDocumentUpdateEvent(Product $product, int $virtualDocumentId): ProductUpdateEvent
    {
        $event = new ProductUpdateEvent($product->getId());
        $event
            ->setLocale('en_US')
            ->setRef($product->getRef())
            ->setTitle('Digital handbook')
            ->setDefaultCategory($product->getDefaultCategoryId())
            ->setVisible(true)
            ->setVirtual(true)
            ->setVirtualDocumentId($virtualDocumentId);

        return $event;
    }

    public function testUpdatePersistsGuestCheckoutForbiddenInTheSameSave(): void
    {
        $product = $this->createVirtualProduct();
        self::assertSame(0, $product->getGuestCheckoutForbidden());
        $versionBefore = $product->getVersion();

        // The title also changes here, so a version bump proves whether the
        // flag was folded into Action\Product::update()'s own save() (one
        // bump) or persisted by a save of its own beforehand (two bumps).
        $event = $this->basicUpdateEvent($product, 'Guest checkout forbidden probe');
        $event->setGuestCheckoutForbidden(true);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->getGuestCheckoutForbidden());
        self::assertSame(
            $versionBefore + 1,
            $reloaded->getVersion(),
            'A single form save must produce a single version, not one per persisted field.',
        );
    }

    public function testUpdateWithoutGuestCheckoutForbiddenLeavesTheColumnIntact(): void
    {
        $product = $this->createVirtualProduct();
        $product->setGuestCheckoutForbidden(1)->save();

        // No setGuestCheckoutForbidden() call: the event does not carry the field.
        $event = $this->basicUpdateEvent($product, 'Untouched flag probe');

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->getGuestCheckoutForbidden(), 'A caller that omits the field must not clear it.');
    }

    public function testUpdatePersistsTheShippingRulesOfTheProduct(): void
    {
        $product = $this->createVirtualProduct();

        $event = $this->basicUpdateEvent($product, 'Shipping rules probe')
            ->setShippingSurcharge(12.5)
            ->setShippingSurchargeMode(ShippingSurchargeMode::PerUnit)
            ->setFreeShipping(true);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(12.5, (float) $reloaded->getShippingSurcharge(), 0.000001);
        self::assertSame(ShippingSurchargeMode::PerUnit->value, $reloaded->getShippingSurchargeMode());
        self::assertSame(1, $reloaded->getFreeShipping());
    }

    public function testUpdateWithoutTheShippingRulesLeavesThemIntact(): void
    {
        $product = $this->createVirtualProduct();
        $product->setShippingSurcharge('8')->setShippingSurchargeMode(ShippingSurchargeMode::PerUnit->value)->setFreeShipping(1)->save();

        // A save from another tab of the sheet does not carry the rules.
        $this->dispatcher->dispatch($this->basicUpdateEvent($product, 'Untouched rules probe'), TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(8.0, (float) $reloaded->getShippingSurcharge(), 0.000001, 'A caller that omits the surcharge must not clear it.');
        self::assertSame(ShippingSurchargeMode::PerUnit->value, $reloaded->getShippingSurchargeMode());
        self::assertSame(1, $reloaded->getFreeShipping());
    }

    public function testACloneShipsUnderTheRulesOfItsSource(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['title' => 'Bulky parcel']);
        $product->setShippingSurcharge('4')->setShippingSurchargeMode(ShippingSurchargeMode::PerUnit->value)->setFreeShipping(1)->save();
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne();
        self::assertNotNull($carrier, 'No delivery module installed - run bin/test-prepare.');
        (new ProductExcludedDeliveryModule())->setProductId($product->getId())->setModuleId($carrier->getId())->save();

        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clone = ProductQuery::create()->findPk($event->getClonedProduct()->getId());
        self::assertNotNull($clone);
        self::assertEqualsWithDelta(4.0, (float) $clone->getShippingSurcharge(), 0.000001);
        self::assertSame(ShippingSurchargeMode::PerUnit->value, $clone->getShippingSurchargeMode());
        self::assertSame(1, $clone->getFreeShipping());
        self::assertTrue(
            ProductExcludedDeliveryModuleQuery::create()->filterByProductId($clone->getId())->filterByModuleId($carrier->getId())->exists(),
            'The clone refuses the carriers its source refuses.',
        );
    }

    public function testUpdateRollsBackGuestCheckoutForbiddenWhenTheSaveFails(): void
    {
        $productA = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['ref' => 'DUP-REF-A'],
        );
        $productB = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['ref' => 'DUP-REF-B'],
        );
        self::assertSame(0, $productB->getGuestCheckoutForbidden());

        // Reusing productA's ref trips the ref_UNIQUE constraint, so save()
        // throws mid-transaction.
        $event = $this->basicUpdateEvent($productB, 'Rollback probe');
        $event->setRef($productA->getRef());
        $event->setGuestCheckoutForbidden(true);

        $caught = null;

        try {
            $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);
        } catch (\Throwable $exception) {
            $caught = $exception;
        }

        self::assertNotNull($caught, 'A duplicate ref must make the save fail.');

        $reloaded = ProductQuery::create()->findPk($productB->getId());
        self::assertNotNull($reloaded);
        self::assertSame('DUP-REF-B', $reloaded->getRef(), 'The ref change must have rolled back too.');
        self::assertSame(
            0,
            $reloaded->getGuestCheckoutForbidden(),
            'A rolled-back save must not leave the flag written.',
        );
    }

    private function basicUpdateEvent(Product $product, string $title): ProductUpdateEvent
    {
        $event = new ProductUpdateEvent($product->getId());
        $event
            ->setLocale('en_US')
            ->setRef($product->getRef())
            ->setTitle($title)
            ->setDefaultCategory($product->getDefaultCategoryId())
            ->setVisible(true)
            ->setVirtual(true);

        return $event;
    }
}
