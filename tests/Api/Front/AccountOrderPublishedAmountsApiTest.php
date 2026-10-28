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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Api\Service\DataAccess\LoopDataAccessService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A theme and an e-mail show the figures of an order and never work one out of two others:
 * the API and the order loop publish every one of them, and publish the same ones, to the
 * cent, whichever rounding mode the order was invoiced with.
 *
 * The order is the one a recap has to carry: a discount, a taxed postage, a line on
 * promotion, a line off promotion and a service line (a gift wrapping) bought in a quantity.
 */
final class AccountOrderPublishedAmountsApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array the transaction rollback cannot reach.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function roundingModeProvider(): array
    {
        return [
            'sum of roundings' => [ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS],
            'rounding of sums' => [ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS],
        ];
    }

    #[DataProvider('roundingModeProvider')]
    public function testTheTotalsOfTheApiAreExactlyThoseOfTheLoop(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        self::assertEqualsWithDelta((float) $loop['TOTAL_AMOUNT'], $data['totalAmountWithoutTaxesWithShipping'], 1e-9, 'Untaxed total, postage included.');
        self::assertEqualsWithDelta((float) $loop['TOTAL_TAX'], $data['totalTaxAmount'], 1e-9, 'Tax of the order.');
        self::assertEqualsWithDelta(round((float) $loop['TOTAL_TAXED_AMOUNT'], 2), $data['totalAmount'], 1e-9, 'Taxed total.');
        self::assertEqualsWithDelta((float) $loop['TOTAL_AMOUNT_AFTER_DISCOUNT'], $data['totalAmountWithoutTaxesAfterDiscount'], 1e-9);
        self::assertEqualsWithDelta((float) $loop['TOTAL_TAXED_AMOUNT_AFTER_DISCOUNT'], $data['totalAmountWithTaxesAfterDiscount'], 1e-9);
        self::assertEqualsWithDelta(round((float) $loop['DISCOUNT_WITHOUT_TAX'], 2), $data['amountDiscountWithoutTaxes'], 1e-9);
        self::assertEqualsWithDelta(round((float) $loop['POSTAGE_UNTAXED'], 2), $data['totalShippingWithoutTaxes'], 1e-9);

        self::assertGreaterThan(0.0, $data['totalTaxAmount']);
    }

    /**
     * What a recap without tax prints: untaxed total + tax = taxed total, and the untaxed
     * goods + the untaxed postage = the untaxed total, to the cent.
     */
    #[DataProvider('roundingModeProvider')]
    public function testTheRecapAddsUpToTheCent(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        self::assertEqualsWithDelta($data['totalAmount'], round($data['totalAmountWithoutTaxesWithShipping'] + $data['totalTaxAmount'], 2), 1e-9);
        self::assertEqualsWithDelta(
            $data['totalAmountWithoutTaxesWithShipping'],
            round($data['totalAmountWithoutTaxesAfterDiscount'] + $data['totalShippingWithoutTaxes'], 2),
            1e-9,
        );
        self::assertEqualsWithDelta(
            round((float) $loop['TOTAL_TAXED_AMOUNT'], 2),
            round((float) $loop['TOTAL_AMOUNT'] + (float) $loop['TOTAL_TAX'], 2),
            1e-9,
            'The loop carries the same identity.',
        );
    }

    #[DataProvider('roundingModeProvider')]
    public function testTheModelPublishesWhatTheApiPublishes(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);

        self::assertEqualsWithDelta($data['totalTaxAmount'], $order->getTotalTaxAmount(), 1e-9);
        self::assertEqualsWithDelta($data['totalAmountWithoutTaxes'], $order->getTotalAmountWithoutTaxes(), 1e-9);
        self::assertEqualsWithDelta($data['totalAmountWithTaxesAfterDiscount'], $order->getTotalAmountWithTaxesAfterDiscount(), 1e-9);
        self::assertEqualsWithDelta($data['totalAmountWithoutTaxesAfterDiscount'], $order->getTotalAmountWithoutTaxesAfterDiscount(), 1e-9);
        self::assertEqualsWithDelta($data['totalAmountWithoutTaxesWithShipping'], $order->getTotalAmountWithoutTaxesWithShipping(), 1e-9);
        self::assertEqualsWithDelta($data['totalShippingWithoutTaxes'], $order->getTotalShippingWithoutTaxes(), 1e-9);
        self::assertEqualsWithDelta($data['amountDiscountWithoutTaxes'], $order->getAmountDiscountWithoutTaxes(), 1e-9);

        $lines = [];
        foreach ($data['orderProducts'] as $line) {
            $lines[$line['productRef']] = $line;
        }

        foreach ($order->getOrderProducts() as $orderProduct) {
            $apiLine = $lines[$orderProduct->getProductRef()];

            self::assertEqualsWithDelta($apiLine['price'], $orderProduct->roundUnitAmount((float) $orderProduct->getPrice()), 1e-9, $orderProduct->getProductRef());
            self::assertEqualsWithDelta($apiLine['unitTaxedPrice'], $orderProduct->roundUnitAmount($orderProduct->getTaxedUnitPrice((bool) $orderProduct->getWasInPromo())), 1e-9, $orderProduct->getProductRef());
        }
    }

    /**
     * The total of a line, a service line included, is published: a theme never multiplies a
     * unit price by a quantity.
     */
    #[DataProvider('roundingModeProvider')]
    public function testEveryLineCarriesItsTotalsAsTheLoopPublishesThem(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 0.0);

        $data = $this->readOwnOrder($customer, $order);
        $loopLines = $this->getService(LoopDataAccessService::class)
            ->theliaLoop('lines', 'order_product', ['order' => $order->getId()]);

        $apiLines = [];
        foreach ($data['orderProducts'] as $line) {
            $apiLines[$line['productRef']] = $line;
        }

        self::assertCount(3, $loopLines);

        foreach ($loopLines as $loopLine) {
            $apiLine = $apiLines[$loopLine['REF']];

            self::assertEqualsWithDelta((float) $loopLine['REAL_TOTAL_PRICE'], $apiLine['lineTotalWithoutTaxes'], 1e-9, $loopLine['REF']);
            self::assertEqualsWithDelta((float) $loopLine['REAL_TOTAL_TAXED_PRICE'], $apiLine['lineTotalWithTaxes'], 1e-9, $loopLine['REF']);
        }

        $service = $apiLines['GIFT-WRAP'];
        self::assertSame('service', $service['lineType']);

        // 3 x 4.166667 untaxed, taxed 5 a unit: 12.51 with the unit cut to the cent, 12.5 otherwise.
        self::assertEqualsWithDelta(
            ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode ? 12.5 : 12.51,
            $service['lineTotalWithoutTaxes'],
            1e-9,
        );
        self::assertEqualsWithDelta(15.0, $service['lineTotalWithTaxes'], 1e-9);

        // The line on promotion is read on its promotional price: 3 x 33.333.
        self::assertEqualsWithDelta(
            ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode ? 100.0 : 99.99,
            $apiLines['PROMO-LINE']['lineTotalWithoutTaxes'],
            1e-9,
        );
    }

    /**
     * The articles of the recap leave the service line out: the sum of the lines that are not a
     * service, which the API, the loop and the model all publish, in either rounding mode.
     */
    #[DataProvider('roundingModeProvider')]
    public function testTheGoodsTotalIsTheSumOfTheLinesThatAreNotAService(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        $untaxed = 0.0;
        $taxed = 0.0;
        foreach ($data['orderProducts'] as $line) {
            if ('GIFT-WRAP' === $line['productRef']) {
                continue;
            }

            $untaxed += $line['lineTotalWithoutTaxes'];
            $taxed += $line['lineTotalWithTaxes'];
        }

        self::assertEqualsWithDelta(round($untaxed, 2), $data['totalGoodsWithoutTaxes'], 1e-9);
        self::assertEqualsWithDelta(round($taxed, 2), $data['totalGoodsWithTaxes'], 1e-9);
        self::assertEqualsWithDelta($data['totalGoodsWithoutTaxes'], (float) $loop['TOTAL_GOODS_AMOUNT'], 1e-9);
        self::assertEqualsWithDelta($data['totalGoodsWithTaxes'], (float) $loop['TOTAL_TAXED_GOODS_AMOUNT'], 1e-9);
        self::assertEqualsWithDelta($data['totalGoodsWithoutTaxes'], $order->getTotalGoodsWithoutTaxes(), 1e-9);
        self::assertEqualsWithDelta($data['totalGoodsWithTaxes'], $order->getTotalGoodsWithTaxes(), 1e-9);
        self::assertLessThan($data['totalAmountWithoutTaxes'], $data['totalGoodsWithoutTaxes'], 'The service line is in totalAmountWithoutTaxes only.');
    }

    /**
     * The four figures a recap prints with their tax (what is paid, the discount, the postage and
     * its tax) are read from the model, which rounds them: the API only calls it.
     */
    #[DataProvider('roundingModeProvider')]
    public function testTheModelGivesTheFiguresOfTheApiTaxIncluded(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithEveryKindOfLine($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);

        self::assertEqualsWithDelta($data['totalAmount'], $order->getTotalAmountWithTaxesWithShipping(), 1e-9);
        self::assertEqualsWithDelta($data['amountDiscountWithTaxes'], $order->getAmountDiscountWithTaxes(), 1e-9);
        self::assertEqualsWithDelta($data['totalShippingWithTaxes'], $order->getTotalShippingWithTaxes(), 1e-9);
        self::assertEqualsWithDelta($data['postageTax'], $order->getTotalShippingTaxAmount(), 1e-9);
        self::assertEqualsWithDelta(12.0, $order->getTotalShippingWithTaxes(), 1e-9);
        self::assertEqualsWithDelta(2.0, $order->getTotalShippingTaxAmount(), 1e-9);
        self::assertEqualsWithDelta(15.0, $order->getAmountDiscountWithTaxes(), 1e-9);

        // The recap adds up: what is paid is the goods after the discount and the postage.
        self::assertEqualsWithDelta(
            $order->getTotalAmountWithTaxesWithShipping(),
            round($order->getTotalAmountWithTaxesAfterDiscount() + $order->getTotalShippingWithTaxes(), 2),
            1e-9,
        );
        // And the untaxed counterpart: the two postage figures give the tax of the postage.
        self::assertEqualsWithDelta(
            $order->getTotalShippingTaxAmount(),
            round($order->getTotalShippingWithTaxes() - $order->getTotalShippingWithoutTaxes(), 2),
            1e-9,
        );
    }

    /**
     * A collection of orders prints both goods totals for each order: the service lines are read once
     * for the two, not once for each.
     */
    public function testTheTwoGoodsTotalsReadTheServiceLinesOnce(): void
    {
        $order = $this->orderWithEveryKindOfLine($this->newCustomer(), discount: 0.0);
        OrderTableMap::clearInstancePool();
        $fresh = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($fresh);

        $statements = $this->recordSqlQueries(static function () use ($fresh): void {
            $fresh->getTotalGoodsWithoutTaxes();
            $fresh->getTotalGoodsWithTaxes();
        });

        $serviceLineReads = array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'line_type'));
        self::assertCount(1, $serviceLineReads, implode(' | ', $statements));

        // Held by the instance, and forgotten when the order is reloaded: the lines may have moved.
        $again = $this->recordSqlQueries(static function () use ($fresh): void {
            $fresh->getTotalGoodsWithoutTaxes();
        });
        self::assertCount(0, array_filter($again, static fn (string $statement): bool => str_contains($statement, 'line_type')));

        $fresh->reload();
        $afterReload = $this->recordSqlQueries(static function () use ($fresh): void {
            $fresh->getTotalGoodsWithoutTaxes();
        });
        self::assertCount(1, array_filter($afterReload, static fn (string $statement): bool => str_contains($statement, 'line_type')), 'a reloaded order reads its service lines again');
    }

    public function testTheGoodsTotalOfAnOrderIsNeverThatOfAnotherCustomer(): void
    {
        $owner = $this->newCustomer();
        $other = $this->newCustomer();
        $ownerOrder = $this->orderWithEveryKindOfLine($owner, discount: 15.0);
        $otherOrder = $this->orderWithEveryKindOfLine($other, discount: 30.0);
        $this->line($otherOrder, 'EXTRA-LINE', price: '20.000000', promoPrice: '0.000000', tax: '4.000000', promoTax: '0.000000', quantity: 1.0, onPromo: false);

        $ownerData = $this->readOwnOrder($owner, $ownerOrder);
        $otherData = $this->readOwnOrder($other, $otherOrder);

        self::assertNotEquals($ownerData['totalGoodsWithoutTaxes'], $otherData['totalGoodsWithoutTaxes']);
        self::assertEqualsWithDelta($ownerData['totalGoodsWithoutTaxes'] + 20.0, $otherData['totalGoodsWithoutTaxes'], 1e-9);

        $denied = $this->jsonRequest('GET', '/api/front/account/orders/'.$ownerOrder->getId(), token: $this->authenticateAsCustomer($other));

        self::assertSame(403, $denied->getStatusCode());
        self::assertStringNotContainsString('totalGoods', (string) $denied->getContent());
    }

    public function testEveryCustomerReadsTheTotalsOfHisOwnOrderOnly(): void
    {
        $owner = $this->newCustomer();
        $other = $this->newCustomer();
        $ownerOrder = $this->orderWithEveryKindOfLine($owner, discount: 15.0);
        $otherOrder = $this->orderWithEveryKindOfLine($other, discount: 30.0);

        $ownerData = $this->readOwnOrder($owner, $ownerOrder);
        $otherData = $this->readOwnOrder($other, $otherOrder);

        self::assertNotEquals($ownerData['totalAmountWithoutTaxesWithShipping'], $otherData['totalAmountWithoutTaxesWithShipping']);
        self::assertNotEquals($ownerData['totalTaxAmount'], $otherData['totalTaxAmount']);

        $denied = $this->jsonRequest('GET', '/api/front/account/orders/'.$ownerOrder->getId(), token: $this->authenticateAsCustomer($other));

        self::assertSame(403, $denied->getStatusCode());
        foreach (['totalAmountWithoutTaxesWithShipping', 'lineTotalWithoutTaxes', 'lineTotalWithTaxes'] as $field) {
            self::assertStringNotContainsString($field, (string) $denied->getContent());
        }

        self::assertEqualsWithDelta(
            $otherData['totalAmount'],
            round($otherData['totalAmountWithoutTaxesWithShipping'] + $otherData['totalTaxAmount'], 2),
            1e-9,
        );
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), ['password' => 'password']);
    }

    /**
     * Two lines taxed at 20 %: 2 x 30 + 6 untaxed, off promotion; 3 x (49.999 normally, 33.333 on
     * promotion) whose promotional tax is 6.6666 a unit; and a gift wrapping, a service line,
     * 3 x 4.166667 with 0.833333 of tax. Postage 12, 2 of it tax. The prices are chosen so that
     * the two rounding modes give different amounts.
     */
    private function orderWithEveryKindOfLine(Customer $customer, float $discount): Order
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($customer, ['statusCode' => 'paid', 'postage' => 12, 'postageTax' => 2]);

        $this->line($order, 'FULL-LINE', price: '50.000000', promoPrice: '45.000000', tax: '10.000000', promoTax: '9.000000', quantity: 2.0, onPromo: false);
        $this->line($order, 'PROMO-LINE', price: '49.999000', promoPrice: '33.333000', tax: '10.000000', promoTax: '6.666600', quantity: 3.0, onPromo: true);
        $this->line($order, 'GIFT-WRAP', price: '4.166667', promoPrice: '0.000000', tax: '0.833333', promoTax: '0.000000', quantity: 3.0, onPromo: false, lineType: OrderProduct::LINE_TYPE_SERVICE);

        $order->setDiscount((string) $discount)->save($this->getPropelConnection());

        return $order;
    }

    private function line(Order $order, string $ref, string $price, string $promoPrice, string $tax, string $promoTax, float $quantity, bool $onPromo, string $lineType = OrderProduct::LINE_TYPE_PRODUCT): void
    {
        $connection = $this->getPropelConnection();

        $line = $this->createFixtureFactory()->orderProduct($order, ['productRef' => $ref, 'price' => $price, 'promoPrice' => $promoPrice, 'quantity' => $quantity, 'lineType' => $lineType]);
        $line->setWasInPromo($onPromo ? 1 : 0)->save($connection);

        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT')
            ->setAmount($tax)
            ->setPromoAmount($promoTax)
            ->save($connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function readOwnOrder(Customer $customer, Order $order): array
    {
        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderLoopRow(Order $order): array
    {
        $rows = $this->getService(LoopDataAccessService::class)
            ->theliaLoop('order', 'order', ['id' => $order->getId(), 'customer' => '*', 'status' => '*']);

        self::assertCount(1, $rows);

        return $rows[0];
    }
}
