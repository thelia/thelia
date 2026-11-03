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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Order\OrderEditEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\DTO\AddedOrderLine;
use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Enum\OrderEditRefusal;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderEditRefusedException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\Service\OrderEditor;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentItem;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderVersionQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

final class OrderEditorTest extends IntegrationTestCase
{
    private OrderEditor $editor;

    private Order $order;

    private OrderProduct $line;

    private Product $product;

    private ProductSaleElements $pse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = $this->getService(OrderEditor::class);
        $this->placeAnOrder(OrderStatus::CODE_PAID);
    }

    public function testAddingALineSnapshotsTheCatalogueTakesTheStockAndTaxesTheDeliveryCountry(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherPse = $other->getDefaultSaleElements();
        $stockBefore = $this->stockOf($otherPse);
        $totalBefore = $this->totalOf();

        $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $otherPse->getId(), 2.0)]));

        $added = OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductRef($other->getRef())->findOne();
        self::assertNotNull($added);
        self::assertSame('Gadget', $added->getTitle());
        self::assertSame('25.000000', (string) $added->getPrice());
        self::assertEqualsWithDelta(2.0, (float) $added->getQuantity(), 0.0001);
        self::assertEqualsWithDelta($stockBefore - 2, $this->stockOf($otherPse), 0.0001);

        $taxes = OrderProductTaxQuery::create()->filterByOrderProductId($added->getId())->find();
        self::assertCount(1, $taxes);
        self::assertSame('5.000000', (string) $taxes[0]->getAmount());
        self::assertEqualsWithDelta($totalBefore + 60.0, $this->totalOf(), 0.0001);
    }

    public function testRaisingAQuantityTakesTheDifferenceFromStockAndLoweringItGivesItBack(): void
    {
        $stock = $this->stockOf($this->pse);

        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 5.0]));
        self::assertEqualsWithDelta($stock - 3, $this->stockOf($this->pse), 0.0001);
        self::assertEqualsWithDelta(60.0, $this->totalOf(), 0.0001);

        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 4.0]));
        self::assertEqualsWithDelta($stock - 2, $this->stockOf($this->pse), 0.0001);
    }

    public function testRemovingALineGivesItsStockBackAndLowersTheTotal(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherLine = $this->aLine($other, 1.0, '25.000000');
        $stock = $this->stockOf($other->getDefaultSaleElements());
        $totalBefore = $this->totalOf();

        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $otherLine->getId()]));

        self::assertNull(OrderProductQuery::create()->findPk($otherLine->getId()));
        self::assertSame(0, OrderProductTaxQuery::create()->filterByOrderProductId($otherLine->getId())->count());
        self::assertEqualsWithDelta($stock + 1, $this->stockOf($other->getDefaultSaleElements()), 0.0001);
        self::assertEqualsWithDelta($totalBefore - 30.0, $this->totalOf(), 0.0001);
    }

    public function testAForcedPriceRecomputesItsTaxesAndStaysWhenTheCatalogueMoves(): void
    {
        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '10.000000']));
        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '15.500000']));

        $reloaded = OrderProductQuery::create()->findPk($this->line->getId());
        self::assertSame('15.500000', (string) $reloaded->getPrice());
        self::assertSame('15.500000', (string) $reloaded->getPromoPrice());
        $taxes = OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->find();
        self::assertCount(1, $taxes);
        self::assertSame('3.100000', (string) $taxes[0]->getAmount());

        $price = \Thelia\Model\ProductPriceQuery::create()->filterByProductSaleElementsId($this->pse->getId())->findOne();
        $price->setPrice('99')->setPromoPrice('99')->save();
        self::assertSame('15.500000', (string) OrderProductQuery::create()->findPk($this->line->getId())->getPrice());
    }

    public function testADiscountLowersTheTotalAndCannotExceedIt(): void
    {
        $this->editor->edit($this->order, new OrderEdit(discount: '5.000000'));
        self::assertEqualsWithDelta(19.0, $this->totalOf(), 0.0001);

        $this->expectRefusal(OrderEditRefusal::InvalidDiscount);
        $this->editor->edit($this->order, new OrderEdit(discount: '500.000000'));
    }

    public function testAnEditIsWrittenInTheHistoryWithItsDetail(): void
    {
        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 3.0]));

        $entry = OrderHistoryQuery::create()->filterByOrderId($this->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->findOne();
        self::assertNotNull($entry);
        self::assertSame($this->pse->getRef(), $entry->getDecodedPayload()['quantities'][0]['ref']);
        self::assertEqualsWithDelta(3.0, $entry->getDecodedPayload()['quantities'][0]['to'], 0.0001);
    }

    public function testAnEditAddsNoStatusToTheVersionsTheStockRuleCounts(): void
    {
        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000'));
        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 3.0]));

        self::assertSame(1, OrderVersionQuery::create()->groupByStatusId()->filterById($this->order->getId())->count());
    }

    public function testBeforeAndAfterEventsCarryTheEditAndABeforeListenerCanRefuse(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $seen = [];
        $before = static function (OrderEditEvent $event) use (&$seen): void {
            $seen[] = 'before:'.$event->getOrder()->getId();
        };
        $after = static function (OrderEditEvent $event) use (&$seen): void {
            $seen[] = 'after:'.array_key_first($event->getEdit()->quantities);
        };
        $dispatcher->addListener(TheliaEvents::ORDER_BEFORE_EDIT, $before);
        $dispatcher->addListener(TheliaEvents::ORDER_AFTER_EDIT, $after);

        try {
            $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 3.0]));
            self::assertSame(['before:'.$this->order->getId(), 'after:'.$this->line->getId()], $seen);

            $veto = static function (): void {
                throw new \RuntimeException('vetoed');
            };
            $dispatcher->addListener(TheliaEvents::ORDER_BEFORE_EDIT, $veto);

            try {
                $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 9.0]));
                self::fail('The listener refusal must stop the edit.');
            } catch (\RuntimeException) {
                self::assertEqualsWithDelta(3.0, (float) OrderProductQuery::create()->findPk($this->line->getId())->getQuantity(), 0.0001);
            } finally {
                $dispatcher->removeListener(TheliaEvents::ORDER_BEFORE_EDIT, $veto);
            }
        } finally {
            $dispatcher->removeListener(TheliaEvents::ORDER_BEFORE_EDIT, $before);
            $dispatcher->removeListener(TheliaEvents::ORDER_AFTER_EDIT, $after);
        }
    }

    public function testAnUnpaidOrderOnlyMovesStockWhenItsPaymentModuleTookItAtCreation(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $stock = $this->stockOf($this->pse);
        $managed = $this->order->isStockManagedOnOrderCreation($this->getService(EventDispatcherInterface::class));

        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 5.0]));

        self::assertEqualsWithDelta($managed ? $stock - 3 : $stock, $this->stockOf($this->pse), 0.0001);
    }

    public function testAnUnpaidOrderThatWasPaidBeforeHoldsNoStockWhateverItsModuleDidAtCreation(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $this->theModuleTakesStockAtCreation();
        $stock = $this->stockOf($this->pse);

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 5.0]));

        self::assertEqualsWithDelta($stock - 3.0, $this->stockOf($this->pse), 0.0001, 'control: a never-paid order of a module taking stock at creation holds it');

        $this->recordAPassageThroughStatus(OrderStatus::CODE_PAID);
        $stock = $this->stockOf($this->pse);

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 8.0]));

        self::assertEqualsWithDelta($stock, $this->stockOf($this->pse), 0.0001);
    }

    public function testTheDiscountIsCappedByTheGoodsAndNotByTheGoodsPlusPostage(): void
    {
        $this->order->setPostage('12.000000')->save();
        $goods = $this->goodsTotal();

        try {
            $this->editor->edit($this->order, new OrderEdit(discount: (string) number_format($goods + 6, 6, '.', '')));
            self::fail('A discount above the goods must be refused even when the postage would absorb it.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertStringContainsString('('.OrderEditRefusal::InvalidDiscount->value.')', $refusal->getMessage());
        }
    }

    public function testAnAmountWithATrailingNewlineIsRefused(): void
    {
        $this->expectRefusal(OrderEditRefusal::InvalidDiscount);
        $this->editor->edit($this->order, new OrderEdit(discount: "1\n"));
    }

    public function testAStatusChangedBehindTheEditorsBackIsSeen(): void
    {
        $this->order->getOrderStatus();
        $closed = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED);
        $this->getPropelConnection()->exec('UPDATE `order` SET `status_id` = '.(int) $closed->getId().' WHERE `id` = '.(int) $this->order->getId());

        $this->expectRefusal(OrderEditRefusal::StatusNotEditable);
        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 3.0]));
    }

    private function goodsTotal(): float
    {
        Order::forgetCachedTotals((int) $this->order->getId());
        $tax = 0;

        return OrderQuery::create()->findPk($this->order->getId())->getTotalAmount($tax, false, false);
    }

    private function recordAPassageThroughStatus(string $statusCode): void
    {
        $connection = $this->getPropelConnection();
        $connection->exec('CREATE TEMPORARY TABLE `order_version_copy` SELECT * FROM `order_version` WHERE `id` = '.(int) $this->order->getId());
        $connection->exec('UPDATE `order_version_copy` SET `version` = `version` + 100, `status_id` = '.(int) OrderStatusQuery::create()->findOneByCode($statusCode)->getId());
        $connection->exec('INSERT INTO `order_version` SELECT * FROM `order_version_copy`');
        $connection->exec('DROP TEMPORARY TABLE `order_version_copy`');
    }

    private function theModuleTakesStockAtCreation(): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static function (\Thelia\Core\Event\Payment\ManageStockOnCreationEvent $event): void {
                $event->setManageStock(true);
            },
        );
    }

    public function testAShortageRefusesTheEdit(): void
    {
        $this->expectException(StockShortageException::class);
        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 500.0]));
    }

    public function testAClosedStatusRefusesTheEdit(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_SENT);

        $this->expectRefusal(OrderEditRefusal::StatusNotEditable);
        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000'));
    }

    public function testAnInvoicedOrderIsRefusedWhenInvoicesAreNumberedAutomatically(): void
    {
        ConfigQuery::write('invoice_ref_auto', '1');
        $this->order->setInvoiceRef('2026-0001')->save();

        $this->expectRefusal(OrderEditRefusal::InvoiceIssued);
        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000'));
    }

    public function testTheLastLineCannotBeRemoved(): void
    {
        $this->expectRefusal(OrderEditRefusal::LastLine);
        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $this->line->getId()]));
    }

    public function testReplacingTheLastLineInOneEditIsAllowed(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);

        $this->editor->edit($this->order, new OrderEdit(
            addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0)],
            removedLines: [(int) $this->line->getId()],
        ));

        self::assertSame(1, OrderProductQuery::create()->filterByOrderId($this->order->getId())->count());
    }

    /**
     * @dataProvider invalidEdits
     */
    public function testInvalidValuesAreRefused(OrderEditRefusal $refusal, \Closure $edit): void
    {
        $this->expectRefusal($refusal);
        $this->editor->edit($this->order, $edit($this));
    }

    /**
     * @return iterable<string, array{OrderEditRefusal, \Closure}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'zero quantity' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(quantities: [$t->lineId() => 0.0])];
        yield 'negative quantity' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(quantities: [$t->lineId() => -1.0])];
        yield 'negative price' => [OrderEditRefusal::InvalidPrice, static fn (self $t) => new OrderEdit(unitPrices: [$t->lineId() => '-1'])];
        yield 'price with too many decimals' => [OrderEditRefusal::InvalidPrice, static fn (self $t) => new OrderEdit(unitPrices: [$t->lineId() => '1.1234567'])];
        yield 'price injection' => [OrderEditRefusal::InvalidPrice, static fn (self $t) => new OrderEdit(unitPrices: [$t->lineId() => '1; DROP TABLE `order`'])];
        yield 'negative discount' => [OrderEditRefusal::InvalidDiscount, static fn (self $t) => new OrderEdit(discount: '-5')];
        yield 'negative postage' => [OrderEditRefusal::InvalidPostage, static fn (self $t) => new OrderEdit(untaxedPostage: '-1')];
        yield 'postage with too many decimals' => [OrderEditRefusal::InvalidPostage, static fn (self $t) => new OrderEdit(untaxedPostage: '1.1234567')];
        yield 'unknown line' => [OrderEditRefusal::UnknownLine, static fn (self $t) => new OrderEdit(quantities: [999999999 => 1.0])];
        yield 'unknown sale element' => [OrderEditRefusal::UnknownProduct, static fn (self $t) => new OrderEdit(addedLines: [new AddedOrderLine(999999999, 1.0)])];
        yield 'added zero quantity' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(addedLines: [new AddedOrderLine(1, 0.0)])];
        yield 'nothing' => [OrderEditRefusal::NothingToEdit, static fn (self $t) => new OrderEdit()];
    }

    public function testAnOfflineProductIsRefusedLikeAnUnknownOne(): void
    {
        $hidden = $this->aProduct(['visible' => 0]);

        try {
            $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $hidden->getDefaultSaleElements()->getId(), 1.0)]));
            self::fail('An offline product must be refused.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertSame(OrderEditRefusal::UnknownProduct, $refusal->refusal);
        }
    }

    public function testAStaleStateIsRefusedAndAFreshOneIsAccepted(): void
    {
        $state = $this->editor->stateOf($this->order);
        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000', expectedState: $state));

        $this->expectRefusal(OrderEditRefusal::StaleState);
        $this->editor->edit($this->order, new OrderEdit(discount: '2.000000', expectedState: $state));
    }

    public function testAServiceLineIsLeftAlone(): void
    {
        $service = $this->aLine($this->product, 1.0, '3.000000');
        $service->setLineType(OrderProduct::LINE_TYPE_SERVICE)->save();

        $this->expectRefusal(OrderEditRefusal::ServiceLine);
        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $service->getId()]));
    }

    public function testAnExemptedOrderIsEditedWithoutTaxLines(): void
    {
        OrderAddressQuery::create()->findPk($this->order->getInvoiceOrderAddressId())->setVatExempted(1)->save();
        OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->delete();

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '20.000000']));

        self::assertSame(0, OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->count());
    }

    public function testTheTotalReadRightAfterAnEditIsNotTheOneCachedBeforeIt(): void
    {
        $tax = 0;
        Order::forgetCachedTotals((int) $this->order->getId());
        $before = $this->order->getTotalAmount($tax, false, true);

        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 5.0]));

        self::assertEqualsWithDelta($before + 36.0, $this->order->getTotalAmount($tax, false, true), 0.0001);
    }

    public function testALineAlreadyInAShipmentIsLeftAlone(): void
    {
        $shipment = (new OrderShipment())->setOrderId((int) $this->order->getId())->setRef('SHIPMENT-'.$this->order->getId())->setStatus('draft');
        $shipment->save($this->getPropelConnection());
        (new OrderShipmentItem())->setOrderShipmentId((int) $shipment->getId())->setOrderProductId((int) $this->line->getId())->setQuantity(1.0)->save($this->getPropelConnection());

        $this->expectRefusal(OrderEditRefusal::LineInUse);
        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 5.0]));
    }

    public function testTheEditNeedsNeitherARequestNorASession(): void
    {
        $requests = $this->getService(RequestStack::class);

        while (null !== $requests->pop()) {
        }

        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $this->line->getId() => 3.0], discount: '1.000000'));

        self::assertEqualsWithDelta(3.0, (float) OrderProductQuery::create()->findPk($this->line->getId())->getQuantity(), 0.0001);
    }

    public function testTheEditableStatusesAreTheShopsToDeclare(): void
    {
        ConfigQuery::write('order_editable_status_codes', OrderStatus::CODE_SENT);
        $this->placeAnOrder(OrderStatus::CODE_SENT);

        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000'));
        self::assertSame('1.000000', (string) \Thelia\Model\OrderQuery::create()->findPk($this->order->getId())->getDiscount());

        $this->placeAnOrder(OrderStatus::CODE_PAID);
        $this->expectRefusal(OrderEditRefusal::StatusNotEditable);
        $this->editor->edit($this->order, new OrderEdit(discount: '1.000000'));
    }

    public function lineId(): int
    {
        return (int) $this->line->getId();
    }

    public function testANewPostageIsTaxedAgainOnTheDeliveryCountryByTheDeliveryModule(): void
    {
        $this->withDeliveryTaxRule(function (): void {
            $this->order->setPostage('6.000000')->setPostageTax('1.000000')->save();
            (new OrderPostageTax())->setOrderId($this->order->getId())->setTitle('stale')->setUntaxedAmount('5')->setAmount('1')->save($this->getPropelConnection());

            $this->editor->edit($this->order, new OrderEdit(untaxedPostage: '10.000000'));

            $order = OrderQuery::create()->findPk($this->order->getId());
            self::assertEqualsWithDelta(12.0, (float) $order->getPostage(), 0.0001);
            self::assertEqualsWithDelta(2.0, (float) $order->getPostageTax(), 0.0001);
            self::assertSame(0, OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->count());
            self::assertSame('6.000000', $this->postageFromHistory()['from']);
            self::assertSame('12.000000', $this->postageFromHistory()['to']);
        });
    }

    public function testAnExemptedOrderGetsAnUntaxedPostage(): void
    {
        OrderAddressQuery::create()->findPk($this->order->getInvoiceOrderAddressId())->setVatExempted(1)->save();

        $this->withDeliveryTaxRule(function (): void {
            $this->editor->edit($this->order, new OrderEdit(untaxedPostage: '10.000000'));

            $order = OrderQuery::create()->findPk($this->order->getId());
            self::assertEqualsWithDelta(10.0, (float) $order->getPostage(), 0.0001);
            self::assertEqualsWithDelta(0.0, (float) $order->getPostageTax(), 0.0001);
        });
    }

    public function testAPostageEditLeavesTheLinesAndTheStockAlone(): void
    {
        $this->withDeliveryTaxRule(function (): void {
            $stock = $this->stockOf($this->pse);

            $this->editor->edit($this->order, new OrderEdit(untaxedPostage: '3.000000'));

            self::assertEqualsWithDelta(2.0, (float) OrderProductQuery::create()->findPk($this->line->getId())->getQuantity(), 0.0001);
            self::assertEqualsWithDelta($stock, $this->stockOf($this->pse), 0.0001);
        });
    }

    public function testTheTotalAfterAnEditIsTheOneTheBackOfficeListComputesInSql(): void
    {
        $filters = 'BackOfficeDefaultTwigBundle\\Service\\Order\\OrderFilters';

        if (!class_exists($filters) || !method_exists($filters, 'totalAmountSqlExpression')) {
            self::markTestSkipped('The mounted back-office theme has no SQL total expression yet.');
        }

        $this->withDeliveryTaxRule(function () use ($filters): void {
            $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);

            $this->editor->edit($this->order, new OrderEdit(
                addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 3.0, '13.333333')],
                quantities: [$this->lineId() => 3.0],
                discount: '4.500000',
                untaxedPostage: '7.770000',
            ));

            $statement = $this->getPropelConnection()->prepare('SELECT '.$filters::totalAmountSqlExpression().' AS computed FROM `order` WHERE id = :id');
            $statement->execute([':id' => $this->order->getId()]);
            $sql = (float) $statement->fetchColumn();

            Order::forgetCachedTotals((int) $this->order->getId());
            $tax = 0;

            self::assertGreaterThan(0.0, $sql);
            self::assertEqualsWithDelta($sql, OrderQuery::create()->findPk($this->order->getId())->getTotalAmount($tax, true, true), 0.005);
        });
    }

    public function testTheDocumentsOfAnEditedOrderStillRender(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);

        $this->editor->edit($this->order, new OrderEdit(
            addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 2.0)],
            quantities: [$this->lineId() => 1.0],
            discount: '1.000000',
        ));

        $tester = new CommandTester((new Application(self::$kernel))->find('pdf:render'));

        foreach (['invoice', 'delivery'] as $document) {
            $file = sys_get_temp_dir().'/edited_order_'.$document.'_'.uniqid('', true).'.pdf';

            try {
                $tester->execute(['document' => $document, '--order' => (string) $this->order->getId(), '--locale' => 'fr_FR', '--out' => $file]);

                $tester->assertCommandIsSuccessful();
                self::assertStringStartsWith('%PDF', (string) file_get_contents($file));
            } finally {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * @return array{from: string, to: string}
     */
    private function postageFromHistory(): array
    {
        return OrderHistoryQuery::create()->filterByOrderId($this->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->findOne()->getDecodedPayload()['postage'];
    }

    private function withDeliveryTaxRule(\Closure $test): void
    {
        $previous = ConfigQuery::read('taxrule_id_delivery_module');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->taxRule()->getId());

        try {
            $test();
        } finally {
            ConfigQuery::write('taxrule_id_delivery_module', (string) $previous);
        }
    }

    private function expectRefusal(OrderEditRefusal $refusal): void
    {
        $this->expectException(OrderEditRefusedException::class);
        $this->expectExceptionMessage('('.$refusal->value.')');
    }

    private function totalOf(): float
    {
        Order::forgetCachedTotals((int) $this->order->getId());

        $tax = 0;

        return $this->order->getTotalAmount($tax, false, true);
    }

    private function stockOf(ProductSaleElements $pse): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function aProduct(array $overrides = []): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $this->taxRule(), $factory->currency(), $overrides + ['title' => 'Widget', 'baseQuantity' => 10]);
    }

    private function taxRule(): \Thelia\Model\TaxRule
    {
        static $rule = null;
        $factory = $this->createFixtureFactory();

        if (null === $rule || null === \Thelia\Model\TaxRuleQuery::create()->findPk($rule->getId())) {
            $rule = $factory->taxRule(['isDefault' => false]);
            (new TaxRuleCountry())
                ->setTaxRuleId($rule->getId())
                ->setCountryId($this->country()->getId())
                ->setTaxId($factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20'])->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
        }

        return $rule;
    }

    private function country(): \Thelia\Model\Country
    {
        static $country = null;

        if (null === $country || null === \Thelia\Model\CountryQuery::create()->findPk($country->getId())) {
            $country = $this->createFixtureFactory()->country(['isocode' => '056', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEL', 'shopCountry' => false]);
        }

        return $country;
    }

    private function placeAnOrder(string $statusCode): void
    {
        $factory = $this->createFixtureFactory();
        $this->order = $factory->order(null, ['statusCode' => $statusCode]);
        OrderAddressQuery::create()->findPk($this->order->getDeliveryOrderAddressId())->setCountryId($this->country()->getId())->save();

        $this->product = $this->aProduct();
        $this->pse = $this->product->getDefaultSaleElements();
        $this->line = $this->aLine($this->product, 2.0, '10.000000');
    }

    private function aLine(Product $product, float $quantity, string $price): OrderProduct
    {
        $factory = $this->createFixtureFactory();
        $pse = $product->getDefaultSaleElements();

        $line = $factory->orderProduct($this->order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $pse->getRef(),
            'productSaleElementsId' => $pse->getId(),
            'quantity' => $quantity,
            'price' => $price,
            'promoPrice' => $price,
        ]);

        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT 20')
            ->setAmount((string) round((float) $price * 0.2, 6))
            ->setPromoAmount((string) round((float) $price * 0.2, 6))
            ->save($this->getPropelConnection());

        return $line;
    }
}
