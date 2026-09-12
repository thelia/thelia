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

namespace Thelia\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonCreateEvent;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonDeleteEvent;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonEvent;
use Thelia\Core\Event\OrderReturnReason\OrderReturnReasonUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\OrderReturnReason as OrderReturnReasonModel;
use Thelia\Model\OrderReturnReasonQuery;

/**
 * The return reasons a customer picks from, written the way every other
 * reference table of the core is: the configuration screen describes what the
 * merchant asked for and dispatches, the action writes it.
 *
 * A reason can be deleted even when returns name it - `order_return.reason_id`
 * is nulled and the wording survives as the snapshot taken when the return was
 * opened, so nothing of the customer's answer is lost.
 */
class OrderReturnReason extends BaseAction implements EventSubscriberInterface
{
    public function create(OrderReturnReasonCreateEvent $event): void
    {
        $this->createOrUpdate($event, new OrderReturnReasonModel());
    }

    public function update(OrderReturnReasonUpdateEvent $event): void
    {
        $this->createOrUpdate($event, $this->getOrderReturnReason($event));
    }

    public function delete(OrderReturnReasonDeleteEvent $event): void
    {
        $reason = $this->getOrderReturnReason($event);
        $reason->delete();

        $event->setOrderReturnReason($reason);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(OrderReturnReasonQuery::create(), $event, $dispatcher);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_RETURN_REASON_CREATE => ['create', 128],
            TheliaEvents::ORDER_RETURN_REASON_UPDATE => ['update', 128],
            TheliaEvents::ORDER_RETURN_REASON_DELETE => ['delete', 128],
            TheliaEvents::ORDER_RETURN_REASON_UPDATE_POSITION => ['updatePosition', 128],
        ];
    }

    protected function createOrUpdate(OrderReturnReasonEvent $event, OrderReturnReasonModel $reason): void
    {
        $reason
            ->setCode($event->getCode())
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription());

        // A new reason goes at the end of the merchant's list; an existing one
        // keeps the rank it was given.
        if (null === $reason->getId()) {
            $last = OrderReturnReasonQuery::create()->orderByPosition(Criteria::DESC)->findOne();
            $reason->setPosition(null !== $last ? (int) $last->getPosition() + 1 : 1);
        }

        $reason->save();

        $event->setOrderReturnReason($reason);
    }

    protected function getOrderReturnReason(OrderReturnReasonUpdateEvent $event): OrderReturnReasonModel
    {
        $reason = OrderReturnReasonQuery::create()->findPk($event->getId());

        if (null === $reason) {
            throw new \LogicException('Order return reason not found');
        }

        return $reason;
    }
}
