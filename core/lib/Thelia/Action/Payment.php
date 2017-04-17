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
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Payment
 * @package Thelia\Action
 * @author Julien Chanséaume <julien@thelia.net>
 */
class Payment implements EventSubscriberInterface
{
    /**
     * Check if a module is valid
     *
     * @param IsValidPaymentEvent $event
     */
    public function isValid(IsValidPaymentEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $module = $event->getModule();

        // dispatch event to target specific module
        $dispatcher->dispatch(
            TheliaEvents::getModuleEvent(
                TheliaEvents::MODULE_PAYMENT_IS_VALID,
                $module->getCode()
            ),
            $event
        );

        if ($event->isPropagationStopped()) {
            return;
        }

        // call legacy module method
        $event->setValidModule($module->isValidPayment());
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::MODULE_PAYMENT_IS_VALID => ['isValid', 128],
        ];
    }
}
