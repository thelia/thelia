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

namespace Thelia\Tests\Integration\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Action\OrderReturn as OrderReturnAction;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\OrderReturnStateMachine;
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\OrderReturn\Service\StockIncrementer;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;

/**
 * Drives a return through the state machine and checks the side effects the
 * merchant relies on: the reception restocks the resellable goods, recomputes
 * the refund, and an illegal transition is rejected.
 */
final class OrderReturnActionTest extends ActionIntegrationTestCase
{
    private ?RecordingMailerFactory $mailer = null;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testReceivingARestocksTheResellableQuantity(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: true);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(
            OrderReturnStatus::CODE_RECEIVED,
            $return->getOrderReturnStatus()?->getCode(),
        );
        self::assertSame(12.0, $this->stockOf($pse));

        // Two units received at 10.00, no tax and no discount on this order:
        // any other number means the refund was computed on something else.
        self::assertSame(20.0, (float) $return->getRefundAmount());
    }

    public function testANonResellableLineIsNotRestockedInResellableMode(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: false);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(10.0, $this->stockOf($pse));
    }

    public function testTheNeverModeKeepsTheStockUntouched(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_NEVER);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: true);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(10.0, $this->stockOf($pse));
    }

    public function testAnIllegalTransitionIsRejected(): void
    {
        [$return] = $this->acceptedReturn(receivedQuantity: 0.0, resellable: false);

        // accepted -> settled is not an authorized edge.
        $this->expectException(ReturnNotAllowedException::class);
        $this->transition($return, OrderReturnStatus::CODE_SETTLED);
    }

    /**
     * The state machine is the contract the merchant screens and the API both
     * lean on. Testing the forbidden edges on the constant only proves the
     * table; what has to hold is that the action refuses them, under the row
     * lock it takes, whatever the caller.
     */
    #[DataProvider('forbiddenTransitions')]
    public function testAForbiddenTransitionIsRefusedByTheAction(string $from, string $to): void
    {
        [$return] = $this->returnInStatus($from);

        $this->expectException(ReturnNotAllowedException::class);
        $this->transition($return, $to);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbiddenTransitions(): iterable
    {
        yield 'requested -> received' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_RECEIVED];
        yield 'requested -> settled' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_SETTLED];
        yield 'info_awaited -> accepted' => [OrderReturnStatus::CODE_INFO_AWAITED, OrderReturnStatus::CODE_ACCEPTED];
        yield 'received -> accepted' => [OrderReturnStatus::CODE_RECEIVED, OrderReturnStatus::CODE_ACCEPTED];
        yield 'refused -> accepted' => [OrderReturnStatus::CODE_REFUSED, OrderReturnStatus::CODE_ACCEPTED];
        yield 'settled -> received' => [OrderReturnStatus::CODE_SETTLED, OrderReturnStatus::CODE_RECEIVED];
        yield 'expired -> received' => [OrderReturnStatus::CODE_EXPIRED, OrderReturnStatus::CODE_RECEIVED];
    }

    /**
     * The story asks for the customer to be told at every step. The mail is
     * dispatched by the transition itself, and what the core owes the template
     * is the set of variables below - the wording belongs to the theme.
     */
    public function testEveryStepOfTheCycleMailsTheCustomer(): void
    {
        [$return] = $this->returnInStatus(OrderReturnStatus::CODE_REQUESTED, receivedQuantity: 2.0, resellable: true);

        $this->recordedTransition($return, OrderReturnStatus::CODE_ACCEPTED);
        $this->recordedTransition($return, OrderReturnStatus::CODE_RECEIVED);
        $this->recordedTransition($return, OrderReturnStatus::CODE_SETTLED);

        $sent = $this->mailer->parametersOfMessagesSent('order_return_status_changed');

        self::assertCount(3, $sent, 'One mail is owed per status change.');
        self::assertSame(
            [
                OrderReturnStatus::CODE_ACCEPTED,
                OrderReturnStatus::CODE_RECEIVED,
                OrderReturnStatus::CODE_SETTLED,
            ],
            array_column($sent, 'status_code'),
        );
    }

    public function testTheStatusMailCarriesWhatTheTemplateNeeds(): void
    {
        [$return] = $this->returnInStatus(OrderReturnStatus::CODE_ACCEPTED, receivedQuantity: 2.0, resellable: true);

        $this->recordedTransition($return, OrderReturnStatus::CODE_RECEIVED);

        $sent = $this->mailer->parametersOfMessagesSent('order_return_status_changed');
        self::assertCount(1, $sent);

        self::assertSame($return->getId(), $sent[0]['return_id']);
        self::assertSame($return->getRef(), $sent[0]['return_ref']);
        self::assertSame($return->getOrderId(), $sent[0]['order_id']);
        self::assertSame($return->getOrder()?->getRef(), $sent[0]['order_ref']);
        self::assertSame(OrderReturnStatus::CODE_RECEIVED, $sent[0]['status_code']);
        self::assertNull($sent[0]['refusal_reason']);
    }

    /**
     * The only variable that is not always there: the reason a merchant refuses
     * a return, which the mail has to carry or the customer is told no without
     * being told why.
     */
    public function testTheRefusalMailCarriesTheReasonTheMerchantGave(): void
    {
        [$return] = $this->returnInStatus(OrderReturnStatus::CODE_REQUESTED);

        $this->recordedTransition($return, OrderReturnStatus::CODE_REFUSED, 'The item came back used.');

        $sent = $this->mailer->parametersOfMessagesSent('order_return_status_changed');
        self::assertCount(1, $sent);
        self::assertSame(OrderReturnStatus::CODE_REFUSED, $sent[0]['status_code']);
        self::assertSame('The item came back used.', $sent[0]['refusal_reason']);
    }

    /**
     * Drives the transition through an action wired to a recording mailer, on a
     * dispatcher of its own so the mail the transition sends comes back here
     * rather than going out through the shop's.
     */
    private function recordedTransition(OrderReturn $return, string $toCode, ?string $refusalReason = null): void
    {
        $target = OrderReturnStatusQuery::create()->findOneByCode($toCode);

        $event = (new OrderReturnEvent($return))
            ->setTargetStatusId((int) $target?->getId())
            ->setRefusalReason($refusalReason);

        $this->recordingDispatcher()->dispatch($event, TheliaEvents::ORDER_RETURN_UPDATE_STATUS);
    }

    private function recordingDispatcher(): EventDispatcher
    {
        $this->mailer ??= new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new OrderReturnAction(
            $this->mailer,
            $this->getService(OrderReturnStateMachine::class),
            $this->getService(StockIncrementer::class),
            $this->getService(RefundAmountCalculator::class),
            $this->getService(ReturnEligibilityChecker::class),
            $this->getService(OrderReturnComposer::class),
        ));

        return $dispatcher;
    }

    private function transition(OrderReturn $return, string $toCode): void
    {
        $target = OrderReturnStatusQuery::create()->findOneByCode($toCode);

        $event = (new OrderReturnEvent($return))->setTargetStatusId((int) $target?->getId());

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_UPDATE_STATUS);
    }

    /**
     * @return array{OrderReturn, ProductSaleElements}
     */
    private function acceptedReturn(float $receivedQuantity, bool $resellable): array
    {
        return $this->returnInStatus(OrderReturnStatus::CODE_ACCEPTED, $receivedQuantity, $resellable);
    }

    /**
     * @return array{OrderReturn, ProductSaleElements}
     */
    private function returnInStatus(string $statusCode, float $receivedQuantity = 0.0, bool $resellable = false): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
        $pse = $this->factory->productSaleElement($product, ['quantity' => 10]);

        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProduct($order, $pse, quantity: 3.0);

        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId((int) $pse->getId())
            ->setQuantity(3.0)
            ->setQuantityReceived($receivedQuantity)
            ->setResellable($resellable)
            ->save($this->getPropelConnection());

        return [$return, $pse];
    }

    private function orderProduct(Order $order, ProductSaleElements $pse, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef((string) $pse->getRef())
            ->setProductSaleElementsId((int) $pse->getId())
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }

    private function stockOf(ProductSaleElements $pse): float
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return (float) ProductSaleElementsQuery::create()
            ->findPk($pse->getId(), $this->getPropelConnection())
            ?->getQuantity();
    }
}
