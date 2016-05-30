<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

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
 * Class OrderStatus
 * @package Thelia\Action
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 * @since 2.4
 */
class OrderStatus extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param OrderStatusCreateEvent $event
     */
    public function create(OrderStatusCreateEvent $event)
    {
        $this->createOrUpdate($event, new OrderStatusModel());
    }

    /**
     * @param OrderStatusUpdateEvent $event
     */
    public function update(OrderStatusUpdateEvent $event)
    {
        $orderStatus = $this->getOrderStatus($event);
        $this->createOrUpdate($event, $orderStatus);
    }

    /**
     * @param OrderStatusDeleteEvent $event
     * @throws \Exception
     */
    public function delete(OrderStatusDeleteEvent $event)
    {
        $orderStatus = $this->getOrderStatus($event);

        if ($orderStatus->getProtectedStatus()) {
            throw new \Exception(
                Translator::getInstance()->trans('This status is protected.')
                . ' ' . Translator::getInstance()->trans('You can not delete it.')
            );
        }

        if (null !== OrderQuery::create()->findOneByStatusId($orderStatus->getId())) {
            throw new \Exception(
                Translator::getInstance()->trans('Some commands use this status.')
                . ' ' . Translator::getInstance()->trans('You can not delete it.')
            );
        }

        $orderStatus->delete();

        $event->setOrderStatus($orderStatus);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_STATUS_CREATE => ["create", 128],
            TheliaEvents::ORDER_STATUS_UPDATE => ["update", 128],
            TheliaEvents::ORDER_STATUS_DELETE => ["delete", 128],
            TheliaEvents::ORDER_STATUS_UPDATE_POSITION => ["updatePosition", 128]
        );
    }

    /**
     * @param OrderStatusEvent $event
     * @param OrderStatusModel $orderStatus
     */
    protected function createOrUpdate(OrderStatusEvent $event, OrderStatusModel $orderStatus)
    {
        $orderStatus
            ->setCode(!$orderStatus->getProtectedStatus() ? $event->getCode() : $orderStatus->getCode())
            ->setColor($event->getColor())
            // i18n
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->setPostscriptum($event->getPostscriptum())
            ->setChapo($event->getChapo());

        if ($orderStatus->getId() === null) {
            $orderStatus->setPosition(
                OrderStatusQuery::create()->orderByPosition(Criteria::DESC)->findOne()->getPosition() + 1
            );
        }

        $orderStatus->save();

        $event->setOrderStatus($orderStatus);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(OrderStatusQuery::create(), $event, $dispatcher);
    }

    /**
     * @param OrderStatusUpdateEvent $event
     * @return OrderStatusModel
     */
    protected function getOrderStatus(OrderStatusUpdateEvent $event)
    {
        if (null === $orderStatus = OrderStatusQuery::create()->findOneById($event->getId())) {
            throw new \LogicException(
                "Order status not found"
            );
        }

        return $orderStatus;
    }
}
