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

use Cheque\Cheque;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The line announcing a status change is written before the e-mail the change triggers.
 *
 * Timeline order is the order of the primary keys: two lines written in the same second
 * cannot be told apart by their date. The status line therefore has to be written by a
 * listener that runs before the ones sending e-mails.
 */
final class OrderHistoryStatusPrecedesEmailTest extends ActionIntegrationTestCase
{
    public function testTheChequeConfirmationEmailComesAfterTheStatusLine(): void
    {
        ConfigQuery::write('store_email', 'shop@example.com');
        $order = $this->factory->order();
        $order->setPaymentModuleId(Cheque::getModuleId())->save($this->getPropelConnection());

        $this->pay($order, 'Cheque');

        $status = $this->linesOf($order, OrderHistoryEventType::STATUS_CHANGED)[0] ?? null;
        $mail = $this->linesOf($order, OrderHistoryEventType::EMAIL_SENT)[0] ?? null;

        self::assertNotNull($status);
        self::assertNotNull($mail);
        self::assertSame(['message_code' => 'order_confirmation_cheque'], $mail->getDecodedPayload());
        self::assertLessThan($mail->getId(), $status->getId(), 'The status line must precede the e-mail line.');
    }

    public function testAListenerBelowTheCoreOneButAboveTheChequeOneStillWritesAfterTheStatusLine(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        // Stands for the e-mail of a module that runs just under the core listener.
        $this->dispatcher->addListener(
            TheliaEvents::ORDER_UPDATE_STATUS,
            static function () use ($recorder, $order): void {
                $recorder->recordEmailSent($order->getId(), 'a_module_message');
            },
            126,
        );

        $this->pay($order);

        $status = $this->linesOf($order, OrderHistoryEventType::STATUS_CHANGED)[0] ?? null;
        $mail = $this->linesOf($order, OrderHistoryEventType::EMAIL_SENT)[0] ?? null;

        self::assertNotNull($status);
        self::assertNotNull($mail);
        self::assertLessThan($mail->getId(), $status->getId());
    }

    private function pay(Order $order, ?string $moduleCode = null): void
    {
        $paid = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);
        self::assertNotNull($paid);

        $event = new OrderEvent($order);
        $event->setStatus($paid->getId());

        if (null !== $moduleCode) {
            $event->setSourceModuleCode($moduleCode);
        }

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    /**
     * @return list<OrderHistory>
     */
    private function linesOf(Order $order, OrderHistoryEventType $type): array
    {
        return array_values(
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType($type->value)
                ->orderById()
                ->find()
                ->getData(),
        );
    }
}
