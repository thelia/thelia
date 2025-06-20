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
     * Create a new configuration entry.
     */
    public function create(ConfigCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $config = new ConfigModel();

        $config
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
     * Change a configuration entry value.
     */
    public function setValue(ConfigUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== ($config = ConfigQuery::create()->findPk($event->getConfigId())) && $event->getValue() !== $config->getValue()) {
            $config->setValue($event->getValue())->save();
            $event->setConfig($config);
        }
    }

    /**
     * Change a configuration entry.
     */
    public function modify(ConfigUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $config = ConfigQuery::create()->findPk($event->getConfigId())) {
            $config
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
        }
    }

    /**
     * Delete a configuration entry.
     */
    public function delete(ConfigDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== ($config = ConfigQuery::create()->findPk($event->getConfigId())) && !$config->getSecured()) {
            $config->delete();
            $event->setConfig($config);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
                TheliaEvents::CONFIG_CREATE => [
                    'create', 128,
                ], TheliaEvents::CONFIG_SETVALUE => [
                    'setValue', 128,
                ], TheliaEvents::CONFIG_UPDATE => [
                    'modify', 128,
                ], TheliaEvents::CONFIG_DELETE => [
                    'delete', 128,
                ],
        ];
    }
}
