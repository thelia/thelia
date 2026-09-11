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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonCreateEvent;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonDeleteEvent;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnReasonQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Return reasons are a reference table the merchant configures, exactly like
 * order statuses: the configuration screen dispatches, the core writes. Without
 * these events a back-office controller has to persist the rows itself, which
 * is the one thing a Thelia controller never does.
 */
final class OrderReturnReasonActionTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testCreatingAReasonByDispatchWritesItAndItsWording(): void
    {
        $code = 'reason-'.uniqid();

        $event = (new OrderReturnReasonCreateEvent())
            ->setCode($code)
            ->setVisible(true)
            ->setLocale('fr_FR')
            ->setTitle('Article abîmé')
            ->setDescription('Le colis est arrivé ouvert.');

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_REASON_CREATE);

        $reason = $event->getOrderReturnReason();
        self::assertNotNull($reason, 'The action must hand the written reason back on the event.');
        self::assertNotNull($reason->getId());

        $stored = OrderReturnReasonQuery::create()->findOneByCode($code, $this->getPropelConnection());
        self::assertNotNull($stored);
        self::assertTrue((bool) $stored->getVisible());
        self::assertSame('Article abîmé', $stored->setLocale('fr_FR')->getTitle());
        self::assertSame('Le colis est arrivé ouvert.', $stored->setLocale('fr_FR')->getDescription());
    }

    public function testANewReasonIsAppendedAfterTheLastOne(): void
    {
        $last = OrderReturnReasonQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->findOne($this->getPropelConnection());

        $event = (new OrderReturnReasonCreateEvent())
            ->setCode('reason-'.uniqid())
            ->setLocale('en_US')
            ->setTitle('A new reason');

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_REASON_CREATE);

        self::assertSame(
            (int) ($last?->getPosition() ?? 0) + 1,
            (int) $event->getOrderReturnReason()?->getPosition(),
        );
    }

    public function testUpdatingAReasonChangesItInPlace(): void
    {
        $reason = $this->reason('Original');

        $event = (new OrderReturnReasonUpdateEvent((int) $reason->getId()))
            ->setCode((string) $reason->getCode())
            ->setVisible(false)
            ->setLocale('en_US')
            ->setTitle('Renamed');

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_REASON_UPDATE);

        // Read back from the database rather than from the instance the action
        // touched: the i18n rows are what the merchant screen will show again.
        $stored = OrderReturnReasonQuery::create()->findPk($reason->getId(), $this->getPropelConnection());
        self::assertNotNull($stored);
        self::assertFalse((bool) $stored->getVisible());
        self::assertSame('Renamed', $stored->setLocale('en_US')->getTitle());
    }

    public function testMovingAReasonReordersTheList(): void
    {
        $first = $this->reason('First');
        $second = $this->reason('Second');

        $firstPosition = (int) $first->getPosition();
        $secondPosition = (int) $second->getPosition();
        self::assertLessThan($secondPosition, $firstPosition);

        $event = new UpdatePositionEvent(
            (int) $second->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            $firstPosition,
        );

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_REASON_UPDATE_POSITION);

        $second->reload(true, $this->getPropelConnection());
        self::assertSame($firstPosition, (int) $second->getPosition());
    }

    /**
     * The reason is a merchant's list entry, not an archive: it can go. What it
     * must not take with it is the returns that named it - the foreign key is
     * nulled and the wording lives on as the snapshot taken when the return was
     * opened.
     */
    public function testDeletingAReasonKeepsTheReturnsThatNamedIt(): void
    {
        $reason = $this->reason('Damaged');
        $return = $this->returnNaming($reason);

        $this->dispatch(
            new OrderReturnReasonDeleteEvent((int) $reason->getId()),
            TheliaEvents::ORDER_RETURN_REASON_DELETE,
        );

        self::assertNull(
            OrderReturnReasonQuery::create()->findPk($reason->getId(), $this->getPropelConnection()),
            'The reason was not deleted.',
        );

        $reloaded = OrderReturnQuery::create()->findPk($return->getId(), $this->getPropelConnection());
        self::assertNotNull($reloaded, 'Deleting a reason took a return down with it.');
        self::assertNull($reloaded->getReasonId());
        self::assertSame('Damaged', $reloaded->getReasonTitle());
    }

    private function reason(string $title): OrderReturnReason
    {
        $event = (new OrderReturnReasonCreateEvent())
            ->setCode('reason-'.uniqid())
            ->setLocale('en_US')
            ->setTitle($title);

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_REASON_CREATE);

        $reason = $event->getOrderReturnReason();
        self::assertNotNull($reason);

        return $reason;
    }

    private function returnNaming(OrderReturnReason $reason): OrderReturn
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED))
            ->setOrderReturnReason($reason)
            ->setReasonTitle((string) $reason->setLocale('en_US')->getTitle());
        $return->save($this->getPropelConnection());

        return $return;
    }
}
