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

namespace Thelia\Tests\Integration\Domain\Payment;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\EventListener\RecordImmediateCaptureListener;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * The capture line of the modules that keep no journal: Cheque, FreeOrder and every
 * published module get it the first time their order is paid, without a line of code.
 */
final class ImmediateCaptureTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    public function testAChequeOrderMarkedPaidGetsOneCaptureOfItsTotalAuthoredByTheModule(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        $journal = OrderPaymentTransactionQuery::create()->findJournal($order->getId());
        self::assertCount(1, $journal);

        /** @var OrderPaymentTransaction $line */
        $line = $journal[0];
        self::assertSame(PaymentTransactionType::CAPTURE->value, $line->getType());
        self::assertTrue($line->isSucceeded());
        self::assertSame('120.000000', $line->getAmount());
        self::assertNull($line->getPspReference(), 'A cheque has no provider reference.');
        self::assertSame(OrderHistoryActorType::MODULE->value, $line->getActorType());
        self::assertSame('Cheque', $line->getActorLabel());
        self::assertSame($order->getPaymentModuleId(), $line->getPaymentModuleId());
    }

    public function testAChequeOrderAnAdministratorMarksPaidIsStillCapturedByTheModuleAndNamesTheAdministrator(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);
        $admin = $this->factory->admin();

        // The gesture of the back office: the request is an admin one, the administrator in session.
        $requestStack = $this->getService('request_stack');
        $testRequest = $requestStack->pop();
        $backOfficeRequest = Request::create('http://localhost/admin/order/update/'.$order->getId());
        $backOfficeRequest->setSession($testRequest->getSession());
        $requestStack->push($backOfficeRequest);
        $securityContext = $this->getService(SecurityContext::class);
        $securityContext->setAdminUser($admin);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        } finally {
            $securityContext->clearAdminUser();
            $requestStack->pop();
            $requestStack->push($testRequest);
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0];
        self::assertSame(OrderHistoryActorType::MODULE->value, $line->getActorType(), 'The cheque is taken through its module, not by the administrator.');
        self::assertSame('Cheque', $line->getActorLabel());
        self::assertSame($admin->getId(), $line->getAdminId(), 'The administrator who marked the order paid stays named on the line.');
    }

    public function testTheTransactionReferenceOfTheOrderIsUntouchedAndCarriedOnTheLine(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        $order->setTransactionRef('PSP-4F2A-7C10')->save();

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame('PSP-4F2A-7C10', OrderQuery::create()->findPk($order->getId())->getTransactionRef());
        self::assertSame('PSP-4F2A-7C10', OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0]->getPspReference());
    }

    public function testMovingAPaidOrderOnWritesNothingMore(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);
        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testAnOrderPaidTwiceGetsOneLine(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testAnOrderThatCostsNothingGetsAZeroCapture(): void
    {
        $order = $this->factory->order(null, ['paymentModuleCode' => 'FreeOrder']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        $journal = OrderPaymentTransactionQuery::create()->findJournal($order->getId());
        self::assertCount(1, $journal);
        self::assertSame('0.000000', $journal[0]->getAmount());
        self::assertSame('FreeOrder', $journal[0]->getActorLabel());
    }

    public function testTheSourceModuleOfTheEventIsTheAuthorWhenItNamesItself(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);

        $event = (new OrderEvent($order))
            ->setStatus($this->orderStatus(OrderStatus::CODE_PAID)->getId())
            ->setSourceModuleCode('SomeGateway');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame('SomeGateway', OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0]->getActorLabel());
    }

    public function testAModuleThatDefersItsCaptureIsLeftAlone(): void
    {
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertCount(0, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testAModuleThatSwitchedItsDeferredCaptureOffGetsTheLineLikeAnyOther(): void
    {
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
        DeferredCapturePaymentModule::$deferredCapture = false;
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testTwoConcurrentNotificationsOfTheSamePaymentLeaveOneLine(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        $paidStatusId = $this->orderStatus(OrderStatus::CODE_PAID)->getId();

        // Both events are built before either is handled, each on its own instance of
        // the order, both still seeing it unpaid — what two notifications of the same
        // payment look like when they arrive at the same time.
        $firstOrder = OrderQuery::create()->findPk($order->getId());
        OrderTableMap::clearInstancePool();
        $secondOrder = OrderQuery::create()->findPk($order->getId());
        self::assertNotSame($firstOrder, $secondOrder);

        $firstEvent = (new OrderEvent($firstOrder))->setStatus($paidStatusId);
        $secondEvent = (new OrderEvent($secondOrder))->setStatus($paidStatusId);

        $this->dispatch($firstEvent, TheliaEvents::ORDER_UPDATE_STATUS);
        $this->dispatch($secondEvent, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testMarkingPaidAnOrderAnAuthorizationStillHoldsWritesNoCaptureNobodyTook(): void
    {
        // The module took its deferred capture off while an authorization was open; an
        // administrator then marks the order paid by hand. No provider took anything.
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->getService(PaymentTransactionRecorder::class)->recordAuthorization($order, 100, 'AUTH-1');
        DeferredCapturePaymentModule::$deferredCapture = false;

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode(), 'The status change goes through.');
        self::assertSame(
            0,
            OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::CAPTURE)->count(),
            'No capture is invented on top of an open authorization.',
        );
    }

    public function testAModuleThatWroteItsOwnCaptureGetsNoSecondLine(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        $order->setTransactionRef('ORDER-LEVEL-REF')->save();
        $this->getService(PaymentTransactionRecorder::class)->recordCapture($order, 120, 'PSP-OWN-REF', moduleCode: 'Cheque');

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    /**
     * The module gave the cheque back on its own and the order says refunded: the journal
     * gives back what it counted as taken, and the order turned unpaid again no longer
     * reads as paid for.
     */
    public function testAChequeOrderRefundedGetsTheRefundOfWhatItsCaptureTook(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertCount(2, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
        $refund = OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::REFUND)->findOne();
        self::assertNotNull($refund);
        self::assertTrue($refund->isSucceeded());
        self::assertSame('120.000000', $refund->getAmount());
        self::assertSame('Cheque', $refund->getActorLabel());

        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);

        self::assertFalse(OrderQuery::create()->findPk($order->getId())->isPaymentSecured(), 'The money went back with the refund.');
    }

    public function testAChequeOrderPaidAgainAfterItsRefundGetsANewCaptureSoTheJournalIsNotNetZero(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);
        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        $query = OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId());
        self::assertSame(2, (clone $query)->filterByTypeEnum(PaymentTransactionType::CAPTURE)->count());
        self::assertSame(1, (clone $query)->filterByTypeEnum(PaymentTransactionType::REFUND)->count());
        $last = (clone $query)->filterByTypeEnum(PaymentTransactionType::CAPTURE)->orderById('desc')->findOne();
        self::assertSame('120.000000', $last->getAmount());
        self::assertSame('Cheque', $last->getActorLabel());
        self::assertTrue(OrderQuery::create()->findPk($order->getId())->isPaymentSecured(), 'The journal holds the money again.');
    }

    public function testAnOrderWhoseJournalStillHoldsANetCapturedAmountGetsNoSecondCaptureWhenPaidAgain(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(1, OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::CAPTURE)->count());
    }

    public function testAModuleThatWroteItsOwnRefundGetsNoSecondOne(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);
        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->getService(PaymentTransactionRecorder::class)->recordRefund($order, 50, 'REFUND-OWN-REF', moduleCode: 'Cheque');

        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame(
            1,
            OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::REFUND)->count(),
            'The refund the module wrote tells the story.',
        );
    }

    public function testAModuleThatDefersItsCaptureGetsNoRefundFromItsStatus(): void
    {
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $recorder = $this->getService(PaymentTransactionRecorder::class);
        $recorder->recordAuthorization($order, 120, 'AUTH-1');
        $recorder->recordCapture($order, 120, 'CAP-1');

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame(0, OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::REFUND)->count());
    }

    public function testTheCaptureLineIsWrittenAfterTheCoreListenersOfThePaidStatus(): void
    {
        $listeners = $this->dispatcher->getListeners(TheliaEvents::ORDER_UPDATE_STATUS);
        $priority = null;

        foreach ($listeners as $listener) {
            if (\is_array($listener) && $listener[0] instanceof RecordImmediateCaptureListener) {
                $priority = $this->dispatcher->getListenerPriority(TheliaEvents::ORDER_UPDATE_STATUS, $listener);
            }
        }

        self::assertNotNull($priority);
        self::assertLessThan(5, $priority, 'Below the status action runner, so a failure here cannot cut it off.');
    }

    private function registerTheDeferredCaptureModule(): void
    {
        if (null !== ModuleQuery::create()->findOneByCode(DeferredCapturePaymentModule::getModuleCode())) {
            return;
        }

        (new Module())
            ->setCode(DeferredCapturePaymentModule::getModuleCode())
            ->setFullNamespace(DeferredCapturePaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->save($this->getPropelConnection());
    }
}
