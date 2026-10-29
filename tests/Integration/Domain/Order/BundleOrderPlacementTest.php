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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\PlacesBundleOrders;

/**
 * What an order made of packs is written as: the line of the pack, one line per component
 * under it, the stock taken from the components and never from the pack, and a check of the
 * stock that adds up what the whole cart asks of each sale element.
 *
 * The pack of the plan: bike (1, required, stock 7), helmet (2, required, stock 5) and lock
 * (1, optional from 0 to 3, stock 10). The stock is taken when the order is placed, as a
 * payment module that takes the money on the spot asks for.
 */
final class BundleOrderPlacementTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use PlacesBundleOrders;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check_available_stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::resetCache();

        $this->manageTheStockWhenTheOrderIsPlaced();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testOrderingAFixedBundleTakesItsComponentsFromTheStockAndNeverTheBundle(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $first = $this->component($this->factory, '10', 4);
        $second = $this->component($this->factory, '10', 4);
        $third = $this->component($this->factory, '10', 4);
        $bundle = $this->bundleProduct($this->factory, $service, BundlePriceMode::Fixed, '0', \Thelia\Domain\Catalog\Bundle\BundleDiscountType::Percent, 25.0);
        $items = [
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($first), '1'),
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($second), '1'),
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($third), '2'),
        ];

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $bundle, [(int) $items[0]->getId() => '1', (int) $items[1]->getId() => '1', (int) $items[2]->getId() => '2'], 2.0, '25.000000', '25.000000');

        $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame(2.0, $this->stockOfProduct($first));
        self::assertSame(2.0, $this->stockOfProduct($second));
        self::assertSame(0.0, $this->stockOfProduct($third), 'Two packs of two units each: four taken out of four.');
        self::assertSame(0.0, $this->stockOfProduct($bundle), 'The sale element of the pack holds no stock and is never decremented.');
    }

    public function testTheLinesOfAnOrderedBundle(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        $bundle = $example['bundle'];
        $bundle->setTaxRuleId($this->taxRuleTaxingAt('20')->getId())->save();
        $bundleSaleElements = ProductSaleElementsQuery::create()->filterByProductId($bundle->getId())->findOne();
        $bundleSaleElements->setWeight(0)->save();
        $example['bikeProduct']->getDefaultSaleElements()->setWeight(12.5)->save();
        $example['helmetProduct']->getDefaultSaleElements()->setWeight(0.5)->save();
        $example['lockProduct']->getDefaultSaleElements()->setWeight(1)->save();

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $bundle, [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '2'], 2.0);

        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        $lines = $this->linesOf($order);

        self::assertCount(4, $lines, 'The pack and its three components.');

        [$bundleLine, $bikeLine, $helmetLine, $lockLine] = $lines;

        self::assertSame(OrderProduct::LINE_TYPE_BUNDLE, $bundleLine->getLineType());
        self::assertTrue($bundleLine->isBundleLine());
        self::assertNull($bundleLine->getParent());
        self::assertSame($bundle->getRef(), $bundleLine->getProductRef());
        self::assertSame(2.0, (float) $bundleLine->getQuantity());
        self::assertSame(600.0, (float) $bundleLine->getPrice(), 'The price of the cart line, copied.');
        self::assertSame(540.0, (float) $bundleLine->getPromoPrice());
        self::assertSame(1, (int) $bundleLine->getWasInPromo());
        self::assertSame(0.0, (float) $bundleLine->getWeight(), 'A pack with no weight of its own weighs nothing: its components do.');
        self::assertGreaterThan(0, OrderProductTaxQuery::create()->filterByOrderProductId($bundleLine->getId())->count(), 'The pack carries the tax of its own tax rule.');

        foreach ([[$bikeLine, $example['bikeProduct'], 2.0, 12.5], [$helmetLine, $example['helmetProduct'], 4.0, 0.5], [$lockLine, $example['lockProduct'], 4.0, 1.0]] as [$line, $product, $quantity, $weight]) {
            self::assertSame(OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, $line->getLineType());
            self::assertTrue($line->isBundleComponentLine());
            self::assertSame($bundleLine->getId(), $line->getParent(), 'A component names the line of its pack.');
            self::assertSame($product->getRef(), $line->getProductRef());
            self::assertSame($this->saleElementsIdOf($product), $line->getProductSaleElementsId());
            self::assertSame($quantity, (float) $line->getQuantity(), 'The quantity of the component times the quantity of the pack.');
            self::assertSame(0.0, (float) $line->getPrice());
            self::assertSame(0.0, (float) $line->getPromoPrice());
            self::assertSame(0, (int) $line->getWasInPromo());
            self::assertSame($weight, (float) $line->getWeight());
            self::assertSame(0, OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->count(), 'A component carries no tax.');
            self::assertNotEmpty($line->getTitle());
        }

        self::assertEqualsWithDelta(2 * 12.5 + 4 * 0.5 + 4 * 1.0, $order->getWeight(), 0.0001, 'The weight of the order counts each component once.');
        $tax = 0.0;
        self::assertEqualsWithDelta(2 * 540.0 * 1.2, $order->getTotalAmount($tax), 0.01, 'Only the pack is charged, at the rate of its tax rule.');
        self::assertEqualsWithDelta(2 * 540.0 * 0.2, $tax, 0.01);
    }

    public function testABundleWithAWeightOfItsOwnIsNotWeighedAgainThroughItsComponents(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        ProductSaleElementsQuery::create()->filterByProductId($example['bundle']->getId())->findOne()->setWeight(20)->save();
        $example['bikeProduct']->getDefaultSaleElements()->setWeight(12.5)->save();
        $example['helmetProduct']->getDefaultSaleElements()->setWeight(0.5)->save();

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2'], 2.0);

        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        [$bundleLine, $bikeLine, $helmetLine] = $this->linesOf($order);

        self::assertSame(20.0, (float) $bundleLine->getWeight());
        self::assertSame(0.0, (float) $bikeLine->getWeight());
        self::assertSame(0.0, (float) $helmetLine->getWeight());
        self::assertEqualsWithDelta(2 * 20.0, $order->getWeight(), 0.0001, 'The weight of the pack, once.');
    }

    public function testTheExampleOfThePlanTakesSevenFiveTenDownToSixThreeEight(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '2']);

        $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame(6.0, $this->stockOfProduct($example['bikeProduct']));
        self::assertSame(3.0, $this->stockOfProduct($example['helmetProduct']));
        self::assertSame(8.0, $this->stockOfProduct($example['lockProduct']));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));
    }

    public function testAnOptionalComponentTheBuyerLeftOutIsNotOrdered(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertCount(3, $this->linesOf($order), 'The pack, the bike and the helmets: the lock was not chosen.');
        self::assertSame(10.0, $this->stockOfProduct($example['lockProduct']));
    }

    public function testTheShortageOfAComponentRefusesTheOrderAndNamesTheComponent(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        $example['helmetProduct']->getDefaultSaleElements()->setQuantity(3)->save();

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        // Two packs ask for four helmets; three are left.
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2'], 2.0);

        try {
            $this->placeOrder($cart, $customer, $currency, $lang);
            self::fail('Two packs need four helmets and three are left.');
        } catch (StockShortageException $shortage) {
            self::assertSame($example['helmetProduct']->getRef(), $shortage->productReference, 'The shortage names the component, not the pack.');
        }

        self::assertSame(7.0, $this->stockOfProduct($example['bikeProduct']), 'Nothing was taken from the components that were there.');
    }

    public function testTheStockOfTheBundleItselfIsNeverRead(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        ProductSaleElementsQuery::create()->filterByProductId($example['bundle']->getId())->findOne()->setQuantity(0)->save();

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertNotNull($order->getId(), 'A pack whose own sale element is at zero is ordered all the same: the stock is the one of its components.');
    }

    public function testABundleAndItsComponentSoldAloneAreCheckedAgainstTheSameStock(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        // The pack takes two helmets, the helmet bought on its own four: six against a stock of five.
        // Each line, read alone, is within the stock.
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);
        $this->addSimpleLine($cart, $example['helmetProduct'], 4.0);

        try {
            $this->placeOrder($cart, $customer, $currency, $lang);
            self::fail('Six helmets are asked for, five are in stock.');
        } catch (StockShortageException $shortage) {
            self::assertSame($example['helmetProduct']->getRef(), $shortage->productReference);
        }

        self::assertSame(7.0, $this->stockOfProduct($example['bikeProduct']), 'The shortage is found before anything is taken.');
        self::assertSame(5.0, $this->stockOfProduct($example['helmetProduct']));
    }

    public function testABundleAndItsComponentSoldAloneThatFitTheStockTogetherAreBothTaken(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);
        $this->addSimpleLine($cart, $example['helmetProduct'], 3.0);

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame(0.0, $this->stockOfProduct($example['helmetProduct']), 'Two in the pack, three alone: five out of five.');
        self::assertSame(['bundle', 'bundle_component', 'bundle_component', 'product'], array_map(static fn (OrderProduct $line): string => $line->getLineType(), $this->linesOf($order)));
    }

    public function testABundleLineAPromotionOfferedIsOrderedWithItsDefaultComposition(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        // The line a coupon offers is placed with no composition to choose.
        (new \Thelia\Model\CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsIdOf($example['bundle']))
            ->setQuantity(1.0)
            ->setPrice('0.000000')
            ->setPromoPrice('0.000000')
            ->setIsOffered(1)
            ->save();

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame(['bundle', 'bundle_component', 'bundle_component'], array_map(static fn (OrderProduct $line): string => $line->getLineType(), $this->linesOf($order)), 'The default composition holds the bike and the helmets, not the optional lock.');
        self::assertSame(6.0, $this->stockOfProduct($example['bikeProduct']));
        self::assertSame(3.0, $this->stockOfProduct($example['helmetProduct']));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));
    }

    public function testAnOrderWithoutAnyBundleIsWrittenAsBefore(): void
    {
        $product = $this->component($this->factory, '10', 5);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $product, 2.0, '12.500000');

        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        $lines = $this->linesOf($order);

        self::assertCount(1, $lines);
        self::assertSame(OrderProduct::LINE_TYPE_PRODUCT, $lines[0]->getLineType());
        self::assertNull($lines[0]->getParent());
        self::assertSame(12.5, (float) $lines[0]->getPrice());
        self::assertSame(2.0, (float) $lines[0]->getQuantity());
        self::assertSame(3.0, $this->stockOfProduct($product));
        self::assertTrue($lines[0]->isProductLine());
        self::assertTrue($lines[0]->isShippableLine());
    }

    public function testACompositionNamingAnItemTheBundleNoLongerHasRefusesTheOrder(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);
        $service->removeItem((int) $example['helmet']->getId());

        $this->expectException(\Thelia\Domain\Order\Exception\BundleLineNotOrderableException::class);

        $this->placeOrder($cart, $customer, $currency, $lang);
    }

    private function manageTheStockWhenTheOrderIsPlaced(): void
    {
        $listener = static fn (\Thelia\Core\Event\Payment\ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $eventName = \Thelia\Core\Event\TheliaEvents::getModuleEvent(\Thelia\Core\Event\TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');

        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
