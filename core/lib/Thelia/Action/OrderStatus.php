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
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus as OrderStatusModel;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderStatus.
 *
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class OrderStatus extends BaseAction implements EventSubscriberInterface
{
    public function create(OrderStatusCreateEvent $event): void
    {
        $this->createOrUpdate($event, new OrderStatusModel());
    }

    public function update(OrderStatusUpdateEvent $event): void
    {
        $orderStatus = $this->getOrderStatus($event);
        $this->createOrUpdate($event, $orderStatus);
    }

    /**
     * @throws \Exception
     */
    public function delete(OrderStatusDeleteEvent $event): void
    {
        $orderStatus = $this->getOrderStatus($event);

        if ($orderStatus->getProtectedStatus()) {
            throw new \Exception(Translator::getInstance()->trans('This status is protected.').' '.Translator::getInstance()->trans('You can not delete it.'));
        }

        if (null !== OrderQuery::create()->findOneByStatusId($orderStatus->getId())) {
            throw new \Exception(Translator::getInstance()->trans('Some commands use this status.').' '.Translator::getInstance()->trans('You can not delete it.'));
        }

        $orderStatus->delete();

        $event->setOrderStatus($orderStatus);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_STATUS_CREATE => ['create', 128],
            TheliaEvents::ORDER_STATUS_UPDATE => ['update', 128],
            TheliaEvents::ORDER_STATUS_DELETE => ['delete', 128],
            TheliaEvents::ORDER_STATUS_UPDATE_POSITION => ['updatePosition', 128],
        ];
    }

    protected function createOrUpdate(OrderStatusEvent $event, OrderStatusModel $orderStatus): void
    {
        $orderStatus
            ->setCode($orderStatus->getProtectedStatus() ? $orderStatus->getCode() : $event->getCode())
            ->setEquivalentCode($this->resolveEquivalentCode($event, $orderStatus))
            ->setColor($event->getColor())
            // i18n
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->setPostscriptum($event->getPostscriptum())
            ->setChapo($event->getChapo());

        if (null === $orderStatus->getId()) {
            $lastOrderStatus = OrderStatusQuery::create()->orderByPosition(Criteria::DESC)->findOne();
            $orderStatus->setPosition(
                null !== $lastOrderStatus ? $lastOrderStatus->getPosition() + 1 : 1,
            );
        }

        $orderStatus->save();

        $event->setOrderStatus($orderStatus);
    }

    /**
     * A protected (native) status stands for itself and never carries an equivalence. A custom one
     * may only stand for a canonical code.
     */
    protected function resolveEquivalentCode(OrderStatusEvent $event, OrderStatusModel $orderStatus): ?string
    {
        if ($orderStatus->getProtectedStatus()) {
            return null;
        }

        $equivalentCode = $event->getEquivalentCode();

        if (null === $equivalentCode || '' === $equivalentCode) {
            return null;
        }

        if (!\in_array($equivalentCode, OrderStatusModel::CANONICAL_CODES, true)) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('%code is not a canonical order status code.', ['%code' => $equivalentCode]));
        }

        return $equivalentCode;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(OrderStatusQuery::create(), $event, $dispatcher);
    }

    protected function getOrderStatus(OrderStatusUpdateEvent $event): OrderStatusModel
    {
        if (null === $orderStatus = OrderStatusQuery::create()->findOneById($event->getId())) {
            throw new \LogicException('Order status not found');
        }

        return $orderStatus;
    }
}
