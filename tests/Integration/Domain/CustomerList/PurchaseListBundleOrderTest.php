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

namespace Thelia\Tests\Integration\Domain\CustomerList;

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * A list made of an order that holds a bundle takes the bundle, not its components: the
 * components come back with the bundle, ordering them again on top would double them.
 */
final class PurchaseListBundleOrderTest extends IntegrationTestCase
{
    use BuildsBundles;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAnOrderedBundleIsTakenWithoutItsComponents(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $alone = $this->component($this->factory, '10', 5);
        $order = $this->factory->order($customer);

        $bundleLine = $this->line((int) $order->getId(), $example['bundle'], OrderProduct::LINE_TYPE_BUNDLE, 2.0);
        $this->line((int) $order->getId(), $example['bikeProduct'], OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 2.0, (int) $bundleLine->getId());
        $this->line((int) $order->getId(), $example['helmetProduct'], OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 4.0, (int) $bundleLine->getId());
        $this->line((int) $order->getId(), $alone, OrderProduct::LINE_TYPE_PRODUCT, 3.0);

        $list = $this->getService(PurchaseListFacade::class)->createFromOrder($customer, (int) $order->getId(), 'Again');

        $stored = [];

        foreach (CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->orderByPosition()->find() as $item) {
            $stored[] = [(int) $item->getProductSaleElementsId(), (int) $item->getQuantity()];
        }

        self::assertSame([
            [(int) $example['bundle']->getDefaultSaleElements()->getId(), 2],
            [(int) $alone->getDefaultSaleElements()->getId(), 3],
        ], $stored);
    }

    private function line(int $orderId, Product $product, string $lineType, float $quantity, int $parent = 0): OrderProduct
    {
        $saleElements = $product->getDefaultSaleElements();
        \assert($saleElements instanceof ProductSaleElements);

        $line = (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($saleElements->getRef())
            ->setProductSaleElementsId($saleElements->getId())
            ->setTitle('Ordered line')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setLineType($lineType)
            ->setParent(0 === $parent ? null : $parent);
        $line->save($this->getPropelConnection());

        return $line;
    }
}
