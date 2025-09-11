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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\DeliveryModuleWithStateInterface;

/**
 * Class Delivery.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class Delivery implements EventSubscriberInterface
{
    public function __construct(protected EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * Get postage from the module using the classical module functions.
     *
     * @throws PropelException
     * @throws \Exception
     */
    public function getPostage(DeliveryPostageEvent $event): void
    {
        /** @var AbstractDeliveryModule $module */
        $module = $event->getModule();

        // dispatch event to target specific module
        $this->dispatcher->dispatch(
            $event,
            TheliaEvents::getModuleEvent(
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                $module->getCode(),
            ),
        );

        if ($event->isPropagationStopped()) {
            return;
        }

        // Add state param to isValidDelivery only if the module handles state
        $isValidModule = $module instanceof DeliveryModuleWithStateInterface
            ? $module->isValidDelivery($event->getCountry(), $event->getState())
            : $module->isValidDelivery($event->getCountry());

        $event->setValidModule($isValidModule)
            ->setDeliveryMode($module->getDeliveryMode());

        if ($event->isValidModule()) {
            // Add state param to getPostage only if the module handles state
            $modulePostage = $module instanceof DeliveryModuleWithStateInterface
                ? $module->getPostage($event->getCountry(), $event->getState())
                : $module->getPostage($event->getCountry());

            $event->setPostage($modulePostage);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE => ['getPostage', 128],
        ];
    }
}
