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

use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Service\OrderEditor;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

final class OrderEditorTaxFactorTest extends IntegrationTestCase
{
    public function testTheDiscountTaxReadRightAfterAnEditFollowsTheEditedLinesNotTheFactorCachedBeforeIt(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->aLine($order, $this->aProduct('Widget'), 2.0, '10.000000', '2.000000');
        $reducedRateLine = $this->aLine($order, $this->aProduct('Book'), 1.0, '10.000000', '0.550000');

        $order->setDiscount('5.000000')->save();

        // The caller reads the order total, discount included, before editing it (a CLI script, the e-mail of a previous edit).
        $tax = 0;
        Order::forgetCachedTotals((int) $order->getId());
        $order->getTotalAmount($tax, false, true);

        $this->getService(OrderEditor::class)->edit($order, new OrderEdit(removedLines: [(int) $reducedRateLine->getId()]));

        // Only the 20 % line is left: the discount of 5.00 holds 5 / 1.2 untaxed, the order tax is 4.00 - (5.00 - 5 / 1.2).
        $taxAfter = 0;
        $order->getTotalAmount($taxAfter, false, true);

        self::assertEqualsWithDelta(5 / 1.2, $order->getUntaxedDiscount(), 0.0001, 'The tax factor of the discount must be read again on the lines the edit left.');
        self::assertEqualsWithDelta(4.0 - (5.0 - 5 / 1.2), $taxAfter, 0.0001);
    }

    private function aProduct(string $title): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(['isDefault' => false]), $factory->currency(), ['title' => $title, 'baseQuantity' => 10]);
    }

    private function aLine(Order $order, Product $product, float $quantity, string $price, string $unitTax): OrderProduct
    {
        $pse = $product->getDefaultSaleElements();

        $line = $this->createFixtureFactory()->orderProduct($order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $pse->getRef(),
            'productSaleElementsId' => $pse->getId(),
            'quantity' => $quantity,
            'price' => $price,
            'promoPrice' => $price,
        ]);

        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT')
            ->setAmount($unitTax)
            ->setPromoAmount($unitTax)
            ->save($this->getPropelConnection());

        return $line;
    }
}
