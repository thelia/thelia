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

namespace Thelia\Tests\Integration\Domain\Order\Edition;

use Propel\Runtime\Propel;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Thelia\Core\Event\Order\OrderEditEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Edition\InvalidOrderEditException;
use Thelia\Domain\Order\Edition\OrderEdit;
use Thelia\Domain\Order\Edition\OrderEditConflictException;
use Thelia\Domain\Order\Edition\OrderEditLine;
use Thelia\Domain\Order\Edition\OrderEditor;
use Thelia\Domain\Order\Edition\OrderNotEditableException;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\SendCustomerEmailAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The lines of an order changed after it was placed: the new values are frozen on the
 * lines, the taxes follow the invoice country, the stock follows when it was taken, and
 * the order stays whole when an edit is refused.
 */
final class OrderEditorTest extends ActionIntegrationTestCase
{
    private OrderEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = $this->getService(OrderEditor::class);
    }

    public function testALineAddedToAnUnpaidOrderCarriesTheCatalogueLabelAndPrice(): void
    {
        $product = $this->product(30.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]]);

        $outcome = $this->apply($order, [...$this->kept($order), OrderEditLine::add((int) $this->pseOf($product)->getId(), 2)]);

        $added = OrderProductQuery::create()->filterByOrderId($order->getId())->filterByProductRef($product->getRef())->findOne();
        self::assertNotNull($added);
        self::assertSame(2.0, (float) $added->getQuantity());
        self::assertSame(30.0, (float) $added->getPrice());
        self::assertNotSame('', (string) $added->getTitle());
        self::assertSame(6.0, (float) OrderProductTaxQuery::create()->filterByOrderProductId($added->getId())->findOne()?->getAmount(), '20% of 30, from the invoice country.');
        self::assertEqualsWithDelta(60.0 + 72.0, $outcome->totalAfter, 0.001);
        self::assertEqualsWithDelta($this->freshTotal($order), $outcome->totalAfter, 0.001, 'The order says what the edit said.');
    }

    public function testRaisingAQuantityOnAPaidOrderTakesTheStock(): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        $line = $this->lines($order)[0];

        $this->apply($order, [OrderEditLine::keep((int) $line->getId(), 3)]);

        self::assertSame(7.0, $this->stockOf($product), 'One taken when it was paid, two more now.');
    }

    public function testRemovingALineFromAPaidOrderGivesTheStockBackAndSaysWhatToRefund(): void
    {
        $kept = $this->product(50.0, stock: 10);
        $removed = $this->product(20.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$kept, 1], [$removed, 2]]);
        $before = $this->freshTotal($order);

        $outcome = $this->apply($order, [OrderEditLine::keep((int) $this->lineOf($order, $kept)->getId(), 1)]);

        self::assertSame(10.0, $this->stockOf($removed), 'The two taken when it was paid come back.');
        self::assertEqualsWithDelta($before - 48.0, $outcome->totalAfter, 0.001);
        self::assertEqualsWithDelta(48.0, $outcome->amountToRefund(), 0.001);
    }

    public function testAUnitPriceCorrectedByHandStaysWhenTheCatalogueChanges(): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$product, 1]]);

        $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 1, '10.00')]);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->update(['Price' => 99]);

        $line = $this->lines($order)[0];
        self::assertSame(10.0, (float) $line->getPrice());
        self::assertSame(2.0, (float) OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->findOne()?->getAmount());
        self::assertEqualsWithDelta(12.0, $this->freshTotal($order), 0.001);
    }

    public function testADiscountOnTheOrderLowersItsTotal(): void
    {
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]]);

        $outcome = $this->apply($order, $this->kept($order), discount: '5.00');

        self::assertEqualsWithDelta(55.0, $outcome->totalAfter, 0.001);
        self::assertSame(5.0, (float) OrderQuery::create()->findPk($order->getId())->getDiscount());
    }

    public function testAPostageTypedByHandKeepsTheRateOfTheOldOne(): void
    {
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]], postage: 12.0, postageTax: 2.0);

        $this->apply($order, $this->kept($order), postage: '18.00');

        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertSame(18.0, (float) $reloaded->getPostage());
        self::assertSame(3.0, (float) $reloaded->getPostageTax());
    }

    public function testAnOrderPastTheWarehouseIsNotEditable(): void
    {
        $order = $this->order(OrderStatus::CODE_SENT, [[$this->product(50.0, stock: 10), 1]]);

        self::assertNotNull($this->editor->refusal($order));
        $this->expectException(OrderNotEditableException::class);

        $this->apply($order, $this->kept($order));
    }

    public function testAnInvoicedOrderIsNotEditableAndSaysWhy(): void
    {
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0, stock: 10), 1]]);
        $order->setInvoiceRef('2026-000777')->save();

        $this->expectException(OrderNotEditableException::class);
        $this->expectExceptionMessage('credit note');

        $this->apply($order, $this->kept($order));
    }

    public function testAnEditIsWrittenToTheHistoryWithWhatChanged(): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$product, 1]]);

        $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 2)]);

        $entry = OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->findOne();
        self::assertNotNull($entry);
        $payload = json_decode((string) $entry->getPayload(), true);
        self::assertSame([['change' => 'quantity', 'product_ref' => $product->getRef(), 'from' => '1', 'to' => '2']], $payload['changes']);
    }

    public function testTwoEditsMadeFromTheSameStateDoNotOverwriteEachOther(): void
    {
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]]);
        $line = (int) $this->lines($order)[0]->getId();
        $seen = $this->editor->fingerprint($order);

        $this->editor->apply($order, new OrderEdit([OrderEditLine::keep($line, 2)]), $seen);

        $this->expectException(OrderEditConflictException::class);

        $this->editor->apply($order, new OrderEdit([OrderEditLine::keep($line, 5)]), $seen);
    }

    public function testTheLastProductLineCannotBeRemoved(): void
    {
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]]);

        $this->expectException(InvalidOrderEditException::class);

        $this->apply($order, []);
    }

    /**
     * @return iterable<string, array{\Closure(self, Order): list<OrderEditLine>}>
     */
    public static function invalidLines(): iterable
    {
        yield 'a quantity of zero' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), 0)]];
        yield 'a negative quantity' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), -1)]];
        yield 'a negative price' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), 1, '-1.00')]];
        yield 'an unknown product' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), 1), OrderEditLine::add(987654321, 1)]];
        yield 'a line of another order' => [static fn (self $test, Order $order): array => [OrderEditLine::keep(987654321, 1)]];
        yield 'an infinite price' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), 1, '1e999')]];
        yield 'an infinite quantity' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), \INF)]];
        yield 'a price the column cannot hold' => [static fn (self $test, Order $order): array => [OrderEditLine::keep((int) $test->lines($order)[0]->getId(), 1, '99999999999')]];
    }

    /**
     * @param \Closure(self, Order): list<OrderEditLine> $lines
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLines')]
    public function testAnEditThatMakesNoSenseIsRefusedAndChangesNothing(\Closure $lines): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        $fingerprint = $this->editor->fingerprint($order);

        try {
            $this->apply($order, $lines($this, $order));
            self::fail('The edit was accepted.');
        } catch (InvalidOrderEditException) {
        }

        self::assertSame($fingerprint, $this->editor->fingerprint(OrderQuery::create()->findPk($order->getId())));
        self::assertSame(9.0, $this->stockOf($product));
    }

    public function testAPreviewGivesTheTotalsAndWritesNothing(): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        $fingerprint = $this->editor->fingerprint($order);

        $outcome = $this->editor->preview($order, new OrderEdit([OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 3)]));

        self::assertEqualsWithDelta(180.0, $outcome->totalAfter, 0.001);
        self::assertSame($fingerprint, $this->editor->fingerprint(OrderQuery::create()->findPk($order->getId())));
        self::assertSame(9.0, $this->stockOf($product));
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count());
    }

    public function testAModuleRefusingTheEditKeepsTheOrderWhole(): void
    {
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        $fingerprint = $this->editor->fingerprint($order);
        $refusal = static function (OrderEditEvent $event): void {
            throw new \RuntimeException('Refused by a module');
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_BEFORE_EDIT, $refusal);

        try {
            $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 3)]);
            self::fail('The edit went through.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Refused by a module', $exception->getMessage());
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_BEFORE_EDIT, $refusal);
        }

        self::assertSame($fingerprint, $this->editor->fingerprint(OrderQuery::create()->findPk($order->getId())));
        self::assertSame(9.0, $this->stockOf($product));
    }

    public function testAnEditFailingAfterItsFirstWritesIsUndoneWhole(): void
    {
        $product = $this->product(50.0, stock: 10);
        $added = $this->product(30.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        $fingerprint = $this->editor->fingerprint($order);
        // The kept line is raised and its stock taken before the added line fails.
        $failure = static function (): void {
            throw new \RuntimeException('A module failed on the added line');
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $failure);

        try {
            $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 3), OrderEditLine::add((int) $this->pseOf($added)->getId(), 1)]);
            self::fail('The edit went through.');
        } catch (\RuntimeException $exception) {
            self::assertSame('A module failed on the added line', $exception->getMessage());
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $failure);
        }

        self::assertSame($fingerprint, $this->editor->fingerprint(OrderQuery::create()->findPk($order->getId())));
        self::assertSame(9.0, $this->stockOf($product));
        self::assertSame(10.0, $this->stockOf($added));
    }

    public function testATransactionTheCallerOpenedCanStillBeCommittedAfterAPreviewOrARefusal(): void
    {
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0, stock: 10), 1]]);
        $line = (int) $this->lines($order)[0]->getId();
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->editor->preview($order, new OrderEdit([OrderEditLine::keep($line, 3)]));

            try {
                $this->apply($order, [OrderEditLine::keep($line, 0)]);
            } catch (InvalidOrderEditException) {
            }

            self::assertTrue($connection->isCommitable(), 'The edit undid itself, not the work of its caller.');
        } finally {
            $connection->commit();
        }
    }

    public function testRaisingAQuantityBeyondTheStockIsRefusedWhenTheOrderDoesNotHoldIt(): void
    {
        $product = $this->product(50.0, stock: 3);
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$product, 1]]);

        $this->expectException(InvalidOrderEditException::class);
        $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 5)]);
    }

    public function testAddingMoreThanTheStockSaysWhichProductIsShort(): void
    {
        $short = $this->product(30.0, stock: 1);
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0, stock: 10), 1]]);

        $this->expectException(InvalidOrderEditException::class);
        $this->expectExceptionMessage($short->getRef());
        $this->apply($order, [...$this->kept($order), OrderEditLine::add((int) $this->pseOf($short)->getId(), 2)]);
    }

    public function testTheCustomerIsToldWhatChangedWhenTheShopAsksForIt(): void
    {
        if (!is_file(THELIA_TEMPLATE_DIR.'email/default/order_edited.txt.twig')) {
            self::markTestSkipped('The installed mail theme has no template for an edited order yet.');
        }

        ConfigQuery::write('store_email', 'shop@example.com');
        (new OrderStatusAction())
            ->setTriggerType(OrderStatusActionTrigger::EDIT->value)
            ->setToStatusId((int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId())
            ->setActionType(SendCustomerEmailAction::getType())
            ->setDecodedPayload([SendCustomerEmailAction::FIELD_MESSAGE_CODE => 'order_edited'])
            ->setPosition(1)
            ->setActive(true)
            ->save();
        $this->getService(OrderStatusActionRunner::class)->reset();
        $product = $this->product(50.0, stock: 10);
        $order = $this->order(OrderStatus::CODE_PAID, [[$product, 1]]);
        // A reference is typed by the merchant: the HTML mail must show it, not run it.
        $reference = $product->getRef().'<i>&';
        $this->lines($order)[0]->setProductRef($reference)->save();
        $texts = [];
        $htmls = [];
        $listener = static function (MessageEvent $event) use (&$texts, &$htmls): void {
            $message = $event->getMessage();

            if ($message instanceof Email && !$event->isQueued()) {
                $texts[] = (string) $message->getTextBody();
                $htmls[] = (string) $message->getHtmlBody();
            }
        };
        $this->dispatcher->addListener(MessageEvent::class, $listener);

        try {
            $this->apply($order, [OrderEditLine::keep((int) $this->lines($order)[0]->getId(), 2)]);
        } finally {
            $this->dispatcher->removeListener(MessageEvent::class, $listener);
            ConfigQuery::resetCache();
        }

        self::assertCount(1, $texts);
        self::assertStringContainsString($reference.': quantity changed from 1 to 2', $texts[0]);
        self::assertStringContainsString(htmlspecialchars($reference).': quantity changed from 1 to 2', $htmls[0]);
        self::assertStringNotContainsString('<i>&', $htmls[0]);
    }

    /**
     * @param list<OrderEditLine> $lines
     */
    private function apply(Order $order, array $lines, ?string $discount = null, ?string $postage = null): \Thelia\Domain\Order\Edition\OrderEditOutcome
    {
        return $this->editor->apply($order, new OrderEdit($lines, $discount, $postage), $this->editor->fingerprint($order));
    }

    /**
     * @return list<OrderEditLine>
     */
    private function kept(Order $order): array
    {
        return array_map(static fn (OrderProduct $line): OrderEditLine => OrderEditLine::keep((int) $line->getId(), (float) $line->getQuantity()), $this->lines($order));
    }

    /**
     * @return list<OrderProduct>
     */
    public function lines(Order $order): array
    {
        return iterator_to_array(OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find(), false);
    }

    private function lineOf(Order $order, Product $product): OrderProduct
    {
        return OrderProductQuery::create()->filterByOrderId($order->getId())->filterByProductRef($product->getRef())->findOne() ?? throw new \LogicException('No such line.');
    }

    private function product(float $price, int $stock): Product
    {
        $product = $this->factory->product($this->factory->category(), TaxRuleQuery::create()->findOneByIsDefault(1), $this->defaultCurrency(), ['baseQuantity' => $stock, 'title' => 'Edited product']);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->update(['Price' => $price, 'PromoPrice' => $price]);

        return $product;
    }

    private function pseOf(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne() ?? throw new \LogicException('No sale element.');
    }

    private function stockOf(Product $product): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($this->pseOf($product)->getId())->getQuantity();
    }

    /**
     * An order placed in France at 20%, its lines written as the checkout writes them and
     * the stock taken, as it is once an order is paid.
     *
     * @param list<array{Product, int}> $lines
     */
    private function order(string $statusCode, array $lines, float $postage = 0.0, float $postageTax = 0.0): Order
    {
        $order = $this->factory->order(null, ['statusCode' => $statusCode, 'postage' => $postage, 'postageTax' => $postageTax]);
        $order->setCurrencyId((int) $this->defaultCurrency()->getId())->save();
        OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId())
            ->setCountryId((int) CountryQuery::create()->findOneByIsoalpha2('FR')->getId())
            ->save();

        foreach ($lines as [$product, $quantity]) {
            $pse = $this->pseOf($product);
            $price = (float) ProductPriceQuery::create()->findOneByProductSaleElementsId($pse->getId())->getPrice();
            $line = (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($pse->getRef())
                ->setProductSaleElementsId($pse->getId())
                ->setTitle('Edited product')
                ->setQuantity($quantity)
                ->setPrice((string) $price)
                ->setPromoPrice((string) $price)
                ->setWasNew(0)
                ->setWasInPromo(0)
                ->setTaxRuleTitle('Default Tax Rule');
            $line->save();
            (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT 20%')->setAmount((string) round($price * 0.2, 2))->setPromoAmount((string) round($price * 0.2, 2))->save();

            if (OrderStatus::CODE_NOT_PAID !== $statusCode) {
                $pse->setQuantity($pse->getQuantity() - $quantity)->save();
            }
        }

        return $order;
    }

    private function freshTotal(Order $order): float
    {
        Order::forgetTotalAmounts();

        return OrderQuery::create()->findPk($order->getId())->getTotalAmount();
    }

    private function defaultCurrency(): \Thelia\Model\Currency
    {
        return CurrencyQuery::create()->findOneByByDefault(1);
    }
}
