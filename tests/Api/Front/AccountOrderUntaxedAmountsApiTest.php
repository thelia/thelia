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
use Thelia\Model\Order;
use Thelia\Model\OrderProductTax;
use Thelia\Test\ApiTestCase;

/**
 * A theme that shows untaxed prices reads the untaxed discount, the untaxed total and the
 * untaxed postage of an order from the API, and never works a tax out itself. They are the
 * figures the order loop publishes, whichever rounding mode the order was invoiced with.
 */
final class AccountOrderUntaxedAmountsApiTest extends ApiTestCase
{
    private const NEW_FIELDS = ['amountDiscountWithoutTaxes', 'totalAmountWithoutTaxesAfterDiscount', 'totalShippingWithoutTaxes', 'totalTaxAmount'];

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
    public function testTheUntaxedAmountsAreTheOnesOfTheOrderLoop(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithDiscountPromoLineAndPostage($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        self::assertEqualsWithDelta(round((float) $loop['DISCOUNT_WITHOUT_TAX'], 2), $data['amountDiscountWithoutTaxes'], 0.0001);
        self::assertEqualsWithDelta(round((float) $loop['POSTAGE_UNTAXED'], 2), $data['totalShippingWithoutTaxes'], 0.0001);
        self::assertEqualsWithDelta(
            round((float) $loop['TOTAL_AMOUNT'], 2),
            round($data['totalAmountWithoutTaxesAfterDiscount'] + $data['totalShippingWithoutTaxes'], 2),
            0.0001,
            'The untaxed total of the loop is the goods after discount and the postage.',
        );

        self::assertEqualsWithDelta(12.5, $data['amountDiscountWithoutTaxes'], 0.01, 'A discount of 15 taxed at 20 %.');
        self::assertGreaterThan(0.0, $data['amountDiscountWithoutTaxes']);
        self::assertLessThan($data['amountDiscountWithTaxes'], $data['amountDiscountWithoutTaxes'], 'The discount was entered taxed: its untaxed part is smaller.');
        self::assertEqualsWithDelta(10.0, $data['totalShippingWithoutTaxes'], 0.0001, 'A postage of 12 with 2 of tax.');
    }

    /**
     * The identity the theme relies on to show the tax of an order: what was charged taxed,
     * less what was charged untaxed, is the tax, discount included.
     */
    #[DataProvider('roundingModeProvider')]
    public function testTheTaxOfTheOrderIsTheGapBetweenItsTaxedAndUntaxedTotals(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithDiscountPromoLineAndPostage($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        self::assertEqualsWithDelta(
            round((float) $loop['TOTAL_TAX'] - (float) $loop['POSTAGE_TAX'], 2),
            round($data['totalAmountWithTaxesAfterDiscount'] - $data['totalAmountWithoutTaxesAfterDiscount'], 2),
            0.0101,
            'Both sides are cut to the cent on their own: they can part by one.',
        );
    }

    /**
     * What the theme prints as the tax of the order is published, not worked out: the untaxed
     * goods, the untaxed postage and the tax make the total the customer paid.
     */
    #[DataProvider('roundingModeProvider')]
    public function testTheUntaxedGoodsThePostageAndTheTaxMakeTheTotalPaid(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithDiscountPromoLineAndPostage($customer, discount: 15.0);

        $data = $this->readOwnOrder($customer, $order);
        $loop = $this->orderLoopRow($order);

        self::assertGreaterThan(0.0, $data['totalTaxAmount']);
        self::assertEqualsWithDelta(
            $data['totalAmount'],
            $data['totalAmountWithoutTaxesAfterDiscount'] + $data['totalShippingWithoutTaxes'] + $data['totalTaxAmount'],
            0.0001,
        );
        self::assertEqualsWithDelta((float) $loop['TOTAL_TAX'], $data['totalTaxAmount'], 0.0101, 'The tax of the order loop, to the cent.');
    }

    #[DataProvider('roundingModeProvider')]
    public function testALineOnPromotionIsTaxedOnItsPromotionalPrice(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $customer = $this->newCustomer();
        $order = $this->orderWithDiscountPromoLineAndPostage($customer, discount: 0.0);

        $data = $this->readOwnOrder($customer, $order);
        $loopLines = $this->getService(LoopDataAccessService::class)
            ->theliaLoop('lines', 'order_product', ['order' => $order->getId()]);

        $apiLines = [];
        foreach ($data['orderProducts'] as $line) {
            $apiLines[$line['productRef']] = $line;
        }

        self::assertCount(2, $loopLines);

        foreach ($loopLines as $loopLine) {
            $apiLine = $apiLines[$loopLine['REF']];

            self::assertEqualsWithDelta((float) $loopLine['REAL_TAXED_PRICE'], $apiLine['unitTaxedPrice'], 0.0001, $loopLine['REF']);
            self::assertEqualsWithDelta((float) $loopLine['TAXED_PROMO_PRICE'], $apiLine['taxedPromoPrice'], 0.0001, $loopLine['REF']);
        }

        // Promotional price 33.333 + promotional tax 6.6666 -- not the 49.999 of the full price.
        $expected = ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode ? 39.9996 : 40.0;
        self::assertEqualsWithDelta($expected, $apiLines['PROMO-LINE']['unitTaxedPrice'], 0.00001);
        self::assertEqualsWithDelta(60.0, $apiLines['FULL-LINE']['unitTaxedPrice'], 0.00001, 'A line off promotion keeps price + tax.');

        foreach ($loopLines as $loopLine) {
            self::assertEqualsWithDelta((float) $loopLine['TAXED_PRICE'], $apiLines[$loopLine['REF']]['unitTaxedPriceBeforePromotion'], 0.0001, $loopLine['REF']);
        }

        // The crossed-out price of the promotional line: 49.999 + 10, whole or cut to the cent.
        $beforePromotion = ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode ? 59.999 : 60.0;
        self::assertEqualsWithDelta($beforePromotion, $apiLines['PROMO-LINE']['unitTaxedPriceBeforePromotion'], 0.00001);
    }

    public function testAnotherCustomerReadsHisOwnAmountsAndNeverThoseOfSomeoneElse(): void
    {
        $owner = $this->newCustomer();
        $intruder = $this->newCustomer();
        $ownerOrder = $this->orderWithDiscountPromoLineAndPostage($owner, discount: 15.0);
        $intruderOrder = $this->orderWithDiscountPromoLineAndPostage($intruder, discount: 30.0);

        $ownerData = $this->readOwnOrder($owner, $ownerOrder);
        $intruderData = $this->readOwnOrder($intruder, $intruderOrder);

        self::assertNotEquals($ownerData['totalTaxAmount'], $intruderData['totalTaxAmount']);
        self::assertNotEquals(
            $ownerData['amountDiscountWithoutTaxes'],
            $intruderData['amountDiscountWithoutTaxes'],
            'Each customer reads the figures of his own order.',
        );

        $denied = $this->jsonRequest('GET', '/api/front/account/orders/'.$ownerOrder->getId(), token: $this->authenticateAsCustomer($intruder));

        self::assertSame(403, $denied->getStatusCode());
        foreach ([...self::NEW_FIELDS, 'unitTaxedPriceBeforePromotion'] as $field) {
            self::assertStringNotContainsString($field, (string) $denied->getContent());
        }

        self::assertEqualsWithDelta(
            $intruderData['totalAmount'],
            $intruderData['totalAmountWithoutTaxesAfterDiscount'] + $intruderData['totalShippingWithoutTaxes'] + $intruderData['totalTaxAmount'],
            0.0001,
        );

        $list = $this->jsonRequest('GET', '/api/front/account/orders', token: $this->authenticateAsCustomer($intruder));
        self::assertJsonResponseSuccessful($list);
        self::assertStringNotContainsString('/api/front/account/orders/'.$ownerOrder->getId().'"', (string) $list->getContent());
        // Priced per order, so a collection leaves them to the single read.
        foreach (self::NEW_FIELDS as $field) {
            self::assertStringNotContainsString($field, (string) $list->getContent());
        }
    }

    public function testTheAdminReadsTheSameAmounts(): void
    {
        $customer = $this->newCustomer();
        $order = $this->orderWithDiscountPromoLineAndPostage($customer, discount: 15.0);

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $loop = $this->orderLoopRow($order);

        self::assertEqualsWithDelta(round((float) $loop['DISCOUNT_WITHOUT_TAX'], 2), $data['amountDiscountWithoutTaxes'], 0.0001);
        self::assertEqualsWithDelta(round((float) $loop['POSTAGE_UNTAXED'], 2), $data['totalShippingWithoutTaxes'], 0.0001);
        self::assertEqualsWithDelta(
            round((float) $loop['TOTAL_AMOUNT'], 2),
            round($data['totalAmountWithoutTaxesAfterDiscount'] + $data['totalShippingWithoutTaxes'], 2),
            0.0001,
        );
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), ['password' => 'password']);
    }

