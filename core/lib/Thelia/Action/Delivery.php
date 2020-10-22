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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Module\AbstractDeliveryModule;

/**
 * Class Delivery
 * @package Thelia\Action
 * @author Julien Chanséaume <julien@thelia.net>
 */
class Delivery implements EventSubscriberInterface
{
    /**
     * Get postage from module using the classical module functions
     *
     * @param DeliveryPostageEvent $event
     */
    public function getPostage(DeliveryPostageEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        /** @var AbstractDeliveryModule $module */
        $module = $event->getModule();

        // dispatch event to target specific module
        $dispatcher->dispatch(
            TheliaEvents::getModuleEvent(
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                $module->getCode()
            ),
            $event
        );

        if ($event->isPropagationStopped()) {
            return;
        }

        // call legacy module method
        $event->setValidModule($module->isValidDelivery($event->getCountry(), $event->getState()))
            ->setDeliveryMode($module->getDeliveryMode());

        if ($event->isValidModule()) {
            $event->setPostage($module->getPostage($event->getCountry(), $event->getState()));
        }
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE => ['getPostage', 128]
        ];
    }
}
