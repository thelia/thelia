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

namespace Thelia\Tests\Functional\Action;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
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
}
