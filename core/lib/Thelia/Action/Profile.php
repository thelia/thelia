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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Group as GroupModel;
use Thelia\Model\GroupQuery;

class Profile extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param ProfileEvent $event
     */
    public function create(ProfileEvent $event)
    {
        /*$group = new GroupModel();

        $group
            ->setDispatcher($this->getDispatcher())
            ->setRequirements($event->getRequirements())
            ->setType($event->getType())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
        ;

        $group->save();

        $event->setGroup($group);*/
    }

    /**
     * @param ProfileEvent $event
     */
    public function update(ProfileEvent $event)
    {
        if (null !== $group = GroupQuery::create()->findPk($event->getId())) {

            /*$group
                ->setDispatcher($this->getDispatcher())
                ->setRequirements($event->getRequirements())
                ->setType($event->getType())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
            ;

            $group->save();

            $event->setGroup($group);*/
        }
    }

    /**
     * @param ProfileEvent $event
     */
    public function delete(ProfileEvent $event)
    {
        if (null !== $group = GroupQuery::create()->findPk($event->getId())) {

            /*$group
                ->delete()
            ;

            $event->setGroup($group);*/
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PROFILE_CREATE            => array("create", 128),
            TheliaEvents::PROFILE_UPDATE            => array("update", 128),
            TheliaEvents::PROFILE_DELETE            => array("delete", 128),
        );
    }
}
