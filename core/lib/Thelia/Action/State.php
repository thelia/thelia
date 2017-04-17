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
use Thelia\Core\Event\State\StateCreateEvent;
use Thelia\Core\Event\State\StateDeleteEvent;
use Thelia\Core\Event\State\StateToggleVisibilityEvent;
use Thelia\Core\Event\State\StateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\State as StateModel;
use Thelia\Model\StateQuery;

/**
 * Class State
 * @package Thelia\Action
 * @author Julien Chanséaume <julien@thelia.net>
 */
class State extends BaseAction implements EventSubscriberInterface
{
    public function create(StateCreateEvent $event)
    {
        $state = new StateModel();

        $state
            ->setVisible($event->isVisible())
            ->setCountryId($event->getCountry())
            ->setIsocode($event->getIsocode())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save()
        ;

        $event->setState($state);
    }

    public function update(StateUpdateEvent $event)
    {
        if (null !== $state = StateQuery::create()->findPk($event->getStateId())) {
            $state
                ->setVisible($event->isVisible())
                ->setCountryId($event->getCountry())
                ->setIsocode($event->getIsocode())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->save()
            ;

            $event->setState($state);
        }
    }

    public function delete(StateDeleteEvent $event)
    {
        if (null !== $state = StateQuery::create()->findPk($event->getStateId())) {
            $state->delete();

            $event->setState($state);
        }
    }

    /**
     * Toggle State visibility
     *
     * @param StateToggleVisibilityEvent $event
     */
    public function toggleVisibility(StateToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $state = $event->getState();

        $state
            ->setDispatcher($dispatcher)
            ->setVisible(!$state->getVisible())
            ->save()
        ;

        $event->setState($state);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::STATE_CREATE => array('create', 128),
            TheliaEvents::STATE_UPDATE => array('update', 128),
            TheliaEvents::STATE_DELETE => array('delete', 128),
            TheliaEvents::STATE_TOGGLE_VISIBILITY => array('toggleVisibility', 128)
        );
    }
}