    /**
     * Two lines taxed at 20 %: 2 x 30 + 6 untaxed, off promotion; and 3 x (49.999 normally,
     * 33.333 on promotion) whose promotional tax is 6.6666 a unit. Postage 12, 2 of it tax.
     * The prices are chosen so that the two rounding modes give different unit amounts.
     */
    private function orderWithDiscountPromoLineAndPostage(Customer $customer, float $discount): Order
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($customer, ['statusCode' => 'paid', 'postage' => 12, 'postageTax' => 2]);

        $this->line($order, 'FULL-LINE', price: '50.000000', promoPrice: '45.000000', tax: '10.000000', promoTax: '9.000000', quantity: 2.0, onPromo: false);
        $this->line($order, 'PROMO-LINE', price: '49.999000', promoPrice: '33.333000', tax: '10.000000', promoTax: '6.666600', quantity: 3.0, onPromo: true);

        $order->setDiscount((string) $discount)->save($this->getPropelConnection());

        return $order;
    }

    private function line(Order $order, string $ref, string $price, string $promoPrice, string $tax, string $promoTax, float $quantity, bool $onPromo): void
    {
        $connection = $this->getPropelConnection();

        $line = $this->createFixtureFactory()->orderProduct($order, ['productRef' => $ref, 'price' => $price, 'promoPrice' => $promoPrice, 'quantity' => $quantity]);
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
