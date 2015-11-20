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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Loop\LoopExtendsEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Loop
 * @package Thelia\Action
 * @author Julien Chanséaume <julien@thelia.net>
 */
class Loop extends BaseAction implements EventSubscriberInterface
{

    /**
     * This function dispatch the event for specific loop
     * if the event contains a loop with a loop name
     *
     * @param LoopExtendsEvent $event
     */
    public function dispatchForLoop(LoopExtendsEvent $event)
    {
        if (null !== $event->getLoopName()) {
            $eventName = $event->getName();
            $event
                ->getDispatcher()
                ->dispatch(
                    TheliaEvents::getLoopExtendsEvent(
                        $event->getName(),
                        $event->getLoopName()
                    ),
                    $event
                )
            ;
            $event->setName($eventName);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::LOOP_EXTENDS_ARG_DEFINITIONS => ['dispatchForLoop', 128],
            TheliaEvents::LOOP_EXTENDS_INITIALIZE_ARGS => ['dispatchForLoop', 128],
            TheliaEvents::LOOP_EXTENDS_BUILD_MODEL_CRITERIA => ['dispatchForLoop', 128],
            TheliaEvents::LOOP_EXTENDS_BUILD_ARRAY => ['dispatchForLoop', 128],
            TheliaEvents::LOOP_EXTENDS_PARSE_RESULTS => ['dispatchForLoop', 128]
        ];
    }
}
