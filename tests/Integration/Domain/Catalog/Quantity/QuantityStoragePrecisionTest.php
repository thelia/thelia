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

use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A quantity is read back through the models as it was written, thousandths included. A
 * single precision column gave six significant digits back: 1234.567 came out 1234.57.
 */
final class QuantityStoragePrecisionTest extends ActionIntegrationTestCase
{
    private const QUANTITY = 1234.567;

    public function testAStockKeepsItsThousandthsAboveAThousand(): void
    {
        $productSaleElements = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency())
            ->getDefaultSaleElements();
        $productSaleElements->setQuantity(self::QUANTITY)->save($this->getPropelConnection());

        ProductSaleElementsTableMap::clearInstancePool();
        $readBack = ProductSaleElementsQuery::create()->findPk($productSaleElements->getId(), $this->getPropelConnection());

        self::assertSame(self::QUANTITY, round((float) $readBack?->getQuantity(), 6));
    }

    public function testACartLineKeepsItsThousandthsAboveAThousand(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $line = $this->factory->cartItem($this->factory->cart(null, ['currency' => $currency]), $product, $product->getDefaultSaleElements());
        $line->setQuantity(self::QUANTITY)->save($this->getPropelConnection());

        CartItemTableMap::clearInstancePool();
        $readBack = CartItemQuery::create()->findPk($line->getId(), $this->getPropelConnection());

        self::assertSame(self::QUANTITY, round((float) $readBack?->getQuantity(), 6));
    }

    public function testAnOrderLineKeepsItsThousandthsAboveAThousand(): void
    {
        $line = $this->factory->orderProduct($this->factory->order(), ['quantity' => self::QUANTITY]);

        OrderProductTableMap::clearInstancePool();
        $readBack = OrderProductQuery::create()->findPk($line->getId(), $this->getPropelConnection());

        self::assertSame(self::QUANTITY, round((float) $readBack?->getQuantity(), 6));
    }
}
