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
use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Config as ConfigModel;
use Thelia\Model\ConfigQuery;

class Config extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigCreateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(ConfigCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $config = new ConfigModel();

        $config->setDispatcher($dispatcher)
            ->setName($event->getEventName())
            ->setValue($event->getValue())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setHidden($event->getHidden())
            ->setSecured($event->getSecured())
        ->save();

        $event->setConfig($config);
    }

    /**
     * Change a configuration entry value
     *
     * @param \Thelia\Core\Event\Config\ConfigUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function setValue(ConfigUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $config = ConfigQuery::create()->findPk($event->getConfigId())) {
            if ($event->getValue() !== $config->getValue()) {
                $config->setDispatcher($dispatcher)->setValue($event->getValue())->save();

                $event->setConfig($config);
            }
        }
    }

    /**
     * Change a configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function modify(ConfigUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $config = ConfigQuery::create()->findPk($event->getConfigId())) {
            $config->setDispatcher($dispatcher)
                ->setName($event->getEventName())
                ->setValue($event->getValue())
                ->setHidden($event->getHidden())
                ->setSecured($event->getSecured())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
            ->save();

            $event->setConfig($config);
        }
    }

    /**
     * Delete a configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function delete(ConfigDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($config = ConfigQuery::create()->findPk($event->getConfigId()))) {
            if (!$config->getSecured()) {
                $config->setDispatcher($dispatcher)->delete();

                $event->setConfig($config);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
                TheliaEvents::CONFIG_CREATE => array(
                    "create", 128
                ), TheliaEvents::CONFIG_SETVALUE => array(
                    "setValue", 128
                ), TheliaEvents::CONFIG_UPDATE => array(
                    "modify", 128
                ), TheliaEvents::CONFIG_DELETE => array(
                    "delete", 128
                ),
        );
    }
}
