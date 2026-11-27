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

namespace Thelia\Tests\Integration\Controller\Front;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BasePaymentModuleController;
use Thelia\Test\IntegrationTestCase;

/**
 * A payment module reaches these two redirects when the customer comes back from the
 * gateway, which is both the least testable and the least forgiving moment of a shop.
 * The route identifiers are plain strings resolved at that very instant, so they are
 * pinned here against the running container.
 */
final class BasePaymentModuleControllerTest extends IntegrationTestCase
{
    public function testAConfirmedPaymentSendsTheCustomerToTheOrderConfirmationPage(): void
    {
        $url = $this->urlOfRedirect(static fn (PaymentControllerUnderTest $controller) => $controller->redirectToSuccessPage(42));

        self::assertStringEndsWith('/checkout/confirm?order_id=42', $url);
    }

    public function testAFailedPaymentSendsTheCustomerToTheFailurePageWithTheOrderAndTheReason(): void
    {
        $url = $this->urlOfRedirect(
            static fn (PaymentControllerUnderTest $controller) => $controller->redirectToFailurePage(42, 'Card declined'),
        );

        self::assertStringEndsWith('/checkout/failed?order_id=42&message=Card%20declined', $url);
    }

    public function testAFailedPaymentWithoutAReasonStillReachesTheFailurePage(): void
    {
        $url = $this->urlOfRedirect(static fn (PaymentControllerUnderTest $controller) => $controller->redirectToFailurePage(42, null));

        self::assertStringEndsWith('/checkout/failed?order_id=42', $url);
    }

    /**
     * Payment gateways name their transactions with references such as "PSP-4F2A-7C10":
     * the column they are stored in is a 100-character string, so the reference the
     * module hands over must reach it as it was issued.
     */
    public function testTheTransactionReferenceOfTheGatewayIsSavedOnTheOrder(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->controller()->saveTransactionRef(
            $this->getService(EventDispatcherInterface::class),
            (int) $order->getId(),
            'PSP-4F2A-7C10',
        );

        self::assertSame(
            'PSP-4F2A-7C10',
            OrderQuery::create()->findPk($order->getId())->getTransactionRef(),
        );
    }

    public function testAPaymentConfirmationIsHeldToTheStatusTransitionGraph(): void
    {
        $order = $this->createFixtureFactory()->order();
        $notPaid = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID);
        $canceled = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED);
        self::assertNotNull($notPaid);
        self::assertNotNull($canceled);

        // A merchant who only lets an unpaid order be canceled: the gateway's confirmation
        // takes the same road as the back office and is refused like it.
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets($notPaid->getId(), [$canceled->getId()]);

        try {
            $this->controller()->confirmPayment($this->getService(EventDispatcherInterface::class), (int) $order->getId());
            self::fail('The confirmation should have been refused by the transition graph.');
        } catch (OrderStatusTransitionRefusedException $exception) {
            self::assertSame(OrderStatus::CODE_PAID, $exception->getToStatusCode());
        }

        self::assertSame($notPaid->getId(), OrderQuery::create()->findPk($order->getId())->getStatusId());
    }

    /**
     * The confirmation of a gateway is a status change like another: the stock action the
     * merchant set on it decides, here in a shop that takes its stock at shipping and leaves
     * it alone at payment.
     */
    public function testAPaymentConfirmationLeavesTheStockToTheStockActionOfTheStatusChange(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $productSaleElements = $factory->productSaleElement($product, ['quantity' => 10]);
        $order = $factory->order();
        $factory->orderProduct($order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $productSaleElements->getRef(),
            'productSaleElementsId' => $productSaleElements->getId(),
            'quantity' => 3.0,
        ]);
        $paid = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);
        self::assertNotNull($paid);
        $action = (new OrderStatusAction())
            ->setTriggerType(OrderStatusActionTrigger::ENTER->value)
            ->setToStatusId($paid->getId())
            ->setActionType(AdjustStockAction::getType())
            ->setDecodedPayload([AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_NONE])
            ->setPosition(1)
            ->setActive(true);
        $action->save();

        // The stock of the order is left for its payment: without the action, the fixed rule
        // of the core takes it when the gateway confirms.
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $manageStockEvent = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $stockLeftForThePayment = static function (ManageStockOnCreationEvent $event): void {
            $event->setManageStock(false);
        };
        $dispatcher->addListener($manageStockEvent, $stockLeftForThePayment, -1024);

        try {
            $this->controller()->confirmPayment($dispatcher, (int) $order->getId());
        } finally {
            $dispatcher->removeListener($manageStockEvent, $stockLeftForThePayment);
        }

        self::assertSame($paid->getId(), OrderQuery::create()->findPk($order->getId())->getStatusId());
        self::assertSame(
            10.0,
            (float) ProductSaleElementsQuery::create()
                ->filterById($productSaleElements->getId())
                ->select(ProductSaleElementsTableMap::COL_QUANTITY)
                ->findOne(),
            'The payment took no stock: the action leaves it as it is.',
        );

        $statusLine = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->findOne();
        self::assertNotNull($statusLine);
        self::assertSame(
            ['stock' => 'none', 'stock_action_id' => $action->getId()],
            array_intersect_key($statusLine->getDecodedPayload(), ['stock' => true, 'stock_action_id' => true]),
        );
    }

    private function urlOfRedirect(callable $redirect): string
    {
        try {
            $redirect($this->controller());
        } catch (RedirectException $redirectException) {
            return $redirectException->getUrl();
        }

        self::fail('Expected a RedirectException to be thrown.');
    }

    private function controller(): PaymentControllerUnderTest
    {
        $container = static::getContainer();

        $controller = new PaymentControllerUnderTest();
        $controller->container = $container;
        $controller->securityContext = $container->get(SecurityContext::class);
        $controller->requestStack = $container->get('request_stack');
        $controller->translator = $container->get('thelia.translator');

        return $controller;
    }
}

/**
 * Stands in for the return-from-gateway controller of a payment module: it inherits the
 * wiring under test and only names the module the log file is written for.
 */
final class PaymentControllerUnderTest extends BasePaymentModuleController
{
    protected function getModuleCode(): string
    {
        return 'TestPayment';
    }
}
