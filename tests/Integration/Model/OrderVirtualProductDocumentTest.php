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

namespace Thelia\Tests\Integration\Model;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Test\IntegrationTestCase;

/**
 * A virtual product is not necessarily downloadable: a subscription sold as a virtual
 * product carries no virtual document. Order::hasVirtualProduct() stays broad for the
 * delivery modules that do not use the core document mechanism, while
 * Order::hasVirtualProductWithDocument() answers the question the download features ask.
 */
final class OrderVirtualProductDocumentTest extends IntegrationTestCase
{
    public function testAVirtualProductWithoutDocumentIsNotDownloadable(): void
    {
        $order = $this->createOrderWithVirtualProduct(null);

        self::assertTrue(
            $order->hasVirtualProduct(),
            'The order does contain a virtual product; the broad check must stay true.',
        );
        self::assertFalse(
            $order->hasVirtualProductWithDocument(),
            'Without a virtual document there is nothing to download.',
        );
    }

    public function testAVirtualProductWithADocumentIsDownloadable(): void
    {
        $order = $this->createOrderWithVirtualProduct('subscription-invoice.pdf');

        self::assertTrue($order->hasVirtualProduct());
        self::assertTrue($order->hasVirtualProductWithDocument());
    }

    public function testOrderLoopReportsNoVirtualDownloadWhenTheDocumentIsMissing(): void
    {
        $order = $this->createOrderWithVirtualProduct(null);

        // The order_product loop only returns products having a document: the order loop
        // must not announce a download the product loop will not be able to list.
        self::assertSame(0, $this->getLoopExecutor()->count('order_product', [
            'order' => $order->getId(),
            'virtual' => 1,
        ]));
        self::assertFalse($this->getOrderLoopVirtualFlag($order));
    }

    public function testOrderLoopReportsAVirtualDownloadWhenTheDocumentIsPresent(): void
    {
        $order = $this->createOrderWithVirtualProduct('subscription-invoice.pdf');

        self::assertSame(1, $this->getLoopExecutor()->count('order_product', [
            'order' => $order->getId(),
            'virtual' => 1,
        ]));
        self::assertTrue($this->getOrderLoopVirtualFlag($order));
    }

    private function createOrderWithVirtualProduct(?string $virtualDocument): Order
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('virtual-ref')
            ->setProductSaleElementsRef('virtual-pse-ref')
            ->setTitle('Virtual product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(1)
            ->setVirtualDocument($virtualDocument)
            ->save();

        return $order;
    }

    private function getOrderLoopVirtualFlag(Order $order): bool
    {
        $result = $this->getLoopExecutor()->execute('order', [
            'id' => $order->getId(),
            'customer' => '*',
            'status' => '*',
        ]);

        foreach ($result as $row) {
            return (bool) $row->getVarVal()['VIRTUAL'];
        }

        self::fail('The order loop returned no row for order '.$order->getId().'.');
    }

    private function getLoopExecutor(): LoopExecutor
    {
        return $this->getService(LoopExecutor::class);
    }
}
