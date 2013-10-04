<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Config as ConfigModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;

class Config extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigCreateEvent $event
     */
    public function create(ConfigCreateEvent $event)
    {
        $config = new ConfigModel();

        $config->setDispatcher($this->getDispatcher())->setName($event->getEventName())->setValue($event->getValue())
                ->setLocale($event->getLocale())->setTitle($event->getTitle())->setHidden($event->getHidden())
                ->setSecured($event->getSecured())->save();

        $event->setConfig($config);
    }

    /**
     * Change a configuration entry value
     *
     * @param \Thelia\Core\Event\Config\ConfigUpdateEvent $event
     */
    public function setValue(ConfigUpdateEvent $event)
    {
        $search = ConfigQuery::create();

        if (null !== $config = $search->findPk($event->getConfigId())) {

            if ($event->getValue() !== $config->getValue()) {

                $config->setDispatcher($this->getDispatcher())->setValue($event->getValue())->save();

                $event->setConfig($config);
            }
        }
    }

    /**
     * Change a configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigUpdateEvent $event
     */
    public function modify(ConfigUpdateEvent $event)
    {
        $search = ConfigQuery::create();

        if (null !== $config = ConfigQuery::create()->findPk($event->getConfigId())) {

            $config->setDispatcher($this->getDispatcher())->setName($event->getEventName())->setValue($event->getValue())
                    ->setHidden($event->getHidden())->setSecured($event->getSecured())->setLocale($event->getLocale())
                    ->setTitle($event->getTitle())->setDescription($event->getDescription())->setChapo($event->getChapo())
                    ->setPostscriptum($event->getPostscriptum())->save();

            $event->setConfig($config);
        }
    }

    /**
     * Delete a configuration entry
     *
     * @param \Thelia\Core\Event\Config\ConfigDeleteEvent $event
     */
    public function delete(ConfigDeleteEvent $event)
    {

        if (null !== ($config = ConfigQuery::create()->findPk($event->getConfigId()))) {

            if (!$config->getSecured()) {

                $config->setDispatcher($this->getDispatcher())->delete();

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
