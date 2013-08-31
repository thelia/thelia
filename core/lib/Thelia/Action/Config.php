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

use Thelia\Core\Event\ConfigChangeEvent;
use Thelia\Core\Event\ConfigCreateEvent;
use Thelia\Core\Event\ConfigDeleteEvent;

class Config extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new configuration entry
     *
     * @param ConfigCreateEvent $event
     */
    public function create(ConfigCreateEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.configuration.variables.create");

        $config = new ConfigModel();

        $config
            ->setDispatcher($this->getDispatcher())

            ->setName($event->getName())
            ->setValue($event->getValue())
            ->setHidden($evetn->getHidden())
            ->setSecured($event->getSecured())

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->setChapo($event->getChapo())
            ->setPostscriptum($event->getPostscriptum())

            ->save()
        ;
    }

    /**
     * Change a configuration entry value
     *
     * @param ConfigChangeEvent $event
     */
    public function setValue(ConfigChangeEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.configuration.variables.change");

        $search = ConfigQuery::create();

        if (null !== $config = $search->findOneById($event->getConfigId())) {

            $config
                ->setDispatcher($this->getDispatcher())
                ->setValue($event->getValue())
                ->save()
            ;
        }
    }

    /**
     * Change a configuration entry
     *
     * @param ConfigChangeEvent $event
     */
    public function modify(ConfigChangeEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.configuration.variables.change");

        $search = ConfigQuery::create();

        if (null !== $config = ConfigQuery::create()->findOneById($event->getConfigId())) {

            $config
                ->setDispatcher($this->getDispatcher())

                ->setName($event->getName())
                ->setValue($event->getValue())
                ->setHidden($evetn->getHidden())
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
     * Delete a configuration entry
     *
     * @param ConfigDeleteEvent $event
     */
    public function delete(ConfigDeleteEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.configuration.variables.delete");

        if (null !== ($config = ConfigQuery::create()->findOneById($event->getConfigId()))) {
            if (! $config->getSecured()) {
                $config->setDispatcher($this->getDispatcher());
                $config->delete();
            }
        }
    }

    /**
     * Returns an array of event names this subscriber listens to.
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CONFIG_CREATE   => array("create", 128),
            TheliaEvents::CONFIG_SETVALUE => array("setValue", 128),
            TheliaEvents::CONFIG_MODIFY   => array("modify", 128),
            TheliaEvents::CONFIG_DELETE   => array("delete", 128),
        );
    }
}
