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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Order\OrderEditEvent;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\DTO\AddedOrderLine;
use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Enum\OrderEditRefusal;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderEditRefusedException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\Service\OrderEditor;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
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
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $this->theModuleTakesStockAtCreation();
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
        $catalogue = (string) OrderProductQuery::create()->findPk($this->line->getId())->getPrice();

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '15.500000']));

        $reloaded = OrderProductQuery::create()->findPk($this->line->getId());
        self::assertSame($catalogue, (string) $reloaded->getPrice());
        self::assertSame('15.500000', (string) $reloaded->getPromoPrice());
        self::assertSame(1, (int) $reloaded->getWasInPromo());
        $taxes = OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->find();
        self::assertCount(1, $taxes);
        self::assertSame('3.100000', (string) $taxes[0]->getPromoAmount());

        $price = \Thelia\Model\ProductPriceQuery::create()->filterByProductSaleElementsId($this->pse->getId())->findOne();
        $price->setPrice('99')->setPromoPrice('99')->save();
        self::assertSame('15.500000', (string) OrderProductQuery::create()->findPk($this->line->getId())->getPromoPrice());
    }

    public function testASimulationInsideTheTransactionOfTheCallerTakesBackItsWrites(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherLine = $this->aLine($other, 1.0, '25.000000');
        $state = $this->editor->stateOf($this->order);

        $preview = $this->editor->simulate($this->order, new OrderEdit(removedLines: [(int) $otherLine->getId()], discount: '2.000000'));

        self::assertLessThan((float) $preview->before->total, (float) $preview->after->total, 'Removing a line and adding a discount lowers the total.');
        self::assertNotNull(OrderProductQuery::create()->findPk($otherLine->getId()));
        self::assertSame(1, OrderProductTaxQuery::create()->filterByOrderProductId($otherLine->getId())->count());
        self::assertSame($state, $this->editor->stateOf(OrderQuery::create()->findPk($this->order->getId())));
        self::assertSame($state, $this->editor->stateOf($this->order));
        self::assertTrue($this->getPropelConnection()->isCommitable(), 'The simulation must not mark the transaction of the caller as unusable.');
    }

    public function testASimulationWithADiscountTaxesTheOrderAsTheRealEditDoes(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherLine = $this->aLine($other, 1.0, '25.000000');
        OrderProductTaxQuery::create()->filterByOrderProductId($otherLine->getId())->update(['Amount' => '1.375000', 'PromoAmount' => '1.375000']);
        $this->editor->edit($this->order, new OrderEdit(discount: '5.000000'));

        $edit = new OrderEdit(removedLines: [(int) $otherLine->getId()]);
        $preview = $this->editor->simulate($this->order, $edit);
        $this->editor->edit($this->order, $edit);

        $fresh = OrderQuery::create()->findPk($this->order->getId());
        Order::forgetCachedTotals((int) $fresh->getId());
        $tax = 0;
        $total = $fresh->getTotalAmount($tax, true, true);

        self::assertSame(number_format($total, 6, '.', ''), $preview->after->total);
        self::assertSame(number_format($tax, 6, '.', ''), $preview->after->tax, 'The tax factor of the discount must be read again on the simulated lines.');
    }

    public function testASimulationOfAnAddedLineAnnouncesTheTotalOfTheRealEdit(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $edit = new OrderEdit(addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 2.0)]);
        $stock = $this->stockOf($other->getDefaultSaleElements());

        $preview = $this->editor->simulate($this->order, $edit);

        self::assertSame(0, OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductRef($other->getRef())->count());
        self::assertEqualsWithDelta($stock, $this->stockOf($other->getDefaultSaleElements()), 0.0001);
        self::assertEqualsWithDelta((float) $preview->before->total + 60.0, (float) $preview->after->total, 0.0001);

        $this->editor->edit($this->order, $edit);
        self::assertEqualsWithDelta((float) $preview->after->total, $this->totalOf(), 0.0001);
    }

    public function testAForcedPriceOnALineAlreadyInPromotionKeepsItsCataloguePrice(): void
    {
        $this->line->setPrice('12.000000')->setPromoPrice('8.000000')->setWasInPromo(1)->save();

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '9.500000']));

        $reloaded = OrderProductQuery::create()->findPk($this->line->getId());
        self::assertSame('12.000000', (string) $reloaded->getPrice());
        self::assertSame('9.500000', (string) $reloaded->getPromoPrice());
        self::assertSame(1, (int) $reloaded->getWasInPromo());
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

    public function testALineRemovedFromAPaidOrderGivesItsStockBackAndLowersTheTotal(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherLine = $this->aLine($other, 1.0, '25.000000');
        $stock = $this->stockOf($other->getDefaultSaleElements());
        $totalBefore = $this->totalOf();

        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $otherLine->getId()]));

        self::assertNull(OrderProductQuery::create()->findPk($otherLine->getId()));
        self::assertEqualsWithDelta($stock + 1, $this->stockOf($other->getDefaultSaleElements()), 0.0001);
        self::assertEqualsWithDelta($totalBefore - 30.0, $this->totalOf(), 0.0001);
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

    #[DataProvider('numberingSettings')]
    public function testAnInvoicedOrderIsRefusedWhateverTheNumberingSetting(string $numbering): void
    {
        $previous = ConfigQuery::read('invoice_ref_auto');
        ConfigQuery::write('invoice_ref_auto', $numbering);

        try {
            $other = $this->aLine($this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]), 1.0, '25.000000');
            $this->order->setInvoiceRef('2026-000042')->save();

            $this->expectRefusal(OrderEditRefusal::InvoiceIssued);
            $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $other->getId()]));
        } finally {
            ConfigQuery::write('invoice_ref_auto', (string) $previous);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function numberingSettings(): iterable
    {
        yield 'automatic numbering' => ['1'];
        yield 'number posed by a status action or a module' => ['0'];
    }

    public function testTheLastLineCannotBeRemoved(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $this->expectRefusal(OrderEditRefusal::LastLine);
        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $this->line->getId()]));
    }

    public function testReplacingTheLastLineInOneEditIsAllowed(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);

        $this->editor->edit($this->order, new OrderEdit(
            addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0)],
            removedLines: [(int) $this->line->getId()],
        ));

        self::assertSame(1, OrderProductQuery::create()->filterByOrderId($this->order->getId())->count());
    }

    public function testAnUnchangedPriceIsRefusedLikeAnUnchangedQuantity(): void
    {
        $line = OrderProductQuery::create()->findPk($this->lineId());
        $written = static fn (self $test): int => OrderHistoryQuery::create()->filterByOrderId($test->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count();
        $before = $written($this);
        $announced = 0;
        $listener = static function () use (&$announced): void {
            ++$announced;
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::ORDER_BEFORE_EDIT, $listener);
        $dispatcher->addListener(TheliaEvents::ORDER_AFTER_EDIT, $listener);

        try {
            $this->editor->edit($this->order, new OrderEdit(unitPrices: [$this->lineId() => (string) $line->getPrice()]));
            self::fail('A price equal to the stored one moves nothing and must be refused.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertStringContainsString('('.OrderEditRefusal::NothingToEdit->value.')', $refusal->getMessage());
        } finally {
            $dispatcher->removeListener(TheliaEvents::ORDER_BEFORE_EDIT, $listener);
            $dispatcher->removeListener(TheliaEvents::ORDER_AFTER_EDIT, $listener);
        }

        self::assertSame(0, $announced, 'No listener hears of a price that did not change.');
        self::assertSame($before, $written($this), 'A refused edit leaves nothing in the history.');
    }

    public function testAnUnchangedPriceBesideAChangedQuantityIsLeftOutOfTheEdit(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $otherLine = $this->aLine($other, 1.0, '25.000000');
        $taxIds = OrderProductTaxQuery::create()->filterByOrderProductId($otherLine->getId())->find()->getPrimaryKeys();

        $this->editor->edit($this->order, new OrderEdit(
            quantities: [$this->lineId() => 3.0],
            unitPrices: [(int) $otherLine->getId() => '25'],
        ));

        $payload = OrderHistoryQuery::create()->filterByOrderId($this->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->findOne()->getDecodedPayload();
        self::assertSame([], $payload['prices']);
        self::assertCount(1, $payload['quantities']);
        self::assertSame(0, (int) OrderProductQuery::create()->findPk($otherLine->getId())->getWasInPromo(), 'The unchanged line is not forced.');
        self::assertSame($taxIds, OrderProductTaxQuery::create()->filterByOrderProductId($otherLine->getId())->find()->getPrimaryKeys(), 'Its taxes are not written again.');
    }

    public function testTwoIdenticalEditsInARowAreBothWrittenInTheHistory(): void
    {
        $gadget = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $added = new OrderEdit(addedLines: [new AddedOrderLine((int) $gadget->getDefaultSaleElements()->getId(), 1.0)]);

        $this->editor->edit($this->order, $added);
        $this->editor->edit($this->order, $added);

        self::assertSame(2, OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductRef($gadget->getRef())->count());
        self::assertSame(2, OrderHistoryQuery::create()->filterByOrderId($this->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count());
    }

    public function testUnchangedValuesSentAgainNeverRefuseAnEdit(): void
    {
        $gone = $this->aProduct(['title' => 'Gone', 'basePrice' => 25.0, 'baseQuantity' => 7]);
        $goneLine = $this->aLine($gone, 1.0, '25.000000');
        $shippedLine = $this->aLine($this->aProduct(['title' => 'Shipped', 'basePrice' => 15.0, 'baseQuantity' => 7]), 1.0, '15.000000');
        $shipment = (new OrderShipment())->setOrderId((int) $this->order->getId())->setRef('SHIPMENT-'.$this->order->getId())->setStatus('draft');
        $shipment->save($this->getPropelConnection());
        (new OrderShipmentItem())->setOrderShipmentId((int) $shipment->getId())->setOrderProductId((int) $shippedLine->getId())->setQuantity(1.0)->save($this->getPropelConnection());
        $goneLine->setProductRef('GONE-'.$goneLine->getId())->save($this->getPropelConnection());

        $this->editor->edit($this->order, new OrderEdit(
            quantities: [$this->lineId() => 3.0, (int) $shippedLine->getId() => 1.0],
            unitPrices: [(int) $goneLine->getId() => '25.000000', (int) $shippedLine->getId() => '15'],
        ));

        self::assertEqualsWithDelta(3.0, (float) OrderProductQuery::create()->findPk($this->lineId())->getQuantity(), 0.0001);
    }

    public function testAnEditThatChangesNothingIsRefusedAndLeavesNoHistory(): void
    {
        $line = OrderProductQuery::create()->findPk($this->lineId());
        $edit = new OrderEdit(quantities: [$this->lineId() => (float) $line->getQuantity()], discount: (string) $this->order->getDiscount());
        $written = static fn (self $test): int => OrderHistoryQuery::create()->filterByOrderId($test->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count();
        $before = $written($this);
        $announced = 0;
        $listener = static function () use (&$announced): void {
            ++$announced;
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::ORDER_BEFORE_EDIT, $listener);

        try {
            $this->editor->edit($this->order, $edit);
            self::fail('An edit that moves nothing must be refused.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertStringContainsString('('.OrderEditRefusal::NothingToEdit->value.')', $refusal->getMessage());
        } finally {
            $dispatcher->removeListener(TheliaEvents::ORDER_BEFORE_EDIT, $listener);
        }

        self::assertSame(0, $announced, 'The listeners are not told of an edit that moves nothing.');

        self::assertSame($before, $written($this), 'A refused edit leaves nothing in the history.');
    }

    #[DataProvider('invalidEdits')]
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
        yield 'quantity above the bound' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(quantities: [$t->lineId() => 1.0e300])];
        yield 'added quantity above the bound' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(addedLines: [new AddedOrderLine(1, 10000000.0)])];
        yield 'nothing' => [OrderEditRefusal::NothingToEdit, static fn (self $t) => new OrderEdit()];
        yield 'a fraction of a line sold by the piece' => [OrderEditRefusal::InvalidQuantity, static fn (self $t) => new OrderEdit(quantities: [$t->lineId() => 2.5])];
    }

    /**
     * A line keeps the decimals it was sold with: 1,2345 kg on a line sold to the hundredth would
     * be printed 1,23 kg and charged otherwise. The minimum and the step of the combination are
     * left to the merchant, who edits an order already placed.
     */
    public function testAnEditedQuantityKeepsTheDecimalsTheLineWasSoldWithAndLeavesTheMinimumAndTheStepFree(): void
    {
        $this->pse->setQuantityDecimals(2)->setMinQuantity('0.500000')->setQuantityStep('0.500000')->save($this->getPropelConnection());
        $this->line->setQuantityDecimals(2)->save($this->getPropelConnection());

        try {
            $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 1.2345]));
            self::fail('Four decimals on a line sold to the hundredth are refused.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertSame(OrderEditRefusal::InvalidQuantity, $refusal->refusal);
            self::assertStringContainsString('decimal(s) at most', $refusal->getMessage());
        }

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 1.25]));

        self::assertSame(1.25, (float) OrderProductQuery::create()->findPk($this->lineId())->getQuantity(), 'Under the minimum and off the step of the combination: the merchant decides.');
    }

    public function testAnAddedLineTakesTheDecimalsOfItsCombination(): void
    {
        $byThePiece = $this->aProduct(['baseQuantity' => 10])->getDefaultSaleElements();
        $byMeasure = $this->aProduct(['baseQuantity' => 10])->getDefaultSaleElements();
        $byMeasure->setQuantityDecimals(1)->setMinQuantity('0.100000')->setQuantityStep('0.100000')->setUnitCode('kg')->save($this->getPropelConnection());

        try {
            $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $byThePiece->getId(), 1.5)]));
            self::fail('Half a piece is refused.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertSame(OrderEditRefusal::InvalidQuantity, $refusal->refusal);
            self::assertStringContainsString('decimal(s) at most', $refusal->getMessage());
        }

        $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $byMeasure->getId(), 1.5)]));

        $added = OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductSaleElementsId($byMeasure->getId())->findOne();
        self::assertSame(1.5, (float) $added->getQuantity());
        self::assertSame(1, (int) $added->getQuantityDecimals());
        self::assertSame('kg', $added->getUnitCode());
    }

    public function testAFreeLineIsAllowedByAForcedPriceOfZero(): void
    {
        $this->editor->edit($this->order, new OrderEdit(unitPrices: [$this->lineId() => '0']));

        $line = OrderProductQuery::create()->findPk($this->lineId());

        self::assertEqualsWithDelta(0.0, (float) $line->getPromoPrice(), 0.0001);
        self::assertSame(1, (int) $line->getWasInPromo());
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
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $service = $this->aLine($this->product, 1.0, '3.000000');
        $service->setLineType(OrderProduct::LINE_TYPE_SERVICE)->save();

        $this->expectRefusal(OrderEditRefusal::ServiceLine);
        $this->editor->edit($this->order, new OrderEdit(removedLines: [(int) $service->getId()]));
    }

    public function testTheLinesOfABundleAreLeftAlone(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $bundle = $this->aLine($this->product, 1.0, '30.000000');
        $bundle->setLineType(OrderProduct::LINE_TYPE_BUNDLE)->save();
        $component = $this->aLine($this->product, 2.0, '0.000000');
        $component->setLineType(OrderProduct::LINE_TYPE_BUNDLE_COMPONENT)->setParent($bundle->getId())->save();

        $this->expectRefusal(OrderEditRefusal::BundleLine);
        $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $component->getId() => 4.0]));
    }

    public function testABundleProductCannotBeAddedAsAPlainLine(): void
    {
        $this->placeAnOrder(OrderStatus::CODE_NOT_PAID);
        $this->product->setIsBundle(1)->save();

        $this->expectRefusal(OrderEditRefusal::BundleLine);
        $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $this->product->getDefaultSaleElements()->getId(), 1.0, '20.000000')]));
    }

    public function testAnExemptedOrderIsEditedWithoutTaxLines(): void
    {
        OrderAddressQuery::create()->findPk($this->order->getInvoiceOrderAddressId())->setVatExempted(1)->save();
        OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->delete();

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [(int) $this->line->getId() => '20.000000']));

        self::assertSame(0, OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->count());
    }

    public function testALineEditOfAnExemptedOrderKeepsThePostageVatItWasFrozenWith(): void
    {
        $invoice = $this->exemptedWith('6.00');
        $this->order->setPostage('10.000000')->setPostageTax('0')->setDeliveryModuleId((int) ModuleQuery::create()->findOneByCode('Cheque')->getId())->save($this->getPropelConnection());

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 3.0]));

        self::assertEqualsWithDelta(3.0, (float) OrderProductQuery::create()->findPk($this->lineId())->getQuantity(), 0.0001);
        self::assertEqualsWithDelta(10.0, (float) OrderQuery::create()->findPk($this->order->getId())->getPostage(), 0.0001);
        self::assertEqualsWithDelta(8.0, $this->exemptedVatOf($invoice), 0.0001, '4.00 on the goods and 2.00 on the postage, frozen; the goods now carry 6.00.');
    }

    public function testALineEditOfAnExemptedOrderDoesNotNeedItsCarrierStillInstalled(): void
    {
        $this->exemptedWith('6.00');
        $this->order->setPostage('10.000000')->setPostageTax('0')->setDeliveryModuleId(null)->setDeliveryModuleTitle('Gone carrier')->save($this->getPropelConnection());

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 3.0]));

        self::assertEqualsWithDelta(3.0, (float) OrderProductQuery::create()->findPk($this->lineId())->getQuantity(), 0.0001);
    }

    public function testTheExemptedVatAmountFollowsTheEditedLines(): void
    {
        $invoice = $this->exemptedWith('5.00');

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [$this->lineId() => '20.000000']));

        self::assertEqualsWithDelta(9.0, $this->exemptedVatOf($invoice), 0.0001, '1.00 frozen on the postage, the goods go from 4.00 to 8.00.');
    }

    public function testTheExemptedVatAmountSharesTheDiscountOfTheEditedOrder(): void
    {
        $invoice = $this->exemptedWith('5.00');

        $this->editor->edit($this->order, new OrderEdit(unitPrices: [$this->lineId() => '20.000000'], discount: '10.000000'));

        self::assertEqualsWithDelta(7.0, $this->exemptedVatOf($invoice), 0.0001, '1.00 frozen on the postage, the goods go from 4.00 to 8.00 less a quarter.');
    }

    public function testANewPostageMovesTheExemptedVatByItsOwnVatOnly(): void
    {
        $invoice = $this->exemptedWith('5.00');

        $this->withDeliveryTaxRule(function () use ($invoice): void {
            $this->order->setPostage('5.000000')->setPostageTax('0')->save();

            $this->editor->edit($this->order, new OrderEdit(untaxedPostage: '10.000000'));

            self::assertEqualsWithDelta(6.0, $this->exemptedVatOf($invoice), 0.0001, 'The postage VAT goes from 1.00 to 2.00, the goods keep their 4.00.');
        });
    }

    public function testAnExemptedOrderWithoutAFrozenAmountKeepsNone(): void
    {
        $invoice = $this->exemptedWith(null);

        $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 3.0]));

        self::assertNull(OrderAddressQuery::create()->findPk($invoice)->getVatExemptedAmount());
    }

    public function testAnExplicitPostageIsSplitOverTheGoodsWhenTheShopSpreadsTheTaxAndALineEditLeavesItAlone(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');

        try {
            $reduced = $this->createFixtureFactory();
            $rule = $reduced->taxRule(['isDefault' => false]);
            (new TaxRuleCountry())
                ->setTaxRuleId($rule->getId())
                ->setCountryId($this->country()->getId())
                ->setTaxId($reduced->tax(['requirements' => ['percent' => '10'], 'title' => 'VAT 10'])->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
            $other = $reduced->product($reduced->category(), $rule, $reduced->currency(), ['title' => 'Book', 'baseQuantity' => 10]);

            $this->editor->edit($this->order, new OrderEdit(
                addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0, '20.000000')],
                untaxedPostage: '10.000000',
            ));

            $rows = OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->find();
            $order = OrderQuery::create()->findPk($this->order->getId());

            self::assertCount(2, $rows);
            self::assertEqualsWithDelta(1.5, (float) $order->getPostageTax(), 0.0001);
            self::assertEqualsWithDelta(array_sum(array_map(static fn ($row): float => (float) $row->getAmount(), iterator_to_array($rows))), (float) $order->getPostageTax(), 0.0001);
            self::assertEqualsWithDelta(11.5, (float) $order->getPostage(), 0.0001);

            $this->editor->edit($this->order, new OrderEdit(quantities: [(int) OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductRef($other->getRef())->findOne()->getId() => 3.0]));

            $order = OrderQuery::create()->findPk($this->order->getId());
            $sum = array_sum(array_map(static fn ($row): float => (float) $row->getAmount(), iterator_to_array(OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->find())));

            self::assertEqualsWithDelta($sum, (float) $order->getPostageTax(), 0.0001);
            self::assertEqualsWithDelta(1.5, (float) $order->getPostageTax(), 0.0001);
            self::assertEqualsWithDelta(11.5, (float) $order->getPostage(), 0.0001);
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
    }

    /**
     * The postage is split on what each tax rule of the order is charged, line by line: 1000 units
     * at 0.005 are charged 10.00 under the sum of roundings (0.01 a unit), not 5.00.
     */
    public function testAnEditedPostageIsSplitOnTheLineTotalsTheOrderCharges(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');

        try {
            $reduced = $this->createFixtureFactory();
            $rule = $reduced->taxRule(['isDefault' => false]);
            (new TaxRuleCountry())
                ->setTaxRuleId($rule->getId())
                ->setCountryId($this->country()->getId())
                ->setTaxId($reduced->tax(['requirements' => ['percent' => '10'], 'title' => 'VAT 10'])->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
            $screws = $reduced->product($reduced->category(), $rule, $reduced->currency(), ['title' => 'Screw', 'baseQuantity' => 5000]);

            $this->editor->edit($this->order, new OrderEdit(
                addedLines: [new AddedOrderLine((int) $screws->getDefaultSaleElements()->getId(), 1000.0, '0.005000')],
                untaxedPostage: '10.000000',
            ));

            // Goods at 20 % charged 20.00, goods at 10 % charged 10.00: 6.67 and 3.33 of the postage.
            $order = OrderQuery::create()->findPk($this->order->getId());
            self::assertEqualsWithDelta(1.33 + 0.33, (float) $order->getPostageTax(), 0.001);
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
    }

    public function testALineAddedWithoutAPriceHonoursThePermanentDiscountOfTheCustomer(): void
    {
        $customer = $this->order->getCustomer();
        self::assertNotNull($customer);
        $customer->setDiscount('10')->save();

        $other = $this->createFixtureFactory()->product($this->createFixtureFactory()->category(), $this->createFixtureFactory()->taxRule(['isDefault' => false]), $this->createFixtureFactory()->currency(), ['title' => 'Discounted', 'baseQuantity' => 10, 'basePrice' => 100.0]);

        $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0)]));

        $line = OrderProductQuery::create()->filterByOrderId($this->order->getId())->filterByProductRef($other->getRef())->findOne();
        self::assertNotNull($line);
        self::assertEqualsWithDelta(90.0, (float) $line->getPrice(), 0.0001);
    }

    public function testAnAddedLineAsksTheVirtualProductListenersOnlyOnce(): void
    {
        $other = $this->createFixtureFactory()->product($this->createFixtureFactory()->category(), $this->createFixtureFactory()->taxRule(['isDefault' => false]), $this->createFixtureFactory()->currency(), ['title' => 'Asked once', 'baseQuantity' => 10]);
        $other->setVirtual(1)->save();
        $asked = 0;
        $listener = static function () use (&$asked): void {
            ++$asked;
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE, $listener);

        try {
            $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0)]));
        } finally {
            $dispatcher->removeListener(TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE, $listener);
        }

        self::assertSame(1, $asked, 'The listeners of a virtual product are asked once for a line, not again for its stock.');
    }

    public function testThePostageOfAnOrderPlacedWithASingleRuleIsLeftAloneWhenTheShopSpreadsTheTaxLater(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');

        try {
            $this->order->setPostage('12.000000')->setPostageTax('2.000000')->save($this->getPropelConnection());
            OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->delete($this->getPropelConnection());

            $reduced = $this->createFixtureFactory();
            $rule = $reduced->taxRule(['isDefault' => false]);
            (new TaxRuleCountry())
                ->setTaxRuleId($rule->getId())
                ->setCountryId($this->country()->getId())
                ->setTaxId($reduced->tax(['requirements' => ['percent' => '10'], 'title' => 'VAT 10'])->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
            $other = $reduced->product($reduced->category(), $rule, $reduced->currency(), ['title' => 'Book', 'baseQuantity' => 10]);

            $this->editor->edit($this->order, new OrderEdit(addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 1.0, '20.000000')]));

            $order = OrderQuery::create()->findPk($this->order->getId());
            self::assertEqualsWithDelta(12.0, (float) $order->getPostage(), 0.0001);
            self::assertEqualsWithDelta(2.0, (float) $order->getPostageTax(), 0.0001);
            self::assertSame(0, OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->count());
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
    }

    public function testALineEditNeverChangesThePostageOfAnOrderPlacedWithASpreadTaxWhenTheShopGoesBackToASingleRule(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'single_rule');

        try {
            $connection = $this->getPropelConnection();
            $this->order->setPostage('12.000000')->setPostageTax('2.000000')->save($connection);
            OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->delete($connection);
            (new OrderPostageTax())->setOrderId($this->order->getId())->setTitle('VAT 20')->setUntaxedAmount('10')->setAmount('2')->save($connection);

            $this->editor->edit($this->order, new OrderEdit(quantities: [$this->lineId() => 4.0]));

            $order = OrderQuery::create()->findPk($this->order->getId());
            $rows = OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->find();

            self::assertEqualsWithDelta(12.0, (float) $order->getPostage(), 0.0001);
            self::assertEqualsWithDelta(2.0, (float) $order->getPostageTax(), 0.0001);
            self::assertCount(1, $rows);
            self::assertEqualsWithDelta(2.0, (float) $rows->getFirst()->getAmount(), 0.0001);
            self::assertNull($this->historyPostage(), 'The history does not say the postage moved.');
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
    }

    public function testAnUntaxedPostageIsRoundedToTheCentLikeAtCheckout(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');

        try {
            $this->editor->edit($this->order, new OrderEdit(untaxedPostage: '10.005000'));

            $order = OrderQuery::create()->findPk($this->order->getId());
            $untaxed = array_sum(array_map(static fn ($row): float => (float) $row->getUntaxedAmount(), iterator_to_array(OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->find())));

            self::assertEqualsWithDelta((float) $order->getPostage() - (float) $order->getPostageTax(), $untaxed, 0.0001);
            self::assertEqualsWithDelta(10.01, $untaxed, 0.0001);
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
    }

    public function testAFreePostageNeverAsksTheDeliveryModuleWhenALineChanges(): void
    {
        $previous = ConfigQuery::read('postage_tax_strategy');
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');

        try {
            $this->order->setPostage('0')->setPostageTax('0')->setDeliveryModuleId((int) ModuleQuery::create()->findOneByCode('Cheque')->getId())->save($this->getPropelConnection());
            OrderPostageTaxQuery::create()->filterByOrderId($this->order->getId())->delete($this->getPropelConnection());
            (new OrderPostageTax())->setOrderId($this->order->getId())->setTitle('Free postage')->setUntaxedAmount('0')->setAmount('0')->save($this->getPropelConnection());
            $line = OrderProductQuery::create()->filterByOrderId($this->order->getId())->findOne();

            $this->editor->edit($this->order, new OrderEdit(quantities: [(int) $line->getId() => 4.0]));

            $order = OrderQuery::create()->findPk($this->order->getId());
            self::assertEqualsWithDelta(0.0, (float) $order->getPostage(), 0.0001);
            self::assertEqualsWithDelta(0.0, (float) $order->getPostageTax(), 0.0001);
            self::assertEqualsWithDelta(4.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity(), 0.0001);
        } finally {
            ConfigQuery::write('postage_tax_strategy', (string) $previous);
        }
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
        self::assertSame('1.000000', (string) OrderQuery::create()->findPk($this->order->getId())->getDiscount());

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
            self::assertEqualsWithDelta($sql, OrderQuery::create()->findPk($this->order->getId())->getTotalAmount($tax, true, true), 0.0001);
        });
    }

    public function testTheDocumentsOfAnEditedOrderStillRender(): void
    {
        $other = $this->aProduct(['title' => 'Gadget', 'basePrice' => 25.0, 'baseQuantity' => 7]);

        $this->editor->edit($this->order, new OrderEdit(
            addedLines: [new AddedOrderLine((int) $other->getDefaultSaleElements()->getId(), 2.0)],
            quantities: [$this->lineId() => 1.0],
            discount: '5.000000',
        ));

        $tester = new CommandTester((new Application(self::$kernel))->find('pdf:render'));
        $html = '';
        $capture = static function (PdfEvent $event) use (&$html): void {
            $html = (string) $event->getContent();
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::GENERATE_PDF, $capture, 1000);

        foreach (['invoice', 'delivery'] as $document) {
            $html = '';
            $file = sys_get_temp_dir().'/edited_order_'.$document.'_'.uniqid('', true).'.pdf';

            try {
                $tester->execute(['document' => $document, '--order' => (string) $this->order->getId(), '--locale' => 'fr_FR', '--out' => $file]);

                $tester->assertCommandIsSuccessful();
                self::assertStringStartsWith('%PDF', (string) file_get_contents($file));
                self::assertStringContainsString('Gadget', $html, \sprintf('The %s carries the line added by the edit.', $document));

                if ('invoice' === $document) {
                    self::assertMatchesRegularExpression('/Remise TTC<\/p>\s*<\/td>\s*<td[^>]*><p>5,00/', $html, 'The invoice shows the discount set by the edit.');
                }
            } finally {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        $dispatcher->removeListener(TheliaEvents::GENERATE_PDF, $capture);
    }

    private function historyPostage(): mixed
    {
        return OrderHistoryQuery::create()->filterByOrderId($this->order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->findOne()?->getDecodedPayload()['postage'] ?? null;
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

    private function exemptedWith(?string $frozenAmount): int
    {
        $invoice = OrderAddressQuery::create()->findPk($this->order->getInvoiceOrderAddressId());
        $invoice->setVatExempted(1)->setVatExemptedAmount($frozenAmount)->save();
        OrderProductTaxQuery::create()->filterByOrderProductId($this->line->getId())->delete();

        return (int) $invoice->getId();
    }

    private function exemptedVatOf(int $invoiceAddressId): float
    {
        return (float) OrderAddressQuery::create()->findPk($invoiceAddressId)->getVatExemptedAmount();
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
